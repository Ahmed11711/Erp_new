<?php

namespace App\Services\Items;

use App\Models\Item;

class ItemCodeService
{
    public function ensureCode(Item $item): void
    {
        if ($item->item_code !== null && $item->item_code !== '') {
            return;
        }

        $item->item_code = $this->generateUniqueCode();
        $item->save();
    }

    public function generateUniqueCode(): string
    {
        $prefix = (string) config('items_import.item_code_prefix', 'ITM-');

        for ($i = 0; $i < 50; $i++) {
            $suffix = strtoupper(bin2hex(random_bytes(4)));
            $code = $prefix.$suffix;
            if (! Item::where('item_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not generate a unique item_code.');
    }

    public function assertCodeAvailable(?string $code, ?int $exceptItemId = null): void
    {
        if ($code === null || $code === '') {
            return;
        }

        $q = Item::query()->where('item_code', $code);
        if ($exceptItemId !== null) {
            $q->where('id', '!=', $exceptItemId);
        }
        if ($q->exists()) {
            throw new \InvalidArgumentException('Duplicate item_code: '.$code);
        }
    }

    /**
     * كود يعكس نفس المنتج مع رقم إصدار جديد (مثال: ITM-XXX-R2) لتمييز النسخة بعد تعديل الوصفة.
     */
    public function suggestSuccessorCode(Item $old, int $nextRevision): string
    {
        $base = $old->item_code;
        if ($base === null || $base === '') {
            $base = 'ID'.$old->id;
        }
        $suffix = '-R'.$nextRevision;
        $maxLen = 64;
        $suffixLen = mb_strlen($suffix);
        $trimBase = mb_substr($base, 0, max(1, $maxLen - $suffixLen));
        $candidate = $trimBase.$suffix;
        if (! Item::where('item_code', $candidate)->exists()) {
            return $candidate;
        }

        return $this->generateUniqueCode();
    }
}
