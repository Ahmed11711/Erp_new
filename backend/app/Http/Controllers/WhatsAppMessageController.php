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
                    ['name' => 'order_confirmation', 'language' => 'en_US', 'body_params' => ['اسم العميل', 'رقم الطلب', 'المبلغ الإجمالي'], 'body_param_keys' => ['customer_name', 'id', 'net_total'], 'phone_number_id' => null],
                    ['name' => 'order_confirmation_flow', 'language' => 'ar', 'ui_label' => 'تجهيز الطلب بالعربية', 'body_params' => ['اسم العميل', 'رقم الطلب'], 'body_param_keys' => ['customer_name', 'id'], 'phone_number_id' => null, 'button_ids' => ['confirm_order', 'postpone_order', 'cancel_order']],
                    ['name' => 'order_flow', 'language' => 'en_US', 'ui_label' => 'تجهيز الطلب بالإنجليزية', 'body_params' => ['اسم العميل', 'رقم الطلب'], 'body_param_keys' => ['customer_name', 'id'], 'phone_number_id' => null],
                    ['name' => 'confirm_order', 'language' => 'ar', 'ui_label' => 'تأكيد الطلب بالعربية', 'body_params' => ['اسم العميل', 'رقم الطلب'], 'body_param_keys' => ['customer_name', 'id'], 'phone_number_id' => null, 'button_ids' => ['confirm_order', 'postpone_order', 'cancel_order']],
                    ['name' => 'confirm_order', 'language' => 'en_US', 'api_language_code' => 'en', 'ui_label' => 'تأكيد الطلب بالإنجليزية', 'body_params' => ['اسم العميل', 'رقم الطلب'], 'body_param_keys' => ['customer_name', 'id'], 'phone_number_id' => null],
                    ['name' => 'review_request', 'language' => 'ar', 'body_params' => ['اسم العميل'], 'body_param_keys' => ['customer_name'], 'phone_number_id' => null],
                    ['name' => 'client_review', 'language' => 'ar', 'ui_label' => 'تقييم العميل بالعربية', 'body_params' => ['اسم العميل'], 'body_param_keys' => ['customer_name'], 'phone_number_id' => null],
                    ['name' => 'client_review', 'language' => 'en_US', 'api_language_code' => 'en', 'ui_label' => 'تقييم العميل بالإنجليزية', 'body_params' => ['اسم العميل'], 'body_param_keys' => ['customer_name'], 'phone_number_id' => null],
                    ['name' => 'feedback', 'language' => 'ar', 'ui_label' => 'فيد باك بالعربية', 'body_params' => [], 'body_param_keys' => [], 'phone_number_id' => null],
                    ['name' => 'feedback', 'language' => 'en_US', 'api_language_code' => 'en', 'ui_label' => 'فيد باك بالانجليزية', 'body_params' => [], 'body_param_keys' => [], 'phone_number_id' => null],
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
            'header_parameters' => 'nullable|array',
            'header_parameters.*' => 'string',
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
            $templateConfig = $this->resolveMetaTemplateConfig($templatesConfig, $templateName, $languageCode);
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

            $headerParams = $request->header_parameters;
            if ($headerParams === null || $headerParams === []) {
                $hKeys = $templateConfig['header_param_keys'] ?? [];
                $headerParams = [];
                foreach ($hKeys as $key) {
                    $val = (string) ($order->{$key} ?? '');
                    $headerParams[] = trim($val) === '' ? '-' : $val;
                }
            }

            $headerFormat = $templateConfig['header_format'] ?? null;
            if ($headerFormat === null && ! empty($templateConfig['header_param_keys'] ?? [])) {
                $headerFormat = 'text';
            }

            $components = [];
            if (! in_array($headerFormat, ['omit', 'none'], true)) {
                if ($headerFormat === 'image') {
                    $imageUrls = [];
                    foreach ($headerParams as $val) {
                        $u = trim((string) $val);
                        if ($u === '' || $u === '-') {
                            $u = '';
                        }
                        $imageUrls[] = $u;
                    }
                    $hasAnyUrl = false;
                    foreach ($imageUrls as $u) {
                        if ($u !== '') {
                            $hasAnyUrl = true;
                            break;
                        }
                    }
                    if (! $hasAnyUrl) {
                        $fallback = trim((string) ($templateConfig['header_default_image_url'] ?? ''));
                        if ($fallback === '' && in_array($templateName, ['client_review', 'feedback'], true)) {
                            $fallback = trim((string) config('whatsapp_meta_templates.review_feedback_header_image_url', ''));
                        }
                        if ($fallback === '') {
                            $fallback = trim((string) config('whatsapp_meta_templates.default_header_image_url', ''));
                        }
                        if ($fallback !== '') {
                            $imageUrls = [$fallback];
                        } else {
                            return response()->json([
                                'success' => false,
                                'error' => 'قالب واتساب يتطلب صورة في الـ header. تأكد أن APP_URL في .env صحيح (https) وأن الملفات موجودة في public/images أو أضف header_default_image_url في config/whatsapp_meta_templates.php لهذا القالب.',
                            ], 422);
                        }
                    }
                    $parameters = [];
                    foreach ($imageUrls as $u) {
                        $link = trim((string) $u);
                        if ($link === '' || $link === '-') {
                            continue;
                        }
                        if (! str_starts_with(strtolower($link), 'http')) {
                            return response()->json([
                                'success' => false,
                                'error' => 'رابط صورة الـ header غير صالح (يجب أن يبدأ بـ http أو https). راجع APP_URL أو header_default_image_url في config/whatsapp_meta_templates.php.',
                            ], 422);
                        }
                        $link = $this->normalizeImageUrlForMeta($link);
                        if ($link === '') {
                            continue;
                        }
                        if ($this->isHeaderImageUrlUnreachableByMeta($link)) {
                            return response()->json([
                                'success' => false,
                                'error' => 'رابط صورة الـ header لا يمكن لخوادم Meta الوصول إليه (localhost أو شبكة داخلية). عيّن في .env قيمة WHATSAPP_META_MEDIA_BASE_URL على نفس الدومين العام بـ HTTPS الذي يخدم مجلد public/images، أو غيّر APP_URL.',
                            ], 422);
                        }
                        if (str_starts_with(strtolower($link), 'http://') && ! $this->isHeaderImageUrlUnreachableByMeta($link)) {
                            Log::warning('Meta template header image uses HTTP; Meta may fail media fetch — prefer HTTPS', [
                                'template' => $templateName,
                                'host' => parse_url($link, PHP_URL_HOST),
                            ]);
                        }
                        $parameters[] = ['type' => 'image', 'image' => ['link' => $link]];
                    }
                    if ($headerFormat === 'image' && empty($parameters)) {
                        return response()->json([
                            'success' => false,
                            'error' => 'لم يُبنَ معامل صورة صالح للـ header. تأكد أن APP_URL صحيح وأن الملفات تحت public/images متاحة عبر المتصفح (أسماء بلا مسافات).',
                        ], 422);
                    }
                    if (! empty($parameters)) {
                        $components[] = ['type' => 'header', 'parameters' => $parameters];
                    }
                } elseif (! empty($headerParams)) {
                    $parameters = [];
                    foreach ($headerParams as $val) {
                        $text = trim((string) $val) === '' ? '-' : (string) $val;
                        $parameters[] = ['type' => 'text', 'text' => $text];
                    }
                    $components[] = ['type' => 'header', 'parameters' => $parameters];
                }
            }
            if (!empty($bodyParams)) {
                $parameters = [];
                foreach ($bodyParams as $val) {
                    $text = trim((string) $val) === '' ? '-' : (string) $val;
                    $parameters[] = ['type' => 'text', 'text' => $text];
                }
                $components[] = ['type' => 'body', 'parameters' => $parameters];
            }

            $resolvedLang = $templateConfig['api_language_code'] ?? ($templateConfig['language'] ?? $languageCode);
            $languageAsIs = isset($templateConfig['api_language_code'])
                && is_string($templateConfig['api_language_code'])
                && trim($templateConfig['api_language_code']) !== '';
            foreach ($components as $c) {
                if (($c['type'] ?? '') === 'header' && ! empty($c['parameters'])) {
                    foreach ($c['parameters'] as $p) {
                        if (($p['type'] ?? '') === 'image' && ! empty($p['image']['link'])) {
                            $hl = (string) $p['image']['link'];
                            Log::info('Meta template header image URL (for delivery)', [
                                'template' => $templateName,
                                'host' => parse_url($hl, PHP_URL_HOST),
                                'path' => parse_url($hl, PHP_URL_PATH),
                            ]);
                            break 2;
                        }
                    }
                }
            }

            $result = $whatsappService->sendTemplateMessage(
                $phone,
                $templateName,
                $resolvedLang,
                $components,
                $languageAsIs
            );

            if ($result['success']) {
                $customer = Customer::firstOrCreate(
                    ['phone' => $phone],
                    ['name' => $order->customer_name, 'assigned_agent_id' => auth()->id()]
                );
                // Build readable message for chat display (نفس الاسم بلغات مختلفة)
                $langKey = explode('_', $resolvedLang)[0];
                $templateLabels = [
                    'order_confirmation' => 'تأكيد الطلب',
                    'order_flow|en' => 'تجهيز الطلب بالإنجليزية',
                    'order_confirmation_flow|ar' => 'تجهيز الطلب بالعربية',
                    'confirm_order|ar' => 'تأكيد الطلب بالعربية',
                    'confirm_order|en' => 'تأكيد الطلب بالإنجليزية',
                    // 'order_update' => 'تحديث الطلب',
                    // 'hello_world' => 'رسالة ترحيب',
                    'review_request' => 'طلب تقييم',
                    'client_review|en' => 'تقييم العميل بالإنجليزية',
                    'client_review|ar' => 'تقييم العميل بالعربية',
                    'feedback|en' => 'فيد باك بالانجليزية',
                    'feedback|ar' => 'فيد باك بالعربية',
                ];
                $composite = $templateName . '|' . $langKey;
                $label = $templateLabels[$composite] ?? $templateLabels[$templateName] ?? $templateName;
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

                $followupText = isset($templateConfig['session_followup_text'])
                    ? trim((string) $templateConfig['session_followup_text'])
                    : '';
                $followupSent = false;
                $followupError = null;
                if ($followupText !== '') {
                    $followButtons = $templateConfig['session_followup_buttons'] ?? [];
                    $followButtons = is_array($followButtons) ? array_values(array_filter($followButtons, function ($b) {
                        return is_array($b) && ! empty($b['id']) && ! empty($b['title']);
                    })) : [];

                    if ($followButtons !== []) {
                        $followRes = $whatsappService->sendInteractiveButtons($phone, $followupText, $followButtons);
                    } else {
                        $followRes = $whatsappService->sendMessage($phone, $followupText);
                    }
                    $followupSent = (bool) ($followRes['success'] ?? false);
                    if (! $followupSent) {
                        $followupError = $followRes['error'] ?? 'unknown';
                        Log::warning('Meta template session follow-up failed', [
                            'template' => $templateName,
                            'order_id' => $order->id,
                            'error' => $followupError,
                        ]);
                    } else {
                        $preview = mb_strlen($followupText) > 200
                            ? mb_substr($followupText, 0, 200).'…'
                            : $followupText;
                        if ($followButtons !== []) {
                            $btnTitles = array_map(fn ($b) => $b['title'] ?? '', $followButtons);
                            $preview = '[أزرار: '.implode(', ', $btnTitles).'] '.$preview;
                        }
                        Message::create([
                            'customer_id' => $customer->id,
                            'order_id' => $order->id,
                            'sender_id' => auth()->id(),
                            'receiver_id' => null,
                            'content' => $preview,
                            'direction' => 'outbound',
                            'status' => 'sent',
                            'twilio_message_sid' => $followRes['message_sid'] ?? null,
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Template sent successfully',
                    'followup_sent' => $followupSent,
                    'followup_error' => $followupError,
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

    /**
     * Find customer by phone (last 10 digits match; supports Egypt formats and typos like +210… vs +2010…).
     */
    public function findCustomerByPhone(Request $request)
    {
        $phone = $request->query('phone');
        if (!$phone) {
            return response()->json(['success' => false, 'error' => 'phone required'], 422);
        }

        try {
            $customer = $this->findCustomerByPhoneDigits($phone);
            if (!$customer) {
                return response()->json(['success' => true, 'customer' => null], 200);
            }

            return response()->json([
                'success' => true,
                'customer' => $customer,
            ], 200);
        } catch (\Exception $e) {
            Log::error('findCustomerByPhone', ['e' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => 'lookup failed'], 500);
        }
    }

    /**
     * Last few WhatsApp lines for order list tooltip (by customer phone).
     */
    public function getWhatsAppSnippet(Request $request)
    {
        $phone = $request->query('phone');
        if (!$phone) {
            return response()->json(['success' => false, 'error' => 'phone required'], 422);
        }

        try {
            $customer = $this->findCustomerByPhoneDigits($phone);
            if (!$customer) {
                return response()->json([
                    'success' => true,
                    'customer_id' => null,
                    'lines' => [],
                ], 200);
            }

            $messages = Message::where('customer_id', $customer->id)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['content', 'direction', 'created_at']);

            $lines = [];
            foreach ($messages->reverse()->values() as $m) {
                $label = $m->direction === 'inbound' ? 'عميل' : 'رد';
                $text = mb_strlen($m->content) > 120 ? mb_substr($m->content, 0, 120) . '…' : $m->content;
                $lines[] = $label . ': ' . $text;
            }

            return response()->json([
                'success' => true,
                'customer_id' => $customer->id,
                'lines' => $lines,
            ], 200);
        } catch (\Exception $e) {
            Log::error('getWhatsAppSnippet', ['e' => $e->getMessage()]);

            return response()->json(['success' => false, 'error' => 'lookup failed'], 500);
        }
    }

    /**
     * يطابق قالب Meta بالاسم + اللغة (نفس الاسم يمكن أن يكون معتمداً بعدة لغات).
     */
    private function resolveMetaTemplateConfig(array $templates, string $templateName, string $languageCode): ?array
    {
        $reqShort = explode('_', $languageCode)[0];

        return collect($templates)->first(function ($t) use ($templateName, $languageCode, $reqShort) {
            if (($t['name'] ?? '') !== $templateName) {
                return false;
            }
            $tl = (string) ($t['language'] ?? 'ar');
            $cfgShort = explode('_', $tl)[0];

            return $tl === $languageCode
                || $cfgShort === $reqShort;
        });
    }

    /**
     * خوادم Meta تجلب صورة الـ header من الرابط؛ localhost وشبكات خاصة غير قابلة للوصول فيفشل التسليم رغم نجاح Graph.
     */
    private function isHeaderImageUrlUnreachableByMeta(string $url): bool
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        if ($host === '') {
            return true;
        }
        if (strcasecmp($host, 'localhost') === 0) {
            return true;
        }
        if (str_ends_with(strtolower($host), '.local')) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return false;
    }

    /**
     * ترميز مقاطع المسار في رابط الصورة (مسافات وأحرف خاصة) حتى يقبله جلب Meta للوسائط.
     */
    private function normalizeImageUrlForMeta(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (! preg_match('#^https?://#i', $url)) {
            $base = rtrim((string) (config('whatsapp_meta_templates.media_base_url') ?: config('app.url')), '/');
            if ($base === '') {
                return $url;
            }
            $url = $base . '/' . ltrim($url, '/');
        }
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            return $url;
        }
        $path = $parsed['path'] ?? '';
        if ($path === '' || $path === '/') {
            return $url;
        }
        $trimmed = trim($path, '/');
        $segments = explode('/', $trimmed);
        $encoded = implode('/', array_map('rawurlencode', $segments));
        $newPath = '/' . $encoded;
        $scheme = $parsed['scheme'];
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

        return $scheme . '://' . $host . $port . $newPath . $query . $fragment;
    }

    private function normalizePhoneDigits(string $phone): string
    {
        $d = preg_replace('/\D/', '', $phone);
        if ($d === '') {
            return '';
        }
        if (strlen($d) === 11 && str_starts_with($d, '0')) {
            $d = '20' . substr($d, 1);
        } elseif (strlen($d) === 10 && str_starts_with($d, '1')) {
            $d = '20' . $d;
        }

        return $d;
    }

    private function findCustomerByPhoneDigits(string $phone): ?Customer
    {
        $digits = $this->normalizePhoneDigits($phone);
        if ($digits === '') {
            return null;
        }

        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        return Customer::whereRaw(
            "REPLACE(REPLACE(phone, '+', ''), ' ', '') LIKE ?",
            ['%' . $last10]
        )->first();
    }
}
