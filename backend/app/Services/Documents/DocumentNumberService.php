<?php

namespace App\Services\Documents;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

/**
 * Central sequential document numbering (ERP-safe locking).
 */
class DocumentNumberService
{
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

            $seq->last_number = ((int) $seq->last_number) + 1;
            $seq->save();

            return $seq->prefix.'-'.$seq->last_number;
        });
    }
}
