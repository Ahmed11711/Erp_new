<?php

namespace App\Services;

use App\Models\WhatsAppAssignment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppService
{
    private ?string $phoneNumberId = null;
    private ?string $accessToken = null;
    private string $metaVersion = 'v21.0';

    public function __construct(?string $userPhoneNumberId = null)
    {
        $this->phoneNumberId = $this->getPhoneNumberId($userPhoneNumberId);
        $this->accessToken = $this->getAccessToken($userPhoneNumberId);
        
        if (empty($this->phoneNumberId) || empty($this->accessToken)) {
            Log::warning('Meta WhatsApp credentials are missing.', [
                'phone_number_id' => $userPhoneNumberId
            ]);
        }
    }

    /**
     * Get phone number ID based on user assignment
     */
    private function getPhoneNumberId(?string $userPhoneNumberId): ?string
    {
        if ($userPhoneNumberId) {
            // Check if it matches any configured phone number
            $configuredNumbers = $this->getConfiguredPhoneNumbers();
            return $configuredNumbers[$userPhoneNumberId] ?? null;
        }
        
        // Default to first phone number
        return env('META_PHONE_NUMBER_ID');
    }

    /**
     * Get access token based on phone number ID
     */
    private function getAccessToken(?string $userPhoneNumberId): ?string
    {
        if ($userPhoneNumberId) {
            // Return corresponding token for the phone number
            if ($userPhoneNumberId === env('META_PHONE_NUMBER_ID_2')) {
                return env('META_ACCESS_TOKEN_2');
            }
        }
        
        // Default to first token
        return env('META_ACCESS_TOKEN');
    }

    /**
     * Get all configured phone numbers
     */
    private function getConfiguredPhoneNumbers(): array
    {
        return [
            env('META_PHONE_NUMBER_ID') => env('META_PHONE_NUMBER_ID'),
            env('META_PHONE_NUMBER_ID_2') => env('META_PHONE_NUMBER_ID_2'),
        ];
    }

    /**
     * Get available phone numbers for assignment
     */
    public static function getAvailablePhoneNumbers(): array
    {
        return [
            [
                'id' => env('META_PHONE_NUMBER_ID'),
                'name' => 'WhatsApp Number 1',
                'is_configured' => !empty(env('META_PHONE_NUMBER_ID')) && !empty(env('META_ACCESS_TOKEN'))
            ],
            [
                'id' => env('META_PHONE_NUMBER_ID_2'),
                'name' => 'WhatsApp Number 2',
                'is_configured' => !empty(env('META_PHONE_NUMBER_ID_2')) && !empty(env('META_ACCESS_TOKEN_2'))
            ],
        ];
    }

    /**
     * Get WhatsApp numbers available for current user
     */
    public static function getUserAvailablePhoneNumbers(int $userId): array
    {
        $assignedPhoneNumbers = WhatsAppAssignment::getPhoneNumbersByUserId($userId);
        $availableNumbers = self::getAvailablePhoneNumbers();
        
        return array_filter($availableNumbers, function($number) use ($assignedPhoneNumbers) {
            return in_array($number['id'], $assignedPhoneNumbers);
        });
    }

    /**
     * Get all assignments with user details
     */
    public static function getAllAssignments(): array
    {
        $assignments = WhatsAppAssignment::getAllWithPhoneNumberDetails();
        $availableNumbers = self::getAvailablePhoneNumbers();
        
        $result = [];
        foreach ($assignments as $phoneNumberId => $assignmentGroup) {
            $phoneNumber = collect($availableNumbers)->firstWhere('id', $phoneNumberId);
            
            $result[] = [
                'phone_number_id' => $phoneNumberId,
                'phone_number_name' => $phoneNumber['name'] ?? 'Unknown',
                'is_configured' => $phoneNumber['is_configured'] ?? false,
                'assigned_users' => $assignmentGroup->map(function($assignment) {
                    return [
                        'id' => $assignment->user->id,
                        'name' => $assignment->user->name,
                        'email' => $assignment->user->email,
                        'is_active' => $assignment->is_active,
                    ];
                })->toArray()
            ];
        }
        
        return $result;
    }

    /**
     * Send a text message via Meta WhatsApp Cloud API
     */
    public function sendMessage(string $to, string $message): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Meta WhatsApp not configured'];
        }

        try {
            // Format phone number (remove + if present, ensure code)
            $to = ltrim($to);

            $response = Http::withToken($this->accessToken)
                ->post("https://graph.facebook.com/{$this->metaVersion}/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $message
                    ]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Meta WhatsApp message sent', [
                    'to' => $to,
                    'message_id' => $data['messages'][0]['id'] ?? null
                ]);

                return [
                    'success' => true,
                    'message_sid' => $data['messages'][0]['id'] ?? null, // Meta uses 'id' not 'sid'
                    'status' => 'sent' // Meta doesn't return status in send response immediately
                ];
            } else {
                Log::error('Meta WhatsApp send failed', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false, 
                    'error' => $response->body()
                ];
            }
        } catch (\Exception $e) {
            Log::error('Meta WhatsApp exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a template message
     */
    public function sendTemplateMessage(string $to, string $templateName, string $languageCode = 'en_US', array $components = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Meta WhatsApp not configured'];
        }

        try {
            $to = ltrim($to, '+');

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => $languageCode
                    ]
                ]
            ];

            if (!empty($components)) {
                $payload['template']['components'] = $components;
            }

            $response = Http::withToken($this->accessToken)
                ->post("https://graph.facebook.com/{$this->metaVersion}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Meta WhatsApp template sent', [
                    'to' => $to,
                    'template' => $templateName,
                    'message_id' => $data['messages'][0]['id'] ?? null
                ]);

                return [
                    'success' => true,
                    'message_sid' => $data['messages'][0]['id'] ?? null,
                    'status' => 'sent'
                ];
            } else {
                Log::error('Meta WhatsApp template send failed', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return ['success' => false, 'error' => $response->body()];
            }

        } catch (\Exception $e) {
            Log::error('Meta WhatsApp exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->phoneNumberId) && !empty($this->accessToken);
    }
}
