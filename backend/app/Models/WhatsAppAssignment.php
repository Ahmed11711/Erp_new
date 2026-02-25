<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number_id',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the assignment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get users assigned to a specific WhatsApp number.
     */
    public static function getUsersByPhoneNumber(string $phoneNumberId)
    {
        return self::where('phone_number_id', $phoneNumberId)
            ->where('is_active', true)
            ->with('user')
            ->get();
    }

    /**
     * Get WhatsApp numbers assigned to a specific user.
     */
    public static function getPhoneNumbersByUserId(int $userId)
    {
        return self::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('phone_number_id')
            ->toArray();
    }

    /**
     * Assign multiple users to a WhatsApp number.
     */
    public static function assignUsersToPhoneNumber(string $phoneNumberId, array $userIds)
    {
        // Remove existing assignments for this phone number
        self::where('phone_number_id', $phoneNumberId)->delete();
        
        // Create new assignments
        $assignments = [];
        foreach ($userIds as $userId) {
            $assignments[] = [
                'phone_number_id' => $phoneNumberId,
                'user_id' => $userId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        return self::insert($assignments);
    }

    /**
     * Get all assignments with phone number details.
     */
    public static function getAllWithPhoneNumberDetails()
    {
        return self::with('user:id,name,email')
            ->get()
            ->groupBy('phone_number_id');
    }
}
