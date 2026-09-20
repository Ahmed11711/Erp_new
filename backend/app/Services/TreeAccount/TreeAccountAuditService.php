<?php

namespace App\Services\TreeAccount;

use App\Models\TreeAccount;
use App\Models\TreeAccountAudit;

final class TreeAccountAuditService
{
    /** حقول دليل الحسابات التي يُسجَّل تغييرها (لا يشمل الرصيد من القيود). */
    public const AUDITABLE_FIELDS = [
        'name',
        'name_en',
        'type',
        'parent_id',
        'is_trading_account',
        'budget_type',
        'budget_amount',
        'budget_period',
        'main_account_id',
        'account_type',
        'detail_type',
    ];

    public function logCreated(TreeAccount $account): void
    {
        if (! $this->shouldAudit()) {
            return;
        }

        $this->insert('created', $account, null);
    }

    /**
     * @param  array<string, mixed>  $original  قيم الحقول قبل التحديث
     */
    public function logUpdated(TreeAccount $account, array $original): void
    {
        if (! $this->shouldAudit()) {
            return;
        }

        $changes = $this->buildChangeSet($account, $original);
        if ($changes === []) {
            return;
        }

        $this->insert('updated', $account, $changes);
    }

    public function logDeleted(TreeAccount $account): void
    {
        if (! $this->shouldAudit()) {
            return;
        }

        $this->insert('deleted', $account, null);
    }

    public function logRestored(TreeAccount $account): void
    {
        if (! $this->shouldAudit()) {
            return;
        }

        $this->insert('restored', $account, null);
    }

    public function logForceDeleted(TreeAccount $account): void
    {
        if (! $this->shouldAudit()) {
            return;
        }

        $this->insert('force_deleted', $account, null);
    }

    public function hasAuditableDirty(TreeAccount $account): bool
    {
        return $this->auditableDirtyKeys($account) !== [];
    }

    /**
     * @return list<string>
     */
    public function auditableDirtyKeys(TreeAccount $account): array
    {
        return array_values(array_intersect(
            array_keys($account->getDirty()),
            self::AUDITABLE_FIELDS
        ));
    }

    /**
     * @param  array<string, mixed>  $original
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function buildChangeSet(TreeAccount $account, array $original): array
    {
        $changes = [];

        foreach ($this->auditableDirtyKeys($account) as $field) {
            $changes[$field] = [
                'old' => $original[$field] ?? null,
                'new' => $account->getAttribute($field),
            ];
        }

        return $changes;
    }

    private function insert(string $action, TreeAccount $account, ?array $changes): void
    {
        TreeAccountAudit::create([
            'tree_account_id' => $account->id,
            'action' => $action,
            'performed_by' => auth()->id(),
            'account_code' => (string) $account->code,
            'account_name' => (string) $account->name,
            'parent_id' => $account->parent_id,
            'changes' => $changes,
        ]);
    }

    private function shouldAudit(): bool
    {
        return auth()->check();
    }
}
