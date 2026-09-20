<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\SystemLock\SystemLockOrdersPreviewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SystemLockOrdersPreviewServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_preview_returns_at_most_ten_orders_with_lookups(): void
    {
        $user = User::factory()->create(['department' => 'Customer Service']);
        $payload = app(SystemLockOrdersPreviewService::class)->payload($user);

        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('lookups', $payload);
        $this->assertLessThanOrEqual(SystemLockOrdersPreviewService::LIMIT, count($payload['data']));
        $this->assertSame(SystemLockOrdersPreviewService::LIMIT, $payload['per_page']);
        $this->assertLessThanOrEqual(SystemLockOrdersPreviewService::LIMIT, $payload['total']);
        $this->assertArrayHasKey('companies', $payload['lookups']);
        $this->assertArrayHasKey('order_sources', $payload['lookups']);
        $this->assertArrayHasKey('shipping_ways', $payload['lookups']);
        $this->assertArrayHasKey('shipping_lines', $payload['lookups']);
    }
}
