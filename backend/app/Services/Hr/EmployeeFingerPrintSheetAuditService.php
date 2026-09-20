<?php

namespace App\Services\Hr;

use App\Models\EmployeeFingerPrintSheetLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EmployeeFingerPrintSheetAuditService
{
    public const FIELD_LABELS = [
        'check_in' => 'الحضور',
        'check_out' => 'الانصراف',
        'hours' => 'ساعات الحضور',
        'hours_permission' => 'إذن',
        'absence_deduction' => 'خصم الغياب',
        'is_overTime_removed' => 'إزالة الإضافي',
        'vacation' => 'إجازة',
        'vacation_reason' => 'سبب الإجازة',
    ];

    public function log(int $sheetId, string $action, array $changes = [], ?string $note = null): void
    {
        if ($changes === [] && $note === null) {
            return;
        }

        $user = Auth::user();

        EmployeeFingerPrintSheetLog::create([
            'finger_print_sheet_id' => $sheetId,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'النظام',
            'action' => $action,
            'changes' => $changes === [] ? null : $changes,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $newValues */
    public function diffModel(Model $model, array $newValues, array $fields): array
    {
        $changes = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $newValues)) {
                continue;
            }

            $old = $model->{$field};
            $new = $newValues[$field];

            if ($this->normalizeValue($old) === $this->normalizeValue($new)) {
                continue;
            }

            $changes[$field] = [
                'label' => self::FIELD_LABELS[$field] ?? $field,
                'old' => $this->formatValue($old),
                'new' => $this->formatValue($new),
            ];
        }

        return $changes;
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'نعم' : 'لا';
        }

        return (string) $value;
    }
}
