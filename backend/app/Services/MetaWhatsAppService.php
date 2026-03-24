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
            $configuredNumbers = $this->getConfiguredPhoneNumbers();
            $matched = $configuredNumbers[$userPhoneNumberId] ?? null;
            if ($matched) {
                return $matched;
            }
        }

        return config('services.meta_whatsapp.phone_number_id');
    }

    /**
     * Get access token based on phone number ID
     */
    private function getAccessToken(?string $userPhoneNumberId): ?string
    {
        $phone2 = config('services.meta_whatsapp.phone_number_id_2');
        if ($userPhoneNumberId && $phone2 && $userPhoneNumberId === $phone2) {
            return config('services.meta_whatsapp.access_token_2');
        }

        return config('services.meta_whatsapp.access_token');
    }

    /**
     * Get all configured phone numbers
     */
    private function getConfiguredPhoneNumbers(): array
    {
        $phone1 = config('services.meta_whatsapp.phone_number_id');
        $phone2 = config('services.meta_whatsapp.phone_number_id_2');

        return array_filter([
            $phone1 => $phone1,
            $phone2 => $phone2,
        ]);
    }

    /**
     * Get available phone numbers for assignment
     */
    public static function getAvailablePhoneNumbers(): array
    {
        $phone1Id = config('services.meta_whatsapp.phone_number_id');
        $phone2Id = config('services.meta_whatsapp.phone_number_id_2');
        $token1 = config('services.meta_whatsapp.access_token');
        $token2 = config('services.meta_whatsapp.access_token_2');

        // If no phone numbers are configured, provide mock numbers for testing
        if (empty($phone1Id) && empty($phone2Id)) {
            return [
                [
                    'id' => 'mock_phone_1',
                    'name' => 'WhatsApp Number 1 (Test)',
                    'is_configured' => false
                ],
                [
                    'id' => 'mock_phone_2',
                    'name' => 'WhatsApp Number 2 (Test)',
                    'is_configured' => false
                ],
            ];
        }

        return [
            [
                'id' => $phone1Id ?: 'mock_phone_1',
                'name' => 'WhatsApp Number 1',
                'is_configured' => ! empty($phone1Id) && ! empty($token1)
            ],
            [
                'id' => $phone2Id ?: 'mock_phone_2',
                'name' => 'WhatsApp Number 2',
                'is_configured' => ! empty($phone2Id) && ! empty($token2)
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

        if (empty($assignedPhoneNumbers)) {
            return [];
        }

        $availableIds = array_map('strval', array_column($availableNumbers, 'id'));
        $unmatchedAssignedIds = array_values(array_filter($assignedPhoneNumbers, fn ($id) => ! in_array((string) $id, $availableIds)));
        sort($unmatchedAssignedIds);
        $unmatchedIndex = 0;

        $result = [];
        foreach ($availableNumbers as $number) {
            $numId = (string) ($number['id'] ?? '');
            $isAssigned = in_array($numId, $assignedPhoneNumbers);

            if (! $isAssigned && isset($unmatchedAssignedIds[$unmatchedIndex])) {
                $isAssigned = true;
                // Use real DB id for MetaWhatsAppService (sending) when mapping mock to real
                $number = array_merge($number, ['id' => $unmatchedAssignedIds[$unmatchedIndex]]);
                $unmatchedIndex++;
            }

            if ($isAssigned) {
                $result[] = $number;
            }
        }

        return $result;
    }

    /**
     * Get all assignments with user details (one entry per available phone number)
     */
    public static function getAllAssignments(): array
    {
        $assignmentsByPhone = WhatsAppAssignment::getAllWithPhoneNumberDetails();
        $availableNumbers = self::getAvailablePhoneNumbers();
        
        // DB phone_number_ids not in available numbers (e.g. real IDs when UI shows mock IDs)
        $availableIds = collect($availableNumbers)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $unmatchedDbIds = $assignmentsByPhone->keys()
            ->filter(fn ($k) => ! in_array((string) $k, $availableIds))
            ->sort()
            ->values()
            ->all();
        $unmatchedIndex = 0;
        
        $result = [];
        foreach ($availableNumbers as $phoneNumber) {
            $phoneNumberId = (string) ($phoneNumber['id'] ?? '');
            
            // Find matching assignment group: direct match or by position (when config uses mock IDs)
            $assignmentGroup = $assignmentsByPhone->get($phoneNumberId);
            if (! $assignmentGroup && isset($unmatchedDbIds[$unmatchedIndex])) {
                $assignmentGroup = $assignmentsByPhone->get($unmatchedDbIds[$unmatchedIndex]);
                $unmatchedIndex++;
            }
            
            $assignedUsers = [];
            if ($assignmentGroup) {
                foreach ($assignmentGroup as $assignment) {
                    if ($assignment->user) {
                        $assignedUsers[] = [
                            'id' => $assignment->user->id,
                            'name' => $assignment->user->name,
                            'email' => $assignment->user->email,
                            'is_active' => $assignment->is_active,
                        ];
                    }
                }
            }
            
            $result[] = [
                'phone_number_id' => $phoneNumberId,
                'phone_number_name' => $phoneNumber['name'] ?? 'Unknown',
                'is_configured' => $phoneNumber['is_configured'] ?? false,
                'assigned_users' => $assignedUsers,
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

    /**
     * Send interactive quick reply buttons (within 24h session).
     *
     * @param  string  $to  Phone number (with or without +)
     * @param  string  $bodyText  Message body text
     * @param  array<int, array{id: string, title: string}>  $buttons  Up to 3 buttons
     */
    public function sendInteractiveButtons(string $to, string $bodyText, array $buttons): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Meta WhatsApp not configured'];
        }

        $to = preg_replace('/\D/', '', $to);
        if (strlen($to) === 10 && str_starts_with($to, '0')) {
            $to = '20' . substr($to, 1);
        } elseif (strlen($to) === 9 && str_starts_with($to, '1')) {
            $to = '20' . $to;
        }
        $to = ltrim($to, '+');

        $actionButtons = [];
        foreach (array_slice($buttons, 0, 3) as $btn) {
            $actionButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $btn['id'],
                    'title' => $btn['title'],
                ],
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $bodyText],
                'action' => ['buttons' => $actionButtons],
            ],
        ];

        try {
            $response = Http::withToken($this->accessToken)
                ->post("https://graph.facebook.com/{$this->metaVersion}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_sid' => $data['messages'][0]['id'] ?? null,
                    'status' => 'sent',
                ];
            }

            Log::error('Meta WhatsApp interactive send failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['success' => false, 'error' => $response->body()];
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
