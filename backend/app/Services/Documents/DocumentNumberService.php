<?php

namespace App\Services\Documents;

use App\Models\DocumentSequence;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Central sequential document numbering (ERP-safe locking).
 */
class DocumentNumberService
{
    private const MAX_COLLISION_RETRIES = 500;

    /**
     * @throws \Throwable
     */
    public function generate(int $transactionTypeId): string
    {
        return DB::transaction(function () use ($transactionTypeId) {
            /** @var DocumentSequence $seq */
            $seq = DocumentSequence::query()
                ->where('transaction_type_id', $transactionTypeId)
                ->lockForUpdate()
                ->firstOrFail();

            $prefix = (string) $seq->prefix;
            $maxExisting = $this->resolveMaxExistingNumber($prefix);
            if ($maxExisting > (int) $seq->last_number) {
                $seq->last_number = $maxExisting;
            }

            $attempts = 0;
            do {
                $seq->last_number = ((int) $seq->last_number) + 1;
                $candidate = $prefix.'-'.$seq->last_number;
                $attempts++;
            } while ($this->documentNumberExists($candidate) && $attempts < self::MAX_COLLISION_RETRIES);

            if ($this->documentNumberExists($candidate)) {
                throw new \RuntimeException('تعذر توليد رقم مستند فريد للبادئة '.$prefix);
            }

            $seq->save();

            return $candidate;
        });
    }

    /**
     * Returns the next document number without consuming the sequence.
     */
    public function peek(int $transactionTypeId): string
    {
        $seq = DocumentSequence::query()
            ->where('transaction_type_id', $transactionTypeId)
            ->firstOrFail();

        $prefix = (string) $seq->prefix;
        $cursor = max((int) $seq->last_number, $this->resolveMaxExistingNumber($prefix));

        $attempts = 0;
        do {
            $cursor++;
            $candidate = $prefix.'-'.$cursor;
            $attempts++;
        } while ($this->documentNumberExists($candidate) && $attempts < self::MAX_COLLISION_RETRIES);

        if ($this->documentNumberExists($candidate)) {
            throw new \RuntimeException('تعذر معاينة رقم مستند فريد للبادئة '.$prefix);
        }

        return $candidate;
    }

    private function documentNumberExists(string $documentNo): bool
    {
        if (Purchase::query()->where('invoice_no', $documentNo)->exists()) {
            return true;
        }

        if (Purchase::query()->where('invoice_number', $documentNo)->exists()) {
            return true;
        }

        if (Schema::hasTable('stock_transactions')) {
            return DB::table('stock_transactions')
                ->where('transaction_no', $documentNo)
                ->exists();
        }

        return false;
    }

    private function resolveMaxExistingNumber(string $prefix): int
    {
        $max = 0;
        $needle = $prefix.'-';

        foreach (Purchase::query()->whereNotNull('invoice_no')->pluck('invoice_no') as $no) {
            $max = max($max, $this->extractNumericSuffix($needle, (string) $no));
        }

        foreach (Purchase::query()->whereNotNull('invoice_number')->pluck('invoice_number') as $no) {
            $max = max($max, $this->extractNumericSuffix($needle, (string) $no));
        }

        if (Schema::hasTable('stock_transactions')) {
            $rows = DB::table('stock_transactions')
                ->whereNotNull('transaction_no')
                ->where('transaction_no', 'like', $prefix.'-%')
                ->pluck('transaction_no');

            foreach ($rows as $no) {
                $max = max($max, $this->extractNumericSuffix($needle, (string) $no));
            }
        }

        return $max;
    }

    private function extractNumericSuffix(string $needle, string $documentNo): int
    {
        if (! str_starts_with($documentNo, $needle)) {
            return 0;
        }

        $suffix = substr($documentNo, strlen($needle));

        return ctype_digit($suffix) ? (int) $suffix : 0;
    }
}
