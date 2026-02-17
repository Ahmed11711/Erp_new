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

    public function __construct(MetaWhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
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
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $phone = $request->customer_phone;
            $messageContent = $request->message;
            $orderId = $request->order_id;

            // Format phone number (ensure it starts with country code)
            if (!str_starts_with($phone, '+')) {
                // Assume Egypt country code if not provided
                if (str_starts_with($phone, '0')) {
                    $phone = '+2' . substr($phone, 1);
                } else {
                    $phone = '+2' . $phone;
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
            $result = $this->whatsappService->sendMessage($phone, $messageContent);

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

            // Format phone number
            if (!str_starts_with($phone, '+')) {
                if (str_starts_with($phone, '0')) {
                    $phone = '+2' . substr($phone, 1);
                } else {
                    $phone = '+2' . $phone;
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
                    $phone = '+2' . substr($phone, 1);
                } else {
                    $phone = '+2' . $phone;
                }
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
}
