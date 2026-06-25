<?php

namespace App\Observers;

use App\Models\TreeAccount;
use App\Services\TreeAccount\TreeAccountAuditService;

class TreeAccountObserver
{
    /** @var array<int, array<string, mixed>> */
    private static array $updateOriginals = [];

    public function __construct(
        private readonly TreeAccountAuditService $auditService,
    ) {
    }

    public function creating(TreeAccount $account): void
    {
        if (auth()->check() && ! $account->created_by) {
            $account->created_by = auth()->id();
        }
    }

    public function created(TreeAccount $account): void
    {
        $this->auditService->logCreated($account);
    }

    public function updating(TreeAccount $account): void
    {
        if (! $this->auditService->hasAuditableDirty($account)) {
            return;
        }

        self::$updateOriginals[(int) $account->id] = $account->getOriginal();

        if (auth()->check()) {
            $account->updated_by = auth()->id();
        }
    }

    public function updated(TreeAccount $account): void
    {
        $original = self::$updateOriginals[(int) $account->id] ?? null;
        unset(self::$updateOriginals[(int) $account->id]);

        if ($original === null) {
            return;
        }

        $this->auditService->logUpdated($account, $original);
    }

    public function deleting(TreeAccount $account): void
    {
        $this->auditService->logDeleted($account);
    }
}
