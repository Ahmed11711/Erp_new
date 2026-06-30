<?php

namespace App\Services\Orders;

use App\Models\User;
use App\Services\Rbac\PermissionResolutionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class OrderStatusVisibilityService
{
    public function __construct(
        protected PermissionResolutionService $permissions,
    ) {}

    /**
     * @return list<string>
     */
    public function allStatusLabels(): array
    {
        $labels = [];
        foreach ($this->statusDefinitions() as $def) {
            foreach ($def['labels'] as $label) {
                $labels[] = $label;
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function filterOptionsFor(User $user): array
    {
        $visibleKeys = $this->visibleStatusKeys($user);
        $options = [];

        foreach ($this->statusDefinitions() as $key => $def) {
            if (! isset($visibleKeys[$key])) {
                continue;
            }
            $options[] = [
                'value' => $def['filter_value'],
                'label' => $def['filter_label'],
            ];
        }

        return $options;
    }

    public function canViewStatus(User $user, string $orderStatus): bool
    {
        if ($this->bypassesStatusFilter($user)) {
            return true;
        }

        $normalized = trim($orderStatus);

        return in_array($normalized, $this->visibleStatusLabels($user), true);
    }

    /**
     * @return list<string>
     */
    public function visibleStatusLabels(User $user): array
    {
        if ($this->bypassesStatusFilter($user)) {
            return $this->allStatusLabels();
        }

        $labels = [];
        foreach ($this->visibleStatusKeys($user) as $key => $_) {
            foreach ($this->statusDefinitions()[$key]['labels'] as $label) {
                $labels[] = $label;
            }
        }

        return array_values(array_unique($labels));
    }

    public function applySearchScope(Builder $query, User $user): void
    {
        if ($this->bypassesStatusFilter($user)) {
            return;
        }

        $labels = $this->visibleStatusLabels($user);
        if ($labels === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        // إخفاء الطلب بالكامل إذا لم تكن حالته ضمن الصلاحيات — بدون استثناءات.
        $query->whereIn('order_status', $labels);
    }

    /**
     * مَخرج طوارئ فقط: المستخدمون في rbac.super_admin_emails يتجاوزون الفلترة.
     * دور Super Admin لا يتجاوز — بل يعتمد على صلاحياته المزروعة (كل الحالات).
     */
    public function bypassesStatusFilter(User $user): bool
    {
        $emails = array_map(
            static fn ($email) => strtolower(trim((string) $email)),
            config('rbac.super_admin_emails', [])
        );

        if ($emails === []) {
            return false;
        }

        return in_array(strtolower(trim((string) $user->email)), $emails, true);
    }

    /**
     * الحالات المرئية = الحالات التي يملك المستخدم صلاحية عرضها فقط.
     *
     * @return array<string, true>
     */
    protected function visibleStatusKeys(User $user): array
    {
        $visible = [];

        foreach ($this->statusDefinitions() as $key => $def) {
            if ($this->permissions->hasPermission($user, $def['permission'])) {
                $visible[$key] = true;
            }
        }

        return $visible;
    }

    /**
     * @return array<string, array{permission: string, name: string, labels: list<string>, filter_value: string, filter_label: string}>
     */
    protected function statusDefinitions(): array
    {
        return config('order_status_visibility.statuses', []);
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    public function permissionDefinitions(): array
    {
        $rows = [];
        foreach ($this->statusDefinitions() as $def) {
            $rows[] = [
                'slug' => Str::lower($def['permission']),
                'name' => $def['name'],
            ];
        }

        return $rows;
    }
}
