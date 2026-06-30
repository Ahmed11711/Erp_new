<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_name',
        'method',
        'route',
        'path',
        'module',
        'action',
        'subject_id',
        'status',
        'ip',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    /** حقول مشتقّة تُرجَع مع كل سجل للواجهة (نوع العنصر القابل للفتح + وصف مقروء) */
    protected $appends = ['entity_type', 'description'];

    /** أول جزء في المسار يدل على عملية مرتبطة بكيان معيّن (مسارات الأفعال المستقلة) */
    private const FIRST_SEGMENT_ENTITY = [
        'confirm' => 'orders',
    ];

    /** كلمات الكيانات في المسار التي يوجد لها شاشة تعديل في الواجهة */
    private const ENTITY_NOUNS = [
        'orders' => 'orders',
        'order' => 'orders',
        'expenses' => 'expenses',
        'expense' => 'expenses',
        'purchases' => 'purchases',
        'purchase' => 'purchases',
        'suppliers' => 'suppliers',
        'supplier' => 'suppliers',
        'categories' => 'categories',
        'category' => 'categories',
        'recipes' => 'recipe',
        'recipe' => 'recipe',
        'processing' => 'processing',
        'shippingcompany' => 'shippingcompany',
        'leads' => 'leads',
    ];

    /** مفاتيح meta شائعة تُترجم لوصف مقروء يتبع الحركة */
    private const META_HIGHLIGHT_LABELS = [
        'name' => 'الاسم',
        'title' => 'العنوان',
        'client_name' => 'العميل',
        'customer_name' => 'العميل',
        'amount' => 'المبلغ',
        'total' => 'الإجمالي',
        'price' => 'السعر',
        'quantity' => 'الكمية',
        'qty' => 'الكمية',
        'status' => 'الحالة',
        'order_status' => 'حالة الطلب',
        'phone' => 'الهاتف',
        'note' => 'ملاحظة',
        'notes' => 'ملاحظة',
        'reason' => 'السبب',
        'date' => 'التاريخ',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    private function pathSegments(): array
    {
        $segments = array_values(array_filter(explode('/', (string) $this->path), fn ($s) => $s !== ''));
        if ($segments && in_array(strtolower($segments[0]), ['api', 'v1', 'v2'], true)) {
            array_shift($segments);
        }

        return $segments;
    }

    /** نوع الكيان القابل للفتح (orders, expenses, ...) أو null لو الحركة لا ترتبط بشاشة */
    public function getEntityTypeAttribute(): ?string
    {
        $segments = $this->pathSegments();
        if (! $segments) {
            return null;
        }

        $first = strtolower($segments[0]);
        if (isset(self::FIRST_SEGMENT_ENTITY[$first])) {
            return self::FIRST_SEGMENT_ENTITY[$first];
        }

        foreach ($segments as $segment) {
            $key = strtolower($segment);
            if (isset(self::ENTITY_NOUNS[$key])) {
                return self::ENTITY_NOUNS[$key];
            }
        }

        return null;
    }

    /** وصف عربي صريح للحركة التي تمت */
    public function getDescriptionAttribute(): string
    {
        $headline = trim((string) $this->action);
        if ($headline === '') {
            $headline = trim((string) $this->module);
        }

        if ($this->subject_id) {
            $headline .= ' (رقم ' . $this->subject_id . ')';
        }

        $highlights = $this->metaHighlights();

        return $highlights !== '' ? trim($headline . ' — ' . $highlights) : $headline;
    }

    private function metaHighlights(): string
    {
        $meta = $this->meta;
        if (! is_array($meta)) {
            return '';
        }

        $out = [];
        foreach (self::META_HIGHLIGHT_LABELS as $key => $label) {
            if (! array_key_exists($key, $meta)) {
                continue;
            }
            $value = $meta[$key];
            if (! is_scalar($value)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            if (mb_strlen($value) > 60) {
                $value = mb_substr($value, 0, 60) . '…';
            }
            $out[] = $label . ': ' . $value;
            if (count($out) >= 4) {
                break;
            }
        }

        return implode(' · ', $out);
    }
}
