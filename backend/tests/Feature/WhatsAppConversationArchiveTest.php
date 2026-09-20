<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WhatsAppConversationArchiveTest extends TestCase
{
    use DatabaseTransactions;

    public function test_inbox_excludes_archived_customers(): void
    {
        $user = $this->makeUser();
        [$inbox, $archived] = $this->seedPair();

        $res = $this->actingAs($user)->getJson('/api/whatsapp/customers?per_page=50&archive=inbox');

        $res->assertOk()->assertJsonPath('success', true);
        $ids = collect($res->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($inbox->id));
        $this->assertFalse($ids->contains($archived->id));
        $this->assertGreaterThanOrEqual(1, (int) $res->json('archive_counts.inbox'));
        $this->assertGreaterThanOrEqual(1, (int) $res->json('archive_counts.archived'));
    }

    public function test_archived_filter_returns_only_archived_customers(): void
    {
        $user = $this->makeUser();
        [$inbox, $archived] = $this->seedPair();

        $res = $this->actingAs($user)->getJson('/api/whatsapp/customers?per_page=50&archive=archived');

        $res->assertOk()->assertJsonPath('success', true);
        $ids = collect($res->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($archived->id));
        $this->assertFalse($ids->contains($inbox->id));
        $row = collect($res->json('data.data'))->firstWhere('id', $archived->id);
        $this->assertTrue((bool) ($row['is_archived'] ?? false));
    }

    public function test_patch_archives_and_restores_conversation(): void
    {
        $user = $this->makeUser();
        [$inbox] = $this->seedPair();

        Message::create([
            'customer_id' => $inbox->id,
            'content' => 'agent reply',
            'type' => 'text',
            'direction' => 'outbound',
            'status' => 'sent',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/whatsapp/customers/'.$inbox->id.'/archive', ['archived' => true])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_archived', true);

        $this->assertNotNull($inbox->fresh()->whatsapp_archived_at);

        $this->actingAs($user)
            ->patchJson('/api/whatsapp/customers/'.$inbox->id.'/archive', ['archived' => false])
            ->assertOk()
            ->assertJsonPath('data.is_archived', false);

        $this->assertNull($inbox->fresh()->whatsapp_archived_at);
    }

    public function test_agent_reply_archives_and_inbound_restores(): void
    {
        [$inbox] = $this->seedPair();
        $this->assertNull($inbox->fresh()->whatsapp_archived_at);

        $inbox->archiveWhatsappConversation();
        $this->assertNotNull($inbox->fresh()->whatsapp_archived_at);

        $this->postJson('/api/meta/webhook', $this->inboundPayload($inbox->phone))
            ->assertOk();

        $this->assertNull($inbox->fresh()->whatsapp_archived_at);
    }

    public function test_inbound_message_restores_archived_conversation(): void
    {
        [, $archived] = $this->seedPair();
        $this->assertNotNull($archived->fresh()->whatsapp_archived_at);

        $this->postJson('/api/meta/webhook', $this->inboundPayload($archived->phone))
            ->assertOk();

        $this->assertNull($archived->fresh()->whatsapp_archived_at);
    }

    public function test_messages_payload_includes_archive_flag(): void
    {
        $user = $this->makeUser();
        [, $archived] = $this->seedPair();

        $this->actingAs($user)
            ->getJson('/api/conversations/'.$archived->id.'/messages?limit=10')
            ->assertOk()
            ->assertJsonPath('conversation.is_archived', true);
    }

    public function test_archive_all_moves_inbox_and_keeps_already_archived(): void
    {
        $user = $this->makeUser();
        [$inbox, $archived] = $this->seedPair();

        Message::create([
            'customer_id' => $inbox->id,
            'content' => 'agent reply',
            'type' => 'text',
            'direction' => 'outbound',
            'status' => 'sent',
        ]);

        $res = $this->actingAs($user)
            ->postJson('/api/whatsapp/customers/archive-all');

        $res->assertOk()
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, (int) $res->json('data.archived_count'));
        $this->assertNotNull($inbox->fresh()->whatsapp_archived_at);
        $this->assertNotNull($archived->fresh()->whatsapp_archived_at);
    }

    public function test_cannot_archive_conversation_awaiting_reply(): void
    {
        $user = $this->makeUser();
        [$inbox] = $this->seedPair();

        $this->actingAs($user)
            ->patchJson('/api/whatsapp/customers/'.$inbox->id.'/archive', ['archived' => true])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNull($inbox->fresh()->whatsapp_archived_at);
    }

    public function test_archive_all_skips_conversations_awaiting_reply(): void
    {
        $user = $this->makeUser();
        [$inbox] = $this->seedPair();

        $this->actingAs($user)
            ->postJson('/api/whatsapp/customers/archive-all')
            ->assertOk();

        $this->assertNull($inbox->fresh()->whatsapp_archived_at);
    }

    public function test_customers_payload_marks_awaiting_reply(): void
    {
        $user = $this->makeUser();
        [$inbox] = $this->seedPair();

        $res = $this->actingAs($user)->getJson('/api/whatsapp/customers?per_page=50&archive=inbox');
        $res->assertOk();
        $row = collect($res->json('data.data'))->firstWhere('id', $inbox->id);
        $this->assertTrue((bool) ($row['awaiting_reply'] ?? false));
    }

    public function test_storing_inbound_message_restores_archived_conversation(): void
    {
        [, $archived] = $this->seedPair();
        $this->assertNotNull($archived->fresh()->whatsapp_archived_at);

        Message::create([
            'customer_id' => $archived->id,
            'content' => 'رد جديد من العميل',
            'type' => 'text',
            'direction' => 'inbound',
            'status' => 'received',
        ]);

        $this->assertNull($archived->fresh()->whatsapp_archived_at);
    }

    public function test_inbound_restores_when_stored_phone_format_differs(): void
    {
        $suffix = substr(str_replace('.', '', uniqid('', true)), -7);
        $archived = Customer::create([
            'name' => 'Local format '.$suffix,
            'phone' => '0101'.$suffix,
        ]);
        Message::create([
            'customer_id' => $archived->id,
            'content' => 'old',
            'type' => 'text',
            'direction' => 'outbound',
            'status' => 'sent',
        ]);
        $archived->archiveWhatsappConversation();
        $this->assertNotNull($archived->fresh()->whatsapp_archived_at);

        $this->postJson('/api/meta/webhook', $this->inboundPayload('20101'.$suffix))
            ->assertOk();

        $this->assertNull($archived->fresh()->whatsapp_archived_at);
        $this->assertSame(1, Customer::query()->where('phone', 'like', '%'.$suffix)->count());
    }

    public function test_listing_customers_restores_archived_when_inbound_bypassed_model_events(): void
    {
        $user = $this->makeUser();
        [, $archived] = $this->seedPair();
        $this->assertNotNull($archived->fresh()->whatsapp_archived_at);

        \Illuminate\Support\Facades\DB::table('messages')->insert([
            'customer_id' => $archived->id,
            'content' => 'رسالة وصلت بدون فك أرشفة',
            'type' => 'text',
            'direction' => 'inbound',
            'status' => 'received',
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);
        $this->assertNotNull($archived->fresh()->whatsapp_archived_at);

        $res = $this->actingAs($user)->getJson('/api/whatsapp/customers?per_page=50&archive=inbox');
        $res->assertOk()->assertJsonPath('success', true);
        $ids = collect($res->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($archived->id));
        $this->assertNull($archived->fresh()->whatsapp_archived_at);
    }

    /**
     * @return array{0: Customer, 1: Customer}
     */
    private function seedPair(): array
    {
        $suffix = substr(str_replace('.', '', uniqid('', true)), -7);

        $inbox = Customer::create([
            'name' => 'Inbox '.$suffix,
            'phone' => '+20100'.$suffix,
        ]);
        $archived = Customer::create([
            'name' => 'Archived '.$suffix,
            'phone' => '+20101'.$suffix,
        ]);

        Message::create([
            'customer_id' => $inbox->id,
            'content' => 'hello inbox',
            'type' => 'text',
            'direction' => 'inbound',
            'status' => 'received',
        ]);
        Message::create([
            'customer_id' => $archived->id,
            'content' => 'hello archived',
            'type' => 'text',
            'direction' => 'inbound',
            'status' => 'received',
        ]);

        $archived->refresh();
        $archived->archiveWhatsappConversation();

        return [$inbox->fresh(), $archived->fresh()];
    }

    private function inboundPayload(string $phone): array
    {
        $digits = ltrim(preg_replace('/\D/', '', $phone) ?: $phone, '+');

        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => 'test-phone'],
                        'contacts' => [[
                            'wa_id' => $digits,
                            'profile' => ['name' => 'Customer'],
                        ]],
                        'messages' => [[
                            'from' => $digits,
                            'id' => 'wamid.archive.'.uniqid(),
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => ['body' => 'رسالة جديدة بعد الأرشفة'],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    private function makeUser(): User
    {
        $user = User::factory()->create([
            'department' => 'Admin',
            'email' => 'wa_archive_'.uniqid().'@test.local',
        ]);
        config(['rbac.super_admin_emails' => [strtolower($user->email)]]);

        return $user;
    }
}
