<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'edited_by_user_id',
        'note',
        'added_from',
        'is_problem',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function editedBy()
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
    }
}
