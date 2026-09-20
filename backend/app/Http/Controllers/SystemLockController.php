<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SystemLock\SystemLockOrdersPreviewService;
use App\Services\SystemLock\SystemLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemLockController extends Controller
{
    public function __construct(
        protected SystemLockService $lock,
        protected SystemLockOrdersPreviewService $ordersPreviewService,
    ) {}

    public function status(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json($this->lock->statusPayload($user));
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $data = $request->validate([
            'locked' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:500'],
            'show_message' => ['nullable', 'boolean'],
            'exempt_user_ids' => ['nullable', 'array'],
            'exempt_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);

        $locked = (bool) $data['locked'];
        if ($locked && ! $this->lock->canLock($user)) {
            return response()->json(['message' => 'غير مسموح بقفل النظام.'], 403);
        }
        if (! $locked && ! $this->lock->canUnlock($user)) {
            return response()->json(['message' => 'غير مسموح بإعادة تشغيل النظام.'], 403);
        }

        $payload = $this->lock->update(
            $user,
            $locked,
            $data['message'] ?? null,
            $data['exempt_user_ids'] ?? [],
            array_key_exists('show_message', $data) ? (bool) $data['show_message'] : null
        );

        return response()->json($payload);
    }

    public function users(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if (! $this->lock->canControl($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rows = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department']);

        return response()->json(['data' => $rows]);
    }

    public function ordersPreview(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json($this->ordersPreviewService->payload($user));
    }
}
