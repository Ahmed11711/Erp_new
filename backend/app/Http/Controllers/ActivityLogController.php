<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $itemsPerPage = (int) ($request->input('itemsPerPage') ?: 25);
        $itemsPerPage = max(1, min($itemsPerPage, 200));

        $query = ActivityLog::query()->with('user:id,name');

        $createdAt = trim((string) $request->input('created_at', ''));
        if ($createdAt !== '' && $createdAt !== '0') {
            $query->whereDate('created_at', $createdAt);
        }

        $userId = (int) $request->input('user_id', 0);
        if ($userId > 0) {
            $query->where('user_id', $userId);
        }

        $module = trim((string) $request->input('module', ''));
        if ($module !== '' && $module !== '0') {
            $query->where('module', $module);
        }

        $method = strtoupper(trim((string) $request->input('method', '')));
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $query->where('method', $method);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderByDesc('id')->paginate($itemsPerPage);

        return response()->json($logs, 200);
    }

    public function modules()
    {
        $modules = ActivityLog::query()
            ->whereNotNull('module')
            ->where('module', '!=', '')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        return response()->json($modules, 200);
    }
}
