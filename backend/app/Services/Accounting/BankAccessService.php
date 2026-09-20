<?php

namespace App\Services\Accounting;

use App\Models\Bank;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * تحكم في ظهور/استخدام البنوك حسب المستخدمين المعيّنين.
 * بنك بدون مستخدمين معيّنين = متاح للجميع (توافق مع البيانات القديمة).
 */
class BankAccessService
{
    /** @var list<string> */
    private const MANAGE_ACCESS_PERMISSIONS = ['system.rbac', 'finance.edit'];

    public function canManageAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->department === 'Admin') {
            return true;
        }

        return has_any_permission(self::MANAGE_ACCESS_PERMISSIONS);
    }

    public function bypassesFilter(?User $user): bool
    {
        return $this->canManageAccess($user);
    }

    /**
     * @param  Builder<Bank>  $query
     * @return Builder<Bank>
     */
    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user || $this->bypassesFilter($user)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user): void {
            $q->whereDoesntHave('assignedUsers')
                ->orWhereHas('assignedUsers', fn (Builder $uq) => $uq->where('users.id', $user->id));
        });
    }

    public function userCanAccessBank(?User $user, int $bankId): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->bypassesFilter($user)) {
            return Bank::query()->whereKey($bankId)->exists();
        }

        return Bank::query()
            ->whereKey($bankId)
            ->where(function (Builder $q) use ($user): void {
                $q->whereDoesntHave('assignedUsers')
                    ->orWhereHas('assignedUsers', fn (Builder $uq) => $uq->where('users.id', $user->id));
            })
            ->exists();
    }

    /**
     * @return list<array{id:int,name:string,email:?string,department:?string}>
     */
    public function assignedUsersForBank(int $bankId): array
    {
        $bank = Bank::query()->with(['assignedUsers:id,name,email,department'])->find($bankId);
        if (! $bank) {
            return [];
        }

        return $bank->assignedUsers
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'department' => $u->department,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $userIds
     * @return list<array{id:int,name:string,email:?string,department:?string}>
     */
    public function syncAssignedUsers(int $bankId, array $userIds): array
    {
        $bank = Bank::query()->findOrFail($bankId);

        $validIds = User::query()
            ->whereIn('id', $userIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($bank, $validIds): void {
            $bank->assignedUsers()->sync($validIds);
        });

        return $this->assignedUsersForBank($bankId);
    }
}
