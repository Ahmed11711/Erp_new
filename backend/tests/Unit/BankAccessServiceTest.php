<?php

namespace Tests\Unit;

use App\Models\Bank;
use App\Models\User;
use App\Services\Accounting\BankAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unrestricted_bank_visible_to_any_user(): void
    {
        $bank = Bank::create([
            'name' => 'Main',
            'type' => 'main',
            'balance' => 0,
            'usage' => 'test',
        ]);
        $user = User::factory()->create(['department' => 'Data Entry']);

        $service = app(BankAccessService::class);

        $this->assertTrue($service->userCanAccessBank($user, $bank->id));
    }

    public function test_restricted_bank_only_visible_to_assigned_user(): void
    {
        $bank = Bank::create([
            'name' => 'Restricted',
            'type' => 'main',
            'balance' => 0,
            'usage' => 'test',
        ]);
        $allowed = User::factory()->create(['department' => 'Data Entry']);
        $blocked = User::factory()->create(['department' => 'Data Entry']);

        $bank->assignedUsers()->attach($allowed->id);

        $service = app(BankAccessService::class);

        $this->assertTrue($service->userCanAccessBank($allowed, $bank->id));
        $this->assertFalse($service->userCanAccessBank($blocked, $bank->id));
    }
}
