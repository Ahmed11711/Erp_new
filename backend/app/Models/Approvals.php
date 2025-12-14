<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Approvals extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'table_name',
        'column_values',
        'details',
        'status',
        'user_id'
    ];

    protected $casts = [
        'column_values' => 'array',
        'details' => 'array',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
