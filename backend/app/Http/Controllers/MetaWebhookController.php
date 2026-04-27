<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Services\OrderConfirmationFlowService;
use App\Services\MetaWhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class MetaWebhookController extends Controller
{
    /**
     * Handle Webhook Verification (GET)
     */
    // public function verify(Request $request)
    // {
    //     Log::info('Meta Webhook VERIFY data', [
    //         'query' => $request->query(),
    //         'ip' => $request->ip(),
    //         'headers' => $request->headers->all(),
    //     ]);

    //     return response('success', 200);
    // }
    
    public function verify(Request $request)
{
    $verifyToken = 'K9xT2pLm8QwZ4rNs7VbY1cHd6EfG3uJk';

    if (
        $request->has('hub_mode') &&
        $request->has('hub_verify_token') &&
        $request->has('hub_challenge')
    ) {
        if ($request->hub_verify_token === $verifyToken) {
            return response($request->hub_challenge, 200);
        }
    }

    return response('Verification failed', 403);
}


    public function handle(Request $request)
    {
        $data = $request->all();
        Log::debug('Meta Webhook payload', ['json' => json_encode($data, JSON_UNESCAPED_UNICODE)]);

        if (isset($data['entry'])) {
            foreach ($data['entry'] as $entry) {
                foreach ($entry['changes'] as $change) {
                    $value = $change['value'];

                    if (isset($value['messages'])) {
                        $contacts = $value['contacts'] ?? [];
                        $metadata = $value['metadata'] ?? [];
                        $phoneNumberId = $metadata['phone_number_id'] ?? null;

                        foreach ($value['messages'] as $msg) {
                            $from = $msg['from'];
                            $metaMessageId = $msg['id']; // preserve — never overwrite
                            $msgType = $msg['type'] ?? 'unknown';
                            Log::info("Incoming WhatsApp message from {$from}", [
                                'meta_id' => $metaMessageId,
                                'type' => $msgType,
                                'has_context' => isset($msg['context']),
                                'keys' => array_keys($msg),
                            ]);

                            $buttonId = null;
                            $buttonTitle = null;
                            $contextId = $msg['context']['id'] ?? null;

                            $msgTypeLower = strtolower((string) $msgType);

                            if ($msgTypeLower === 'interactive') {
                                $interactive = $msg['interactive'] ?? [];
                                if (isset($interactive['button_reply']) && is_array($interactive['button_reply'])) {
                                    $br = $interactive['button_reply'];
                                    $buttonId = $br['id'] ?? null;
                                    $buttonTitle = $br['title'] ?? null;
                                } elseif (isset($interactive['list_reply']) && is_array($interactive['list_reply'])) {
                                    $lr = $interactive['list_reply'];
                                    $buttonId = $lr['id'] ?? null;
                                    $buttonTitle = $lr['title'] ?? null;
                                }
                                if ($buttonId === null && $buttonTitle === null) {
                                    Log::warning('Meta Webhook: interactive message without button_reply/list_reply', [
                                        'interactive_keys' => array_keys($interactive),
                                        'interactive_type' => $interactive['type'] ?? null,
                                    ]);
                                }
                            } elseif ($msgTypeLower === 'button') {
                                $button = $msg['button'] ?? [];
                                $buttonId = $button['payload'] ?? $button['id'] ?? null;
                                $buttonTitle = $button['text'] ?? $button['title'] ?? null;
                            } elseif ($msgTypeLower === 'text') {
                                $body = trim($msg['text']['body'] ?? '');
                                $body = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $body);
                                try {
                                    $whatsappService = new MetaWhatsAppService($phoneNumberId);
                                    if ($whatsappService->isConfigured() && $body !== '') {
                                        $flowService = new OrderConfirmationFlowService($whatsappService);
                                        if (! $flowService->handleTextReply($from, $body, $contextId)) {
                                            $knownTitles = [
                                                'تأكيد الطلب', 'تأجيل الطلب', 'إلغاء الطلب', 'تعديل الطلب', 'إعادة الشحن',
                                                'تأكيد التجهيز', 'تأكيد الشحن', 'تاجيل الطلب',
                                                'نعم إلغاء الطلب', 'لا أريد تأكيد الطلب', 'بعد يومين', 'بعد أسبوع', 'تحديد موعد آخر',
                                                'أريد إعادة الشحن', 'أريد تعديل الطلب', 'أريد إلغاء الطلب', 'رفض الاستلام',
                                                'خلال 3 أيام', 'تحديد موعد',
                                                'Recharge', 'Edit Order', 'Cancel order', 'Cancel Order',
                                                'Confirm Preparation', 'Confirm Shipping', 'Delay Shipping',
                                                'Yes, cancel order', 'No, keep order',
                                                'In two days', 'In one week', 'Another date',
                                                'Within 3 days', 'Schedule a date',
                                                'Write review', 'Leave a quick review', 'Leave a review', 'Quick review',
                                                'اكتب تقييمك', 'اكتب رأيك', 'تقييم سريع',
                                            ];
                                            if (in_array($body, $knownTitles, true)) {
                                                $buttonTitle = $body;
                                            }
                                        }
                                    }
                                } catch (\Throwable $e) {
                                    Log::error('OrderConfirmationFlow: Text reply exception', [
                                        'message' => $e->getMessage(),
                                    ]);
                                }
                            }

                            if ($buttonId || $buttonTitle) {
                                Log::info('OrderConfirmationFlow: Button reply received', [
                                    'from' => $from,
                                    'button_id' => $buttonId,
                                    'button_title' => $buttonTitle,
                                    'context_id' => $contextId,
                                ]);
                                try {
                                    $whatsappService = new MetaWhatsAppService($phoneNumberId);
                                    if (! $whatsappService->isConfigured()) {
                                        Log::warning('OrderConfirmationFlow: Meta WhatsApp not configured — no auto reply', [
                                            'phone_number_id' => $phoneNumberId,
                                        ]);
                                    } else {
                                        $flowService = new OrderConfirmationFlowService($whatsappService);
                                        $handled = $flowService->handleButtonReply($from, $buttonId, $contextId, $phoneNumberId, $buttonTitle);
                                        if (! $handled) {
                                            Log::warning('OrderConfirmationFlow: Button not handled', [
                                                'button_id' => $buttonId,
                                                'button_title' => $buttonTitle,
                                            ]);
                                        }
                                    }
                                } catch (\Throwable $e) {
                                    Log::error('OrderConfirmationFlow: Exception', [
                                        'message' => $e->getMessage(),
                                        'trace' => $e->getTraceAsString(),
                                    ]);
                                }
                            }

                            $this->processMessage($msg, $contacts, $phoneNumberId);
                        }
                    }

                    if (isset($value['statuses'])) {
                        foreach ($value['statuses'] as $status) {
                            $this->processStatus($status);
                        }
                    }
                }
            }
        }

        return response('success', 200);
    }
    


private function sendStaticReply($to)
{
    $phone_number_id = '992330837294579'; // رقم البزنس الخاص بيك
    $access_token = 'EAANBfjf5ke8BQj6wDWDwZCXyTCRJuZA2osiOWXm6z7tX1J96Jrc1yVZCxZBJLVlZB8E7EFOqZCcsGQz0ckGGnPHwPQECog1KCgCMwwNyDZAKVrAgXJW7ly8vWDnMWGPrkMOTpZCLomok08VCB7mFbwTmdWPCPlWVgToATbiZBMm1ZB5CZA7vOWzMtcpGQDl9QfL';

    $url = "https://graph.facebook.com/v17.0/{$phone_number_id}/messages";

    $response = Http::withToken($access_token)
        ->post($url, [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "text",
            "text" => [
                "body" => "Hello! This is a test message ✅"
            ]
        ]);

    Log::info('Reply sent: ' . $response->body());
}



    private function processMessage($messageData, $contacts, ?string $phoneNumberId = null)
    {
        $from = $messageData['from'];
        $metaId = $messageData['id'];   // ← never overwrite this
        $type = $messageData['type'];
        $timestamp = $messageData['timestamp'];

        $mediaTypes = ['image', 'video', 'audio', 'document', 'sticker'];
        $mediaId = null;
        $mediaMime = null;
        $mediaFilename = null;
        $mediaCaption = null;
        $storedType = $type;

        $body = '';
        if ($type === 'text') {
            $body = $messageData['text']['body'] ?? '';
        } elseif (in_array($type, $mediaTypes, true) && isset($messageData[$type]) && is_array($messageData[$type])) {
            $payload = $messageData[$type];
            $mediaId = $payload['id'] ?? null;
            $mediaMime = $payload['mime_type'] ?? null;
            $mediaFilename = $payload['filename'] ?? null;
            $mediaCaption = $payload['caption'] ?? null;
            $body = ($mediaCaption !== null && $mediaCaption !== '')
                ? $mediaCaption
                : '[' . ucfirst($type) . ']';
            Log::info('Webhook media extracted', [
                'meta_id' => $metaId,
                'type' => $type,
                'media_id' => $mediaId,
                'mime' => $mediaMime,
            ]);
        } elseif ($type === 'interactive') {
            $interactive = $messageData['interactive'] ?? [];
            $buttonReply = $interactive['button_reply'] ?? null;
            $listReply = $interactive['list_reply'] ?? null;
            if ($buttonReply) {
                $brTitle = $buttonReply['title'] ?? null;
                $brId = $buttonReply['id'] ?? null;
                $body = $brTitle ? '🔘 ' . $brTitle : ($brId ? '🔘 ' . $brId : '[رسالة تفاعلية]');
            } elseif ($listReply && isset($listReply['title'])) {
                $body = '📋 ' . $listReply['title'];
            } else {
                $body = '[رسالة تفاعلية]';
            }
        } elseif ($type === 'button') {
            $button = $messageData['button'] ?? [];
            $text = $button['text'] ?? $button['title'] ?? null;
            $payload = $button['payload'] ?? $button['id'] ?? null;
            $body = $text ? '🔘 ' . $text : ($payload ? '🔘 ' . $payload : '[زر تفاعلي]');
        } elseif ($type === 'reaction') {
            $emoji = $messageData['reaction']['emoji'] ?? '';
            $body = $emoji !== '' ? $emoji : '[Reaction]';
            $storedType = 'text';
        } elseif ($type === 'contacts') {
            $body = '[Contact Card]';
            $storedType = 'text';
        } elseif ($type === 'location') {
            $lat = $messageData['location']['latitude'] ?? '';
            $lon = $messageData['location']['longitude'] ?? '';
            $body = "📍 Location: {$lat}, {$lon}";
            $storedType = 'text';
        } else {
            $body = '[' . ucfirst($type) . ' Message]';
        }

        // Ensure body is never empty (DB column is NOT NULL)
        if ($body === '' || $body === null) {
            $body = '[' . ucfirst($type) . ']';
        }

        $customerName = 'Customer ' . substr($from, -4);
        foreach ($contacts as $contact) {
            if (($contact['wa_id'] ?? '') === $from) {
                $customerName = $contact['profile']['name'] ?? $customerName;
                break;
            }
        }

        $phone = '+' . ltrim($from, '+');

        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $customerName,
                'assigned_agent_id' => $this->assignToAgent(),
            ]
        );

        $receiverId = $customer->assigned_agent_id ?? $this->assignToAgent();
        if (! $customer->assigned_agent_id && $receiverId) {
            $customer->assigned_agent_id = $receiverId;
            $customer->save();
        }

        if (Message::where('twilio_message_sid', $metaId)->exists()) {
            Log::debug('Webhook duplicate skipped', ['meta_id' => $metaId]);
            return;
        }

        $message = Message::create([
            'customer_id' => $customer->id,
            'sender_id' => null,
            'receiver_id' => $receiverId,
            'content' => $body,
            'type' => $storedType ?: 'text',
            'media_id' => $mediaId,
            'media_mime_type' => $mediaMime,
            'media_filename' => $mediaFilename,
            'media_caption' => $mediaCaption,
            'direction' => 'inbound',
            'status' => 'received',
            'twilio_message_sid' => $metaId,
            'phone_number_id' => $phoneNumberId,
            'created_at' => date('Y-m-d H:i:s', $timestamp),
        ]);

        Log::info('Meta Message Stored', [
            'db_id' => $message->id,
            'meta_id' => $metaId,
            'type' => $storedType,
            'media_id' => $mediaId,
            'customer_id' => $customer->id,
        ]);
    }

    private function processStatus($statusData)
    {
        $id = $statusData['id'] ?? null;
        $status = $statusData['status'] ?? '';
        $errors = $statusData['errors'] ?? null;
        $recipientId = $statusData['recipient_id'] ?? null;

        if (in_array($status, ['failed', 'undelivered'], true)) {
            Log::warning('Meta WhatsApp delivery not completed', [
                'id' => $id,
                'status' => $status,
                'recipient_id' => $recipientId,
                'errors' => $errors,
            ]);
        }

        $message = $id ? Message::where('twilio_message_sid', $id)->first() : null;

        if ($message && $message->status !== $status) {
            $message->status = $status;
            $message->save();
            Log::info('Meta Message Status Updated', ['id' => $id, 'status' => $status]);
        }
    }

    private function assignToAgent(): ?int
    {
        $agent = User::withCount('customers')
            ->orderBy('customers_count', 'asc')
            ->first();

        return $agent?->id;
    }
}
