<?php

namespace App\Services\Rbac;

use App\Models\PermissionActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class PermissionAuditLogger
{
    public function log(string $action, ?User $actor, ?User $target = null, ?string $subjectType = null, ?int $subjectId = null, array $properties = [], ?Request $request = null): void
    {
        PermissionActivityLog::query()->create([
            'actor_id' => $actor?->id,
            'target_user_id' => $target?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
        ]);
    }
}
