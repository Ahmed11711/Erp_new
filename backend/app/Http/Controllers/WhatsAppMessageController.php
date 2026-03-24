<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Services\MetaWhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WhatsAppMessageController extends Controller
{
    protected $whatsappService;

    public function __construct()
    {
        // Initialize with user's assigned WhatsApp numbers
        $this->whatsappService = null; // Will be initialized per request
    }

    /**
     * Initialize WhatsApp service with specific phone number
     */
    private function initializeWhatsAppService(?string $phoneNumberId = null)
    {
        if (!$phoneNumberId) {
            // Get user's available WhatsApp numbers
            $userId = auth()->id();
            $availableNumbers = \App\Services\MetaWhatsAppService::getUserAvailablePhoneNumbers($userId);
            
            if (empty($availableNumbers)) {
                return null;
            }
            
            // Use first available number
            $phoneNumberId = $availableNumbers[0]['id'];
        }
        
        $this->whatsappService = new \App\Services\MetaWhatsAppService($phoneNumberId);
        return $this->whatsappService;
    }

    /**
     * Get user's available WhatsApp numbers
     */
    public function getUserPhoneNumbers()
    {
        try {
            $userId = auth()->id();
            $availableNumbers = \App\Services\MetaWhatsAppService::getUserAvailablePhoneNumbers($userId);
            
            return response()->json([
                'success' => true,
                'data' => $availableNumbers,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch user WhatsApp numbers',
            ], 500);
        }
    }

    /**
     * Send a WhatsApp message to a customer
     */
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_phone' => 'required|string',
            'message' => 'required|string',
            'order_id' => 'nullable|exists:orders,id',
            'phone_number_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $phone = $request->customer_phone;
            $messageContent = $request->message;
            $orderId = $request->order_id;
            $phoneNumberId = $request->phone_number_id;

            // Initialize WhatsApp service with selected or default phone number
            $whatsappService = $this->initializeWhatsAppService($phoneNumberId);
            if (!$whatsappService) {
                return response()->json([
                    'success' => false,
                    'error' => 'No WhatsApp number available for this user',
                ], 422);
            }

            // Format phone number (ensure it starts with country code)
            if (!str_starts_with($phone, '+')) {
                // Assume Egypt country code (+20) if not provided
                if (str_starts_with($phone, '0')) {
                    $phone = '+20' . substr($phone, 1);
                } else {
                    $phone = '+20' . $phone;
                }
            }

            // Find or create customer
            $customer = Customer::firstOrCreate(
                ['phone' => $phone],
                [
                    'name' => 'Customer ' . substr($phone, -4),
                    'assigned_agent_id' => auth()->id(),
                ]
            );

            // Send message via Meta WhatsApp
            $result = $whatsappService->sendMessage($phone, $messageContent);

            if ($result['success']) {
                // Store message in database
                $message = Message::create([
                    'customer_id' => $customer->id,
                    'sender_id' => auth()->id(),
                    'receiver_id' => null, // Customer receives it
                    'content' => $messageContent,
                    'direction' => 'outbound',
                    'status' => 'sent',
                    'twilio_message_sid' => $result['message_sid'] ?? null,
                ]);

                Log::info('WhatsApp message sent successfully', [
                    'customer_id' => $customer->id,
                    'message_id' => $message->id,
                    'phone' => $phone,
                    'phone_number_id' => $phoneNumberId,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Message sent successfully',
                    'data' => $message->load('customer', 'sender'),
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to send message',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while sending the message',
            ], 500);
        }
    }

    /**
     * Send message using a template
     */
    public function sendTemplateMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_phone' => 'required|string',
            'template_id' => 'required|exists:message_templates,id',
            'order_id' => 'nullable|exists:orders,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $phone = $request->customer_phone;
            $templateId = $request->template_id;
            $orderId = $request->order_id;

            // Format phone number (Egypt +20)
            if (!str_starts_with($phone, '+')) {
                if (str_starts_with($phone, '0')) {
                    $phone = '+20' . substr($phone, 1);
                } else {
                    $phone = '+20' . $phone;
                }
            }

            // Get template
            $template = MessageTemplate::findOrFail($templateId);
            if (!$template->is_active) {
                return response()->json(['error' => 'Template is not active'], 422);
            }

            // Replace placeholders if order_id is provided
            $messageContent = $template->content;
            if ($orderId) {
                $order = Order::find($orderId);
                if ($order) {
                    $messageContent = str_replace('{order_id}', $order->id, $messageContent);
                    $messageContent = str_replace('{customer_name}', $order->customer_name, $messageContent);
                    $messageContent = str_replace('{order_status}', $order->order_status, $messageContent);
                    $messageContent = str_replace('{net_total}', $order->net_total, $messageContent);
                }
            }

            // Find or create customer
            $customer = Customer::firstOrCreate(
                ['phone' => $phone],
                [
                    'name' => 'Customer ' . substr($phone, -4),
                    'assigned_agent_id' => auth()->id(),
                ]
            );

            // Send message
            $result = $this->whatsappService->sendMessage($phone, $messageContent);

            if ($result['success']) {
                // Store message
                $message = Message::create([
                    'customer_id' => $customer->id,
                    'sender_id' => auth()->id(),
                    'receiver_id' => null,
                    'content' => $messageContent,
                    'direction' => 'outbound',
                    'status' => 'sent',
                    'twilio_message_sid' => $result['message_sid'] ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Template message sent successfully',
                    'data' => $message->load('customer', 'sender'),
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to send message',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error sending template message', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while sending the message',
            ], 500);
        }
    }

    /**
     * Get chat messages for a customer
     */
    public function getChatMessages($customerId)
    {
        try {
            $customer = Customer::findOrFail($customerId);
            
            $messages = Message::where('customer_id', $customerId)
                ->with(['sender', 'receiver'])
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'customer' => $customer,
                'messages' => $messages,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch messages',
            ], 500);
        }
    }

    /**
     * Get all customers with their latest message
     */
    public function getCustomers(Request $request)
    {
        try {
            $customers = Customer::with(['assignedAgent', 'messages' => function ($query) {
                $query->latest()->limit(1);
            }])
            ->withCount('messages')
            ->orderBy('updated_at', 'desc')
            ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $customers,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch customers',
            ], 500);
        }
    }

    /**
     * Get message templates
     */
    public function getTemplates()
    {
        try {
            $templates = MessageTemplate::where('is_active', true)
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $templates,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch templates',
            ], 500);
        }
    }

    /**
     * Create a new template
     */
    public function createTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:message_templates,name',
            'content' => 'required|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $template = MessageTemplate::create([
                'name' => $request->name,
                'content' => $request->content,
                'description' => $request->description,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => $template,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create template',
            ], 500);
        }
    }

    /**
     * Send message to customer from order (replaces email notification)
     */
    public function sendMessageFromOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $order = Order::findOrFail($request->order_id);
            $phone = $order->customer_phone_1;
            $messageContent = $request->message;

            if (!$phone) {
                return response()->json([
                    'success' => false,
                    'error' => 'Customer phone number not found',
                ], 422);
            }

            // Format phone number
            if (!str_starts_with($phone, '+')) {
                if (str_starts_with($phone, '0')) {
                    $phone = '+20' . substr($phone, 1);
                } else {
                    $phone = '+20' . $phone;
                }
            }

            $whatsappService = $this->initializeWhatsAppService();
            if (!$whatsappService) {
                return response()->json([
                    'success' => false,
                    'error' => 'No WhatsApp number available for this user',
                ], 422);
            }

            // Find or create customer
            $customer = Customer::firstOrCreate(
                ['phone' => $phone],
                [
                    'name' => $order->customer_name,
                    'assigned_agent_id' => auth()->id(),
                ]
            );

            // Send message
            $result = $whatsappService->sendMessage($phone, $messageContent);

            if ($result['success']) {
                // Store message
                $message = Message::create([
                    'customer_id' => $customer->id,
                    'sender_id' => auth()->id(),
                    'receiver_id' => null,
                    'content' => $messageContent,
                    'direction' => 'outbound',
                    'status' => 'sent',
                    'twilio_message_sid' => $result['message_sid'] ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Message sent successfully',
                    'data' => $message->load('customer', 'sender'),
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to send message',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error sending message from order', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while sending the message',
            ], 500);
        }
    }

    /**
     * Get list of Meta WhatsApp templates (from config) for use when sending from order
     */
    public function getMetaTemplatesList(Request $request)
    {
        try {
            $templates = config('whatsapp_meta_templates.templates', []);

            // Fallback when config is empty (e.g. config cache or file not deployed)
            if (empty($templates)) {
                $templates = [
                    ['name' => 'order_update', 'language' => 'ar', 'body_params' => ['اسم العميل', 'رقم الطلب', 'حالة الطلب'], 'body_param_keys' => ['customer_name', 'id', 'order_status'], 'phone_number_id' => null],
                    ['name' => 'order_confirmation', 'language' => 'en', 'body_params' => ['اسم العميل', 'رقم الطلب', 'المبلغ الإجمالي'], 'body_param_keys' => ['customer_name', 'id', 'net_total'], 'phone_number_id' => null],
                    ['name' => 'order_confirmation_flow', 'language' => 'ar', 'body_params' => ['اسم العميل', 'رقم الطلب'], 'body_param_keys' => ['customer_name', 'id'], 'phone_number_id' => null],
                    ['name' => 'hello_world', 'language' => 'ar', 'body_params' => [], 'body_param_keys' => [], 'phone_number_id' => null],
                ];
                Log::warning('Meta templates loaded from fallback - config may be empty. Run: php artisan config:clear && php artisan config:cache');
            }

            // Filter by phone_number_id if provided (show only templates for this number)
            $phoneNumberId = $request->query('phone_number_id');
            if ($phoneNumberId) {
                $templates = collect($templates)->filter(function ($t) use ($phoneNumberId) {
                    $tPhone = $t['phone_number_id'] ?? null;
                    return $tPhone === null || $tPhone === $phoneNumberId;
                })->values()->all();
            }

            return response()->json([
                'success' => true,
                'data' => $templates,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching Meta templates list', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch templates',
            ], 500);
        }
    }

    /**
     * Send Meta WhatsApp template message from order (for 24h session / first contact)
     */
    public function sendMetaTemplateFromOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'template_name' => 'required|string',
            'language_code' => 'nullable|string|max:10',
            'body_parameters' => 'nullable|array',
            'body_parameters.*' => 'string',
            'phone_number_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $order = Order::findOrFail($request->order_id);
            $phone = $order->customer_phone_1;
            if (!$phone) {
                return response()->json([
                    'success' => false,
                    'error' => 'Customer phone number not found',
                ], 422);
            }

            if (!str_starts_with($phone, '+')) {
                $phone = str_starts_with($phone, '0') ? '+20' . substr($phone, 1) : '+20' . $phone;
            }

            $phoneNumberId = $request->phone_number_id;
            $whatsappService = $this->initializeWhatsAppService($phoneNumberId);
            if (!$whatsappService) {
                return response()->json([
                    'success' => false,
                    'error' => 'No WhatsApp number available for this user',
                ], 422);
            }

            $templateName = $request->template_name;
            $languageCode = $request->language_code ?? 'ar';
            $bodyParams = $request->body_parameters;

            $templatesConfig = config('whatsapp_meta_templates.templates', []);
            $templateConfig = collect($templatesConfig)->firstWhere('name', $templateName);
            if (!$templateConfig) {
                return response()->json([
                    'success' => false,
                    'error' => 'Template not found or not configured. Add it in config/whatsapp_meta_templates.php',
                ], 422);
            }

            if ($bodyParams === null || $bodyParams === []) {
                $keys = $templateConfig['body_param_keys'] ?? [];
                $bodyParams = [];
                foreach ($keys as $key) {
                    $val = (string) ($order->{$key} ?? '');
                    // Meta rejects empty parameters - use placeholder to avoid "Parameter name is missing or empty"
                    $bodyParams[] = trim($val) === '' ? '-' : $val;
                }
            }

            $components = [];
            if (!empty($bodyParams)) {
                $parameters = [];
                foreach ($bodyParams as $val) {
                    $text = trim((string) $val) === '' ? '-' : (string) $val;
                    $parameters[] = ['type' => 'text', 'text' => $text];
                }
                $components[] = ['type' => 'body', 'parameters' => $parameters];
            }

            $result = $whatsappService->sendTemplateMessage(
                $phone,
                $templateName,
                $languageCode,
                $components
            );

            if ($result['success']) {
                $customer = Customer::firstOrCreate(
                    ['phone' => $phone],
                    ['name' => $order->customer_name, 'assigned_agent_id' => auth()->id()]
                );
                // Build readable message for chat display
                $templateLabels = [
                    'order_confirmation' => 'تأكيد الطلب',
                    'order_confirmation_flow' => 'فلو تأكيد الطلب',
                    // 'order_update' => 'تحديث الطلب',
                    // 'hello_world' => 'رسالة ترحيب',
                    'review_request' => 'طلب تقييم',
                ];
                $label = $templateLabels[$templateName] ?? $templateName;
                $messageContent = "📋 قالب: {$label} - الطلب #{$order->id} - {$order->customer_name} - {$order->net_total} ج.م";
                Message::create([
                    'customer_id' => $customer->id,
                    'order_id' => $order->id,
                    'sender_id' => auth()->id(),
                    'receiver_id' => null,
                    'content' => $messageContent,
                    'direction' => 'outbound',
                    'status' => 'sent',
                    'twilio_message_sid' => $result['message_sid'] ?? null,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Template sent successfully',
                ], 200);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to send template',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error sending Meta template from order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'An error occurred while sending the template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available WhatsApp numbers for assignment
     */
    public function getAvailablePhoneNumbers()
    {
        try {
            $phoneNumbers = \App\Services\MetaWhatsAppService::getAvailablePhoneNumbers();
            
            return response()->json([
                'success' => true,
                'data' => $phoneNumbers,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch available phone numbers',
            ], 500);
        }
    }

    /**
     * Get all WhatsApp assignments
     */
    public function getAllAssignments()
    {
        try {
            $assignments = \App\Services\MetaWhatsAppService::getAllAssignments();
            
            return response()->json([
                'success' => true,
                'data' => $assignments,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch assignments',
            ], 500);
        }
    }

    /**
     * Assign multiple users to WhatsApp number
     */
    public function assignUsersToPhoneNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number_id' => 'required|string',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            Log::warning('WhatsApp assignment validation failed', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all()
            ]);
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $phoneNumberId = $request->phone_number_id;
            $userIds = $request->user_ids;

            // Verify the phone number is available (allow unconfigured for testing)
            $availableNumbers = \App\Services\MetaWhatsAppService::getAvailablePhoneNumbers();
            $isValidNumber = collect($availableNumbers)->pluck('id')->contains($phoneNumberId);
            
            if (!$isValidNumber) {
                Log::warning('Invalid WhatsApp phone number ID', [
                    'phone_number_id' => $phoneNumberId,
                    'available_numbers' => array_column($availableNumbers, 'id')
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid WhatsApp phone number ID',
                ], 422);
            }

            // Check if phone number is configured (optional warning)
            $phoneNumber = collect($availableNumbers)->firstWhere('id', $phoneNumberId);
            if (!$phoneNumber['is_configured']) {
                Log::info('Assigning users to unconfigured WhatsApp phone number', [
                    'phone_number_id' => $phoneNumberId,
                    'phone_number_name' => $phoneNumber['name']
                ]);
            }

            // Verify users exist
            $validUsers = \App\Models\User::whereIn('id', $userIds)->pluck('id')->toArray();
            if (count($validUsers) !== count($userIds)) {
                $invalidUserIds = array_diff($userIds, $validUsers);
                Log::warning('Some users are invalid', [
                    'invalid_user_ids' => $invalidUserIds,
                    'requested_user_ids' => $userIds
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Some users are invalid or do not exist: ' . implode(', ', $invalidUserIds),
                ], 422);
            }

            $result = \App\Models\WhatsAppAssignment::assignUsersToPhoneNumber($phoneNumberId, $validUsers);

            Log::info('Users assigned to WhatsApp number successfully', [
                'phone_number_id' => $phoneNumberId,
                'user_ids' => $validUsers,
                'result' => $result
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Users assigned to WhatsApp number successfully',
                'data' => [
                    'phone_number_id' => $phoneNumberId,
                    'assigned_users_count' => count($validUsers)
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error assigning users to WhatsApp number', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while assigning users to WhatsApp number: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove user assignment from WhatsApp number
     */
    public function removeUserAssignment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number_id' => 'required|string',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $phoneNumberId = $request->phone_number_id;
            $userId = $request->user_id;

            \App\Models\WhatsAppAssignment::where('phone_number_id', $phoneNumberId)
                ->where('user_id', $userId)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'User assignment removed successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error removing user assignment', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while removing user assignment',
            ], 500);
        }
    }
}
