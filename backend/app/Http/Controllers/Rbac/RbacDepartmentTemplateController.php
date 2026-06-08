<?php

namespace App\Http\Controllers\Rbac;

use App\Http\Controllers\Controller;
use App\Models\DepartmentRoleTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\Rbac\PermissionAuditLogger;
use App\Services\Rbac\RbacStampService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RbacDepartmentTemplateController extends Controller
{
    public function __construct(
        protected PermissionAuditLogger $audit,
        protected RbacStampService $stamp,
    ) {
    }

    public function show(Request $request)
    {
        $department = trim((string) $request->query('department', ''));
        if ($department === '') {
            return response()->json(['message' => 'department required'], 422);
        }

        $tpl = DepartmentRoleTemplate::query()
            ->where('department', $department)
            ->with(['role' => fn ($q) => $q->withCount('permissions')])
            ->first();

        return response()->json([
            'department' => $department,
            'template' => $tpl,
        ]);
    }

    /**
     * Popular role for department + saved template (smart preset).
     */
    public function suggest(Request $request)
    {
        $department = trim((string) $request->query('department', ''));
        if ($department === '') {
            return response()->json(['message' => 'department required'], 422);
        }

        $tpl = DepartmentRoleTemplate::query()
            ->where('department', $department)
            ->with('role')
            ->first();

        $popular = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('users.department', $department)
            ->select('model_has_roles.role_id', DB::raw('COUNT(*) as users_count'))
            ->groupBy('model_has_roles.role_id')
            ->orderByDesc('users_count')
            ->first();

        $recommendedId = $tpl?->role_id ?? ($popular->role_id ?? null);

        return response()->json([
            'department' => $department,
            'recommended_role_id' => $recommendedId ? (int) $recommendedId : null,
            'saved_template_role_id' => $tpl?->role_id,
            'popular_role_id' => isset($popular->role_id) ? (int) $popular->role_id : null,
            'popular_users_count' => isset($popular->users_count) ? (int) $popular->users_count : 0,
            'sample_users_count' => $tpl?->sample_users_count ?? 0,
            'role' => $recommendedId ? Role::query()->withCount('permissions')->find($recommendedId) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'department' => ['required', 'string', 'max:191'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $tpl = DepartmentRoleTemplate::query()->updateOrCreate(
            ['department' => trim($data['department'])],
            ['role_id' => $data['role_id']]
        );

        $this->stamp->bump();
        $this->audit->log('department.template_saved', auth()->user(), null, DepartmentRoleTemplate::class, $tpl->id, $data, $request);

        return response()->json($tpl->load(['role']));
    }

    public function touchSample(Request $request)
    {
        $data = $request->validate([
            'department' => ['required', 'string', 'max:191'],
            'delta' => ['nullable', 'integer'],
        ]);

        $tpl = DepartmentRoleTemplate::query()->where('department', trim($data['department']))->first();
        if ($tpl) {
            $tpl->sample_users_count = max(0, $tpl->sample_users_count + ($data['delta'] ?? 1));
            $tpl->save();
        }

        return response()->json(['ok' => true, 'template' => $tpl]);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'department' => ['required', 'string', 'max:191'],
        ]);

        DepartmentRoleTemplate::query()->where('department', trim($data['department']))->delete();

        $this->stamp->bump();

        return response()->json(['ok' => true]);
    }
}
