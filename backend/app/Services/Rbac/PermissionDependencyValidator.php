<?php

namespace App\Services\Rbac;

use App\Models\PermissionDependency;
use Illuminate\Support\Collection;

class PermissionDependencyValidator
{
    /**
     * @param  iterable<int>  $permissionIds
     */
    public function missingRequirements(iterable $permissionIds): Collection
    {
        $ids = collect($permissionIds)->unique()->filter()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $deps = PermissionDependency::query()
            ->whereIn('permission_id', $ids)
            ->get(['permission_id', 'requires_permission_id']);

        $missing = collect();
        foreach ($deps as $dep) {
            if (! $ids->contains($dep->requires_permission_id)) {
                $missing->push([
                    'permission_id' => $dep->permission_id,
                    'requires_permission_id' => $dep->requires_permission_id,
                ]);
            }
        }

        return $missing;
    }
}
