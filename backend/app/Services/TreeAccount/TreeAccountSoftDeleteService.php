<?php

namespace App\Services\TreeAccount;

use App\Models\TreeAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TreeAccountSoftDeleteService
{
    /**
     * Soft-delete an account and all active descendants.
     */
    public function softDelete(TreeAccount $account): void
    {
        DB::transaction(function () use ($account) {
            $this->softDeleteChildren($account);
            if (! $account->trashed()) {
                $account->delete();
            }
        });
    }

    /**
     * Restore a soft-deleted account and its soft-deleted descendants.
     *
     * @throws \InvalidArgumentException
     */
    public function restore(TreeAccount $account): TreeAccount
    {
        if (! $account->trashed()) {
            throw new \InvalidArgumentException('الحساب غير موجود في سلة المحذوفات');
        }

        if ($account->parent_id) {
            $parent = TreeAccount::withTrashed()->find($account->parent_id);
            if (! $parent) {
                throw new \InvalidArgumentException('الحساب الأب غير موجود؛ لا يمكن الاسترجاع');
            }
            if ($parent->trashed()) {
                throw new \InvalidArgumentException(
                    'يجب استرجاع الحساب الأب «'.$parent->name.'» أولاً'
                );
            }
        }

        DB::transaction(function () use ($account) {
            $this->restoreWithChildren($account);
        });

        return $account->fresh(['children', 'parent', 'createdByUser:id,name', 'updatedByUser:id,name']);
    }

    /**
     * Permanently delete a soft-deleted account and its soft-deleted descendants.
     * Hard delete cascades related journal lines via DB foreign keys.
     *
     * @throws \InvalidArgumentException
     */
    public function forceDelete(TreeAccount $account): void
    {
        if (! $account->trashed()) {
            throw new \InvalidArgumentException('الحذف النهائي متاح فقط من سلة المحذوفات');
        }

        DB::transaction(function () use ($account) {
            $this->forceDeleteChildren($account);
            $account->forceDelete();
        });
    }

    /**
     * Soft-deleted accounts that form the root of a deleted subtree
     * (parent missing or still active — not itself in trash).
     *
     * @return Collection<int, TreeAccount>
     */
    public function listTrash(): Collection
    {
        return TreeAccount::onlyTrashed()
            ->with(['parent:id,name,code,deleted_at', 'createdByUser:id,name'])
            ->where(function ($q) {
                $q->whereNull('parent_id')
                    ->orWhereHas('parent');
            })
            ->orderByDesc('deleted_at')
            ->get();
    }

    private function softDeleteChildren(TreeAccount $account): void
    {
        $children = TreeAccount::query()
            ->where('parent_id', $account->id)
            ->get();

        foreach ($children as $child) {
            $this->softDeleteChildren($child);
            $child->delete();
        }
    }

    private function restoreWithChildren(TreeAccount $account): void
    {
        $account->restore();

        $children = TreeAccount::onlyTrashed()
            ->where('parent_id', $account->id)
            ->get();

        foreach ($children as $child) {
            $this->restoreWithChildren($child);
        }
    }

    private function forceDeleteChildren(TreeAccount $account): void
    {
        $children = TreeAccount::onlyTrashed()
            ->where('parent_id', $account->id)
            ->get();

        foreach ($children as $child) {
            $this->forceDeleteChildren($child);
            $child->forceDelete();
        }
    }
}
