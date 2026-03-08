<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyEntry extends Model
{
    use HasFactory;

    /**
     * Generate the next unique entry number. Uses lockForUpdate() to prevent
     * duplicate entry numbers when multiple requests run concurrently.
     * Must be called within a DB transaction.
     */
    public static function getNextEntryNumber(): string
    {
        $maxNum = static::lockForUpdate()->max(DB::raw('CAST(entry_number AS UNSIGNED)'));
        $entryNumber = ($maxNum ?? 0) + 1;
        return str_pad((string) $entryNumber, 6, '0', STR_PAD_LEFT);
    }

    protected $fillable = [
        'date',
        'entry_number',
        'description',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(DailyEntryItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}