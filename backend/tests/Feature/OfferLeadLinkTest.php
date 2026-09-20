<?php

namespace Tests\Feature;

use App\Models\CorporateSalesIndustry;
use App\Models\CorporateSalesLead;
use App\Models\CorporateSalesLeadSource;
use App\Models\CorporateSalesLeadTool;
use App\Models\CorporateSalesTracking;
use App\Models\Offers;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfferLeadLinkTest extends TestCase
{
    use DatabaseTransactions;

    public function test_corporate_user_can_create_quotation_linked_to_lead(): void
    {
        [$user, $lead] = $this->makeCorporateUserAndLead();

        $response = $this->actingAs($user, 'api')->postJson('/api/offer', [
            'offer' => 'offer1',
            'quote' => $lead->company_name,
            'contact_person' => 'Sales Contact',
            'client_phone' => '01000000000',
            'corporate_sales_lead_id' => $lead->id,
            'dateFrom' => '2026-09-06',
            'dateTo' => '2026-09-20',
            'subtotal' => 100,
            'vat' => 14,
            'transportation' => 0,
            'total' => 114,
            'phone_number' => '+201118127345',
            'email' => 'info@magalis-egypt.com',
            'categories' => [
                [
                    'category_name' => 'Chair',
                    'category_quantity' => 1,
                    'old_category_price' => 0,
                    'new_category_price' => 100,
                    'total_price' => 100,
                ],
            ],
        ]);

        $response->assertCreated();
        $offerId = (int) $response->json('id');
        $this->assertGreaterThan(0, $offerId);

        $offer = Offers::query()->find($offerId);
        $this->assertNotNull($offer);
        $this->assertSame($lead->id, (int) $offer->corporate_sales_lead_id);

        $leadResponse = $this->actingAs($user, 'api')->getJson('/api/lead/'.$lead->id);
        $leadResponse->assertOk();
        $offerIds = collect($leadResponse->json('offers'))->pluck('id')->all();
        $this->assertContains($offerId, $offerIds);

        $listResponse = $this->actingAs($user, 'api')->getJson('/api/lead?itemsPerPage=50&page=1');
        $listResponse->assertOk();
        $listed = collect($listResponse->json('data'))->firstWhere('id', $lead->id);
        $this->assertNotNull($listed);
        $this->assertContains($offerId, collect($listed['offers'] ?? [])->pluck('id')->all());

        $this->assertTrue(
            CorporateSalesTracking::query()
                ->where('corporate_sales_lead_id', $lead->id)
                ->where('details', 'add Quotation')
                ->where('new_value', 'Offer #'.$offerId)
                ->exists()
        );
    }

    public function test_store_recalculates_vat_on_price_after_discount(): void
    {
        [$user] = $this->makeCorporateUserAndLead();

        $response = $this->actingAs($user, 'api')->postJson('/api/offer', [
            'offer' => 'offer1',
            'quote' => 'sunrise remal',
            'contact_person' => 'hussein',
            'dateFrom' => '2026-08-03',
            'dateTo' => '2026-08-10',
            'subtotal' => 40300,
            'vat' => 5936,
            'transportation' => 0,
            'total' => 46236,
            'phone_number' => '+201118127345',
            'email' => 'info@magalis-egypt.com',
            'categories' => [
                [
                    'category_name' => 'bavo',
                    'category_quantity' => 20,
                    'old_category_price' => 2120,
                    'new_category_price' => 2015,
                    'total_price' => 40300,
                ],
            ],
        ]);

        $response->assertCreated();
        $offer = Offers::query()->find((int) $response->json('id'));
        $this->assertNotNull($offer);
        $this->assertEqualsWithDelta(40300.0, (float) $offer->subtotal, 0.001);
        $this->assertEqualsWithDelta(5642.0, (float) $offer->vat, 0.001);
        $this->assertEqualsWithDelta(45942.0, (float) $offer->total, 0.001);

        $show = $this->actingAs($user, 'api')->getJson('/api/offer/'.$offer->id);
        $show->assertOk()
            ->assertJsonPath('vat', 5642)
            ->assertJsonPath('total', 45942);
    }

    public function test_corporate_user_can_link_existing_quotation_to_lead(): void
    {
        [$user, $lead] = $this->makeCorporateUserAndLead();

        $offer = Offers::query()->create([
            'user_id' => $user->id,
            'offer' => 'offer1',
            'quote' => 'Unlinked client',
            'contact_person' => 'Someone',
            'dateFrom' => '2026-09-06',
            'dateTo' => '2026-09-20',
            'subtotal' => 50,
            'vat' => 0,
            'transportation' => 0,
            'total' => 50,
            'phone_number' => '+201118127345',
            'email' => 'info@magalis-egypt.com',
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/offer/'.$offer->id.'/link-lead', [
            'corporate_sales_lead_id' => $lead->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('offer.corporate_sales_lead_id', $lead->id)
            ->assertJsonPath('offer.lead.id', $lead->id);

        $this->assertSame($lead->id, (int) $offer->fresh()->corporate_sales_lead_id);
    }

    /**
     * @return array{0: User, 1: CorporateSalesLead}
     */
    private function makeCorporateUserAndLead(): array
    {
        $user = User::factory()->create([
            'department' => 'Corparates',
            'email' => 'lead_quote_'.Str::lower(Str::random(8)).'@test.local',
        ]);

        $industry = CorporateSalesIndustry::query()->create([
            'name' => 'Industry '.Str::random(8),
            'user_id' => $user->id,
        ]);
        $source = CorporateSalesLeadSource::query()->create([
            'name' => 'Source '.Str::random(8),
            'user_id' => $user->id,
        ]);
        $tool = CorporateSalesLeadTool::query()->create([
            'name' => 'Tool '.Str::random(8),
            'user_id' => $user->id,
        ]);

        $lead = CorporateSalesLead::query()->create([
            'date' => '2026-09-06',
            'company_name' => 'Acme Lead '.Str::random(6),
            'country_name' => 'Egypt',
            'company_facebook' => '',
            'company_instagram' => '',
            'company_linkedin' => '',
            'company_website' => '',
            'corporate_sales_industry_id' => $industry->id,
            'corporate_sales_lead_source_id' => $source->id,
            'corporate_sales_lead_tool_id' => $tool->id,
            'user_id' => $user->id,
        ]);

        return [$user, $lead];
    }
}
