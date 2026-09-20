<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'assigned_agent_id',
        'whatsapp_archived_at',
    ];

    protected $casts = [
        'whatsapp_archived_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function isWhatsappArchived(): bool
    {
        return $this->whatsapp_archived_at !== null;
    }

    public function archiveWhatsappConversation(): void
    {
        if ($this->whatsapp_archived_at !== null) {
            return;
        }

        $this->forceFill(['whatsapp_archived_at' => now()])->save();
    }

    public function restoreWhatsappConversationFromArchive(): void
    {
        if ($this->whatsapp_archived_at === null) {
            return;
        }

        $this->forceFill(['whatsapp_archived_at' => null])->save();
    }

    public static function normalizeWhatsappPhoneDigits(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?: '';
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '20'.substr($digits, 1);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            $digits = '20'.$digits;
        } elseif (str_starts_with($digits, '0020')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }

    public static function findByWhatsappPhone(string $phone): ?self
    {
        $digits = static::normalizeWhatsappPhoneDigits($phone);
        if ($digits === '') {
            return null;
        }

        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        return static::query()
            ->whereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') LIKE ?", ['%'.$last10])
            ->withCount('messages')
            ->orderByDesc('messages_count')
            ->orderBy('id')
            ->first();
    }

    /**
     * أي رسالة واردة بعد وقت الأرشفة تُعيد المحادثة للصندوق.
     * شبكة أمان إذا فوّت الويب هوك فك الأرشفة.
     */
    public static function restoreInboxFromNewerInboundReplies(): int
    {
        return (int) static::query()
            ->whereNotNull('whatsapp_archived_at')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('messages')
                    ->whereColumn('messages.customer_id', 'customers.id')
                    ->where('messages.direction', 'inbound')
                    ->where(function ($inner) {
                        $inner->whereColumn('messages.created_at', '>', 'customers.whatsapp_archived_at')
                            ->orWhereColumn('messages.updated_at', '>', 'customers.whatsapp_archived_at');
                    });
            })
            ->update(['whatsapp_archived_at' => null]);
    }

    public function restoreIfHasNewerInboundReply(): void
    {
        if ($this->whatsapp_archived_at === null) {
            return;
        }

        $hasNewerInbound = $this->messages()
            ->where('direction', 'inbound')
            ->where(function ($query) {
                $query->where('created_at', '>', $this->whatsapp_archived_at)
                    ->orWhere('updated_at', '>', $this->whatsapp_archived_at);
            })
            ->exists();

        if ($hasNewerInbound) {
            $this->restoreWhatsappConversationFromArchive();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function findOrCreateByWhatsappPhone(string $phone, array $attributes = []): self
    {
        $existing = static::findByWhatsappPhone($phone);
        if ($existing) {
            return $existing;
        }

        $digits = static::normalizeWhatsappPhoneDigits($phone);
        $normalized = $digits !== '' ? '+'.$digits : $phone;

        return static::create(array_merge($attributes, ['phone' => $normalized]));
    }

    public function isAwaitingWhatsappReply(): bool
    {
        if (array_key_exists('last_message_direction', $this->getAttributes())) {
            return $this->getAttribute('last_message_direction') === 'inbound';
        }

        $last = $this->messages()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('direction');

        return $last === 'inbound';
    }

    public function scopeWhereNotAwaitingWhatsappReply($query)
    {
        $latestDirection = '(SELECT m.direction FROM messages m WHERE m.customer_id = customers.id ORDER BY m.created_at DESC, m.id DESC LIMIT 1)';

        return $query->where(function ($inner) use ($latestDirection) {
            $inner->whereRaw($latestDirection.' IS NULL')
                ->orWhereRaw($latestDirection.' <> ?', ['inbound']);
        });
    }

    public function assignedAgent()
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }
}
