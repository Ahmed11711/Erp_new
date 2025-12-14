<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    public static function boot()
    {
        parent::boot();

        static::created(function($notification) {
            $notification->notification_number .= 'NF' . $notification->id;
            $notification->save();
        });
    }

    protected $fillable = [
        'send_from',
        'send_to',
        'type',
        'ref',
        'note',
        'order_id',
        'notification_number',
        'review_status',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'send_from');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'send_to');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
