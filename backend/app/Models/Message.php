<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_STICKER = 'sticker';

    protected $fillable = [
        'customer_id',
        'order_id',
        'sender_id',
        'receiver_id',
        'content',
        'type',
        'media_id',
        'media_mime_type',
        'media_filename',
        'media_caption',
        'direction',
        'status',
        'twilio_message_sid',
        'phone_number_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** Types that carry a downloadable media asset on the Meta side. */
    public static function mediaTypes(): array
    {
        return [
            self::TYPE_IMAGE,
            self::TYPE_VIDEO,
            self::TYPE_AUDIO,
            self::TYPE_DOCUMENT,
            self::TYPE_STICKER,
        ];
    }

    public function isMedia(): bool
    {
        return in_array($this->type, self::mediaTypes(), true)
            && ! empty($this->media_id);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    protected static function booted(): void
    {
        static::created(function (self $message): void {
            if ($message->customer_id) {
                Customer::whereKey($message->customer_id)->update(['updated_at' => now()]);
            }
        });
    }
}
