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
        // Log::info('Meta Webhook HANDLE data: ' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

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
                            $msgType = $msg['type'] ?? 'unknown';
                            Log::info("Incoming WhatsApp message from {$from}", [
                                'type' => $msgType,
                                'has_context' => isset($msg['context']),
                                'keys' => array_keys($msg),
                                'msg_sample' => $msgType === 'text' ? ($msg['text']['body'] ?? null) : ($msg[$msgType] ?? null),
                            ]);

                            $buttonId = null;
                            $buttonTitle = null;
                            $contextId = $msg['context']['id'] ?? null;

                            $msgTypeLower = strtolower((string) $msgType);

                            // Handle button / list reply — استخراج button_reply حتى لو تغيّر ترتيب الحقول أو type
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
                                // جلسة فلو (1/2/3 أو نسخ العبارة): يُعالَج قبل محاكاة أزرار القالب
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

                            $this->processMessage($msg, $contacts);
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



    private function processMessage($messageData, $contacts)
    {
        $from = $messageData['from']; // Phone number
        $id = $messageData['id']; // Message ID
        $type = $messageData['type'];
        $timestamp = $messageData['timestamp'];
        
        $body = '';
        if ($type === 'text') {
            $body = $messageData['text']['body'];
        } elseif ($type === 'interactive') {
            // Extract button reply or list reply for readable display
            $interactive = $messageData['interactive'] ?? [];
            $buttonReply = $interactive['button_reply'] ?? null;
            $listReply = $interactive['list_reply'] ?? null;
            if ($buttonReply) {
                $title = $buttonReply['title'] ?? null;
                $id = $buttonReply['id'] ?? null;
                if ($title) {
                    $body = '🔘 ' . $title;
                } elseif ($id) {
                    $body = '🔘 ' . $id;
                } else {
                    $body = '[رسالة تفاعلية]';
                }
            } elseif ($listReply && isset($listReply['title'])) {
                $body = '📋 ' . $listReply['title'];
            } else {
                $body = '[رسالة تفاعلية]';
            }
        } elseif ($type === 'button') {
            $button = $messageData['button'] ?? [];
            $text = $button['text'] ?? $button['title'] ?? null;
            $payload = $button['payload'] ?? $button['id'] ?? null;
            if ($text) {
                $body = '🔘 ' . $text;
            } elseif ($payload) {
                $body = '🔘 ' . $payload;
            } else {
                $body = '[زر تفاعلي]';
            }
        } else {
            $body = '[' . ucfirst($type) . ' Message]';
        }

        // Get customer name from contacts if available
        $customerName = 'Customer ' . substr($from, -4);
        foreach ($contacts as $contact) {
            if ($contact['wa_id'] === $from) {
                $customerName = $contact['profile']['name'] ?? $customerName;
                break;
            }
        }

        // Find or create customer
        // Ensure phone starts with +
        $phone = '+' . $from;
        
        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => $customerName,
                'assigned_agent_id' => $this->assignToAgent(),
            ]
        );

        $receiverId = $customer->assigned_agent_id ?? $this->assignToAgent();
         if (!$customer->assigned_agent_id && $receiverId) {
            $customer->assigned_agent_id = $receiverId;
            $customer->save();
        }

        // Check for duplicates
        if (Message::where('twilio_message_sid', $id)->exists()) {
            return;
        }

        // Create Message
        Message::create([
            'customer_id' => $customer->id,
            'sender_id' => null,
            'receiver_id' => $receiverId,
            'content' => $body,
            'direction' => 'inbound',
            'status' => 'received',
            'twilio_message_sid' => $id, // Storing Meta ID in existing column
            'created_at' => date('Y-m-d H:i:s', $timestamp),
        ]);

        Log::info('Meta Message Stored', ['id' => $id]);
    }

    private function processStatus($statusData)
    {
        $id = $statusData['id'];
        $status = $statusData['status'];
        // $recipient_id = $statusData['recipient_id'];

        $message = Message::where('twilio_message_sid', $id)->first();

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
