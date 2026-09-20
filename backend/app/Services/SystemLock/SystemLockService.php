<?php

namespace App\Services\SystemLock;

use App\Models\Setting;
use App\Models\User;
use App\Services\Rbac\PermissionResolutionService;
use Illuminate\Support\Facades\Schema;

class SystemLockService
{
    public const SETTING_KEY = 'system_lock';

    public const DEFAULT_MESSAGE = "خدمة الانترنت لا تعمل بشكل كامل\nسيحدث خطاً في تحميل البيانات";

    public const CONTROL_PERMISSION = 'system.lock';

    public const UNLOCK_PERMISSION = 'system.unlock';

    public const BYPASS_PERMISSION = 'system.lock_bypass';

    public function __construct(
        protected PermissionResolutionService $resolver
    ) {}

    /**
     * @return array{
     *   locked: bool,
     *   message: string,
     *   show_message: bool,
     *   exempt_user_ids: list<int>,
     *   locked_by: int|null,
     *   locked_at: string|null,
     *   unlocked_by: int|null,
     *   unlocked_at: string|null
     * }
     */
    public function state(): array
    {
        $defaults = [
            'locked' => false,
            'message' => self::DEFAULT_MESSAGE,
            'show_message' => true,
            'exempt_user_ids' => [],
            'locked_by' => null,
            'locked_at' => null,
            'unlocked_by' => null,
            'unlocked_at' => null,
        ];

        if (! Schema::hasTable('settings')) {
            return $defaults;
        }

        $raw = Setting::query()->where('key', self::SETTING_KEY)->value('value');
        if (! is_string($raw) || trim($raw) === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $defaults;
        }

        $ids = [];
        foreach ($decoded['exempt_user_ids'] ?? [] as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $ids[] = $intId;
            }
        }

        $message = trim((string) ($decoded['message'] ?? ''));

        return [
            'locked' => (bool) ($decoded['locked'] ?? false),
            'message' => $message !== '' ? $message : self::DEFAULT_MESSAGE,
            'show_message' => array_key_exists('show_message', $decoded)
                ? (bool) $decoded['show_message']
                : true,
            'exempt_user_ids' => array_values(array_unique($ids)),
            'locked_by' => isset($decoded['locked_by']) ? (int) $decoded['locked_by'] : null,
            'locked_at' => isset($decoded['locked_at']) ? (string) $decoded['locked_at'] : null,
            'unlocked_by' => isset($decoded['unlocked_by']) ? (int) $decoded['unlocked_by'] : null,
            'unlocked_at' => isset($decoded['unlocked_at']) ? (string) $decoded['unlocked_at'] : null,
        ];
    }

    public function isLocked(): bool
    {
        return $this->state()['locked'] === true;
    }

    public function canLock(User $user): bool
    {
        if ($this->resolver->isSuperAdmin($user)) {
            return true;
        }

        return $this->resolver->hasPermission($user, self::CONTROL_PERMISSION);
    }

    public function canUnlock(User $user): bool
    {
        if ($this->resolver->isSuperAdmin($user)) {
            return true;
        }

        return $this->resolver->hasPermission($user, self::UNLOCK_PERMISSION);
    }

    public function canControl(User $user): bool
    {
        return $this->canLock($user) || $this->canUnlock($user);
    }

    public function isExempt(User $user): bool
    {
        if ($this->canUnlock($user) || $this->canLock($user)) {
            return true;
        }

        if ($this->resolver->hasPermission($user, self::BYPASS_PERMISSION)) {
            return true;
        }

        return in_array((int) $user->id, $this->state()['exempt_user_ids'], true);
    }

    public function isRestricted(User $user): bool
    {
        return $this->isLocked() && ! $this->isExempt($user);
    }

    /**
     * @return array{
     *   locked: bool,
     *   restricted: bool,
     *   can_lock: bool,
     *   can_unlock: bool,
     *   can_control: bool,
     *   message: string,
     *   show_message: bool,
     *   exempt_user_ids: list<int>,
     *   locked_by: array{id:int,name:string}|null,
     *   locked_at: string|null
     * }
     */
    public function statusPayload(User $user): array
    {
        $state = $this->state();
        $lockedBy = null;
        if (! empty($state['locked_by'])) {
            $actor = User::query()->find($state['locked_by'], ['id', 'name']);
            if ($actor) {
                $lockedBy = [
                    'id' => (int) $actor->id,
                    'name' => (string) $actor->name,
                ];
            }
        }

        return [
            'locked' => $state['locked'],
            'restricted' => $this->isRestricted($user),
            'can_lock' => $this->canLock($user),
            'can_unlock' => $this->canUnlock($user),
            'can_control' => $this->canControl($user),
            'message' => $state['message'],
            'show_message' => $state['show_message'],
            'exempt_user_ids' => $state['exempt_user_ids'],
            'locked_by' => $lockedBy,
            'locked_at' => $state['locked_at'],
        ];
    }

    /**
     * @param  list<int>  $exemptUserIds
     * @return array<string, mixed>
     */
    public function update(User $actor, bool $locked, ?string $message, array $exemptUserIds, ?bool $showMessage = null): array
    {
        $state = $this->state();
        $ids = [];
        foreach ($exemptUserIds as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $ids[] = $intId;
            }
        }
        $ids[] = (int) $actor->id;
        $ids = array_values(array_unique($ids));
        $existing = User::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $trimmedMessage = is_string($message) ? trim($message) : '';
        if ($trimmedMessage === '') {
            $trimmedMessage = $state['message'] !== '' ? $state['message'] : self::DEFAULT_MESSAGE;
        }

        $payload = [
            'locked' => $locked,
            'message' => $trimmedMessage,
            'show_message' => $showMessage === null ? (bool) $state['show_message'] : $showMessage,
            'exempt_user_ids' => $existing,
            'locked_by' => $locked ? (int) $actor->id : $state['locked_by'],
            'locked_at' => $locked ? now()->toIso8601String() : $state['locked_at'],
            'unlocked_by' => $locked ? null : (int) $actor->id,
            'unlocked_at' => $locked ? null : now()->toIso8601String(),
        ];

        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($payload, JSON_UNESCAPED_UNICODE)]
        );

        return $this->statusPayload($actor);
    }
}
