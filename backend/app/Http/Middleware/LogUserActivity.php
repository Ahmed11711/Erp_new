<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * يسجّل كل عمليات الكتابة (POST/PUT/PATCH/DELETE) التي ينفّذها المستخدمون
 * في جدول activity_logs ليكون سجلّاً عاماً لكل حركات المستخدمين في النظام.
 */
class LogUserActivity
{
    private const LOGGED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** مسارات يتم تجاهلها (مطابقة من البداية) لتقليل الضوضاء أو لحساسيتها */
    private const SKIP_PREFIXES = [
        'auth/login',
        'auth/logout',
        'auth/refresh',
        'auth/me',
        'login',
        'logout',
        'activity-logs',
        'sanctum',
    ];

    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'old_password', 'token', 'access_token', 'refresh_token', 'secret',
        'api_key', 'apikey', 'authorization', 'pin', 'otp',
    ];

    /** الحد الأقصى لطول النص المخزّن لكل قيمة داخل الـ meta */
    private const MAX_VALUE_LENGTH = 500;

    private const MODULE_LABELS = [
        'orders' => 'الطلبات',
        'order' => 'الطلبات',
        'purchases' => 'المشتريات',
        'purchase' => 'المشتريات',
        'expenses' => 'المصروفات',
        'expense' => 'المصروفات',
        'suppliers' => 'الموردين',
        'supplier' => 'الموردين',
        'categories' => 'الأصناف',
        'category' => 'الأصناف',
        'vouchers' => 'السندات',
        'voucher' => 'السندات',
        'banks' => 'الخزائن/البنوك',
        'bank' => 'الخزائن/البنوك',
        'customers' => 'العملاء',
        'customer' => 'العملاء',
        'shipping' => 'الشحن',
        'shippingcompany' => 'شركات الشحن',
        'collection' => 'التحصيل',
        'manufacture' => 'التصنيع',
        'manufacturing' => 'التصنيع',
        'recipe' => 'الوصفات',
        'recipes' => 'الوصفات',
        'processing' => 'التشغيل لدى الغير',
        'offers' => 'العروض',
        'notes' => 'الملاحظات',
        'note' => 'الملاحظات',
        'notification' => 'الإشعارات',
        'register' => 'المستخدمين',
        'users' => 'المستخدمين',
        'user' => 'المستخدمين',
        'rbac' => 'الصلاحيات',
        'approvals' => 'الموافقات',
        'tracking' => 'الطلبات',
    ];

    private const VERB_LABELS = [
        'POST' => 'إضافة',
        'PUT' => 'تعديل',
        'PATCH' => 'تعديل',
        'DELETE' => 'حذف',
    ];

    private const ACTION_SUFFIX_LABELS = [
        'approve' => 'اعتماد',
        'reject' => 'رفض',
        'confirm' => 'تأكيد',
        'cancel' => 'إلغاء',
        'undo' => 'تراجع',
        'post' => 'ترحيل',
        'pay' => 'سداد',
        'delete' => 'حذف',
        'update' => 'تعديل',
        'store' => 'إضافة',
        'export' => 'تصدير',
        'import' => 'استيراد',
        'merge' => 'دمج',
        'restore' => 'استرجاع',
        'voucher' => 'سند',
        'settle' => 'تسوية',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! in_array($request->method(), self::LOGGED_METHODS, true)) {
                return;
            }

            $path = trim($request->path(), '/');
            // إزالة بادئة api/ حتى يكون القسم والمسار المخزّن نظيفاً (orders بدلاً من api/orders)
            if (str_starts_with($path, 'api/')) {
                $path = substr($path, 4);
            } elseif ($path === 'api') {
                $path = '';
            }

            foreach (self::SKIP_PREFIXES as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                    return;
                }
            }

            $user = $request->user();
            if (! $user) {
                return;
            }

            ActivityLog::create([
                'user_id' => $user->id ?? null,
                'user_name' => $user->name ?? null,
                'method' => $request->method(),
                'route' => optional($request->route())->uri(),
                'path' => $path,
                'module' => $this->resolveModule($path),
                'action' => $this->resolveAction($request, $path),
                'subject_id' => $this->resolveSubjectId($request, $path),
                'status' => $response->getStatusCode(),
                'ip' => $request->ip(),
                'meta' => $this->sanitizePayload($request),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // التسجيل لا يجب أن يكسر الاستجابة أبداً
        }
    }

    private function segments(string $path): array
    {
        return array_values(array_filter(explode('/', $path), fn ($s) => $s !== ''));
    }

    private function resolveModule(string $path): string
    {
        $segments = $this->segments($path);
        $idx = 0;
        $key = strtolower($segments[0] ?? '');

        // تجاوز بادئات الإصدارات مثل v2
        if (in_array($key, ['v1', 'v2', 'api'], true) && isset($segments[1])) {
            $idx = 1;
            $key = strtolower($segments[1]);
        }

        return self::MODULE_LABELS[$key] ?? ($segments[$idx] ?? '—');
    }

    /** كلمات في المسار تدل على نوع العملية حتى لو اختلف نوع الطلب (مثل POST لـ bulk-delete) */
    private const ACTION_KEYWORD_LABELS = [
        'purge' => 'حذف نهائي',
        'delete' => 'حذف',
        'destroy' => 'حذف',
        'remove' => 'حذف',
        'cancel' => 'إلغاء',
        'reject' => 'رفض',
        'approve' => 'اعتماد',
        'confirm' => 'تأكيد',
        'restore' => 'استرجاع',
        'merge' => 'دمج',
        'import' => 'استيراد',
        'export' => 'تصدير',
        'undo' => 'تراجع',
    ];

    private function resolveAction(Request $request, string $path): string
    {
        $segments = $this->segments($path);
        $module = $this->resolveModule($path);

        $last = strtolower((string) end($segments));
        if ($last !== '' && ! ctype_digit($last)) {
            $normalized = str_replace(['-', '_'], ' ', $last);

            foreach (self::ACTION_KEYWORD_LABELS as $keyword => $label) {
                if (str_contains($normalized, $keyword)) {
                    return $label . ' — ' . $module;
                }
            }

            if (isset(self::ACTION_SUFFIX_LABELS[$last])) {
                return self::ACTION_SUFFIX_LABELS[$last] . ' — ' . $module;
            }
        }

        $verb = self::VERB_LABELS[$request->method()] ?? $request->method();

        return $verb . ' — ' . $module;
    }

    private function resolveSubjectId(Request $request, string $path): ?int
    {
        $route = $request->route();
        if ($route) {
            foreach ($route->parameters() as $value) {
                if (is_numeric($value)) {
                    return (int) $value;
                }
            }
        }

        foreach ($this->segments($path) as $segment) {
            if (ctype_digit($segment)) {
                return (int) $segment;
            }
        }

        return null;
    }

    private function sanitizePayload(Request $request): ?array
    {
        $input = $request->except(self::SENSITIVE_KEYS);

        $clean = $this->scrub($input);

        return empty($clean) ? null : $clean;
    }

    private function scrub($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (in_array(strtolower((string) $k), self::SENSITIVE_KEYS, true)) {
                    $out[$k] = '***';
                    continue;
                }
                $out[$k] = $this->scrub($v);
            }

            return $out;
        }

        if (is_object($value)) {
            return '[object]';
        }

        if (is_string($value) && strlen($value) > self::MAX_VALUE_LENGTH) {
            return substr($value, 0, self::MAX_VALUE_LENGTH) . '…';
        }

        return $value;
    }
}
