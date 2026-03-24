<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderConfirmationSession;
use App\Models\OrderDetails;
use App\Models\tracking;
use Illuminate\Support\Facades\Log;

class OrderConfirmationFlowService
{
    public function __construct(
        private MetaWhatsAppService $whatsappService
    ) {}

    /**
     * Map button ID or title to flow action.
     * Supports: confirm_order, postpone_order, cancel_order
     * Also supports alternate IDs and Arabic titles from existing templates.
     */
    private function mapButtonToAction(?string $buttonId, ?string $buttonTitle): ?string
    {
        $idMap = [
            'confirm_order' => 'confirm_order',
            'postpone_order' => 'postpone_order',
            'cancel_order' => 'cancel_order',
            'modify_order' => 'postpone_order',      // تعديل الطلب
            'reship' => 'confirm_order',             // إعادة الشحن
            'reshipping' => 'confirm_order',
        ];
        $titleMap = [
            'تأكيد الطلب' => 'confirm_order',
            'تأجيل الطلب' => 'postpone_order',
            'إلغاء الطلب' => 'cancel_order',
            'تعديل الطلب' => 'postpone_order',
            'إعادة الشحن' => 'confirm_order',
        ];

        if ($buttonId && isset($idMap[$buttonId])) {
            return $idMap[$buttonId];
        }
        if ($buttonTitle && isset($titleMap[trim($buttonTitle)])) {
            return $titleMap[trim($buttonTitle)];
        }
        return $buttonId ?: null;
    }

    /**
     * Handle button reply from order_confirmation_flow template.
     */
    public function handleButtonReply(
        string $customerPhone,
        ?string $buttonId,
        ?string $contextMessageId,
        ?string $phoneNumberId,
        ?string $buttonTitle = null
    ): bool {
        $action = $this->mapButtonToAction($buttonId, $buttonTitle);
        if (!$action) {
            Log::warning('OrderConfirmationFlow: Unknown button', [
                'button_id' => $buttonId,
                'button_title' => $buttonTitle,
            ]);
            return false;
        }

        $order = $this->resolveOrder($customerPhone, $contextMessageId);
        if (!$order) {
            Log::warning('OrderConfirmationFlow: Could not resolve order', [
                'phone' => $customerPhone,
                'button_id' => $buttonId,
                'action' => $action,
            ]);
            $this->whatsappService->sendMessage($customerPhone, 'عذراً، لم نتمكن من العثور على الطلب. يرجى التواصل معنا.');
            return true;
        }

        if (!in_array($order->order_status, ['طلب جديد', 'طلب مؤكد', 'مؤجل'])) {
            $this->whatsappService->sendMessage($customerPhone, "عذراً، الطلب #{$order->id} في حالة {$order->order_status} ولا يمكن تعديله.");
            return true;
        }

        return match ($action) {
            'confirm_order' => $this->handleConfirmOrder($order, $customerPhone),
            'postpone_order' => $this->handlePostponeOrder($order, $customerPhone),
            'cancel_order' => $this->handleCancelOrder($order, $customerPhone),
            'postpone_2days' => $this->handlePostponeChoice($order, $customerPhone, 2),
            'postpone_week' => $this->handlePostponeChoice($order, $customerPhone, 7),
            'postpone_custom' => $this->handlePostponeCustom($order, $customerPhone),
            'cancel_yes' => $this->handleCancelConfirm($order, $customerPhone, true),
            'cancel_no' => $this->handleCancelConfirm($order, $customerPhone, false),
            default => false,
        };
    }

    private function resolveOrder(string $customerPhone, ?string $contextMessageId): ?Order
    {
        if ($contextMessageId) {
            $msg = Message::where('twilio_message_sid', $contextMessageId)->first();
            if ($msg && $msg->order_id) {
                $order = Order::find($msg->order_id);
                if ($order) {
                    Log::info('OrderConfirmationFlow: Order found via context', ['order_id' => $order->id]);
                    return $order;
                }
            }
        }

        $digits = preg_replace('/\D/', '', $customerPhone);
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '20' . substr($digits, 1);
        } elseif (strlen($digits) === 9 && str_starts_with($digits, '1')) {
            $digits = '20' . $digits;
        }
        $mobilePart = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        $order = Order::where(function ($q) use ($digits, $mobilePart) {
            $q->where('customer_phone_1', 'like', "%{$digits}%")
                ->orWhere('customer_phone_1', 'like', "%{$mobilePart}%");
        })
            ->whereIn('order_status', ['طلب جديد', 'طلب مؤكد', 'مؤجل'])
            ->orderByDesc('id')
            ->first();

        if ($order) {
            Log::info('OrderConfirmationFlow: Order found via phone', ['order_id' => $order->id, 'digits' => $digits]);
        }
        return $order;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '20') && strlen($phone) === 11) {
            return '+' . $phone;
        }
        if (strlen($phone) === 10) {
            return '+20' . $phone;
        }
        return '+' . $phone;
    }

    private function handleConfirmOrder(Order $order, string $phone): bool
    {
        Log::info('OrderConfirmationFlow: handleConfirmOrder', ['order_id' => $order->id, 'phone' => $phone]);

        $order->order_status = 'طلب مؤكد';
        $order->save();

        $orderDetails = $order->order_details;
        OrderDetails::updateOrCreate(
            ['order_id' => $order->id],
            [
                'need_by_date' => $orderDetails->need_by_date ?? now()->addDays(3)->format('Y-m-d'),
                'status_date' => now()->format('Y-m-d'),
                'confirm_date' => now()->format('Y-m-d'),
                'reviewed' => 0,
            ]
        );

        tracking::create([
            'order_id' => $order->id,
            'user_id' => null,
            'action' => 'تم تأكيد الطلب (واتساب)',
            'created_at' => now(),
        ]);

        $shippingTime = $orderDetails->need_by_date ?? now()->addDays(3)->format('Y-m-d');
        $msg = "شكرًا لك 🙌\nتم تأكيد طلبك وسيتم التواصل معك قبل الشحن.\n\n📦 موعد الشحن المتوقع:\n{$shippingTime}\n\nفريق Magalis في خدمتك دائمًا.";

        $result = $this->whatsappService->sendMessage($phone, $msg);
        Log::info('OrderConfirmationFlow: sendMessage result', [
            'order_id' => $order->id,
            'success' => $result['success'] ?? false,
            'error' => $result['error'] ?? null,
        ]);
        return $result['success'] ?? false;
    }

    private function handlePostponeOrder(Order $order, string $phone): bool
    {
        OrderConfirmationSession::updateOrCreate(
            [
                'customer_phone' => $phone,
                'order_id' => $order->id,
            ],
            ['flow_state' => 'pending_postpone_choice']
        );

        $buttons = [
            ['id' => 'postpone_2days', 'title' => 'بعد يومين'],
            ['id' => 'postpone_week', 'title' => 'بعد أسبوع'],
            ['id' => 'postpone_custom', 'title' => 'تحديد موعد آخر'],
        ];

        return $this->whatsappService->sendInteractiveButtons(
            $phone,
            "تمام 👍\nهل تود تأجيل الطلب؟",
            $buttons
        )['success'];
    }

    private function handleCancelOrder(Order $order, string $phone): bool
    {
        OrderConfirmationSession::updateOrCreate(
            [
                'customer_phone' => $phone,
                'order_id' => $order->id,
            ],
            ['flow_state' => 'pending_cancel_confirm']
        );

        $buttons = [
            ['id' => 'cancel_yes', 'title' => 'نعم إلغاء الطلب'],
            ['id' => 'cancel_no', 'title' => 'لا أريد تأكيد الطلب'],
        ];

        return $this->whatsappService->sendInteractiveButtons(
            $phone,
            "هل أنت متأكد من إلغاء الطلب #{$order->id}؟",
            $buttons
        )['success'];
    }

    private function handlePostponeChoice(Order $order, string $phone, int $days): bool
    {
        $session = OrderConfirmationSession::where('customer_phone', $phone)
            ->where('order_id', $order->id)
            ->where('flow_state', 'pending_postpone_choice')
            ->first();

        if (!$session) {
            return false;
        }

        $newDate = now()->addDays($days)->format('Y-m-d');
        $order->order_status = 'مؤجل';
        $order->save();

        OrderDetails::updateOrCreate(
            ['order_id' => $order->id],
            [
                'need_by_date' => $newDate,
                'status_date' => now()->format('Y-m-d'),
                'postponed_date' => now()->format('Y-m-d'),
                'postponed' => (($order->order_details?->postponed ?? 0) + 1),
            ]
        );

        tracking::create([
            'order_id' => $order->id,
            'user_id' => null,
            'action' => 'تم تأجيل الطلب (واتساب)',
            'created_at' => now(),
        ]);

        $session->delete();

        $msg = "تم تأجيل طلبك بنجاح ✔️\nوسنقوم بالتواصل معك في الموعد المحدد لتأكيد الشحن.\n\nرقم الطلب: #{$order->id}";

        return $this->whatsappService->sendMessage($phone, $msg)['success'];
    }

    private function handlePostponeCustom(Order $order, string $phone): bool
    {
        $session = OrderConfirmationSession::where('customer_phone', $phone)
            ->where('order_id', $order->id)
            ->where('flow_state', 'pending_postpone_choice')
            ->first();

        if (!$session) {
            return false;
        }

        $session->delete();

        $msg = "تم تأجيل طلبك بنجاح ✔️\nوسنقوم بالتواصل معك في الموعد المحدد لتأكيد الشحن.\n\nرقم الطلب: #{$order->id}\n\nيرجى التواصل معنا لتحديد الموعد المناسب.";

        $order->order_status = 'مؤجل';
        $order->save();

        OrderDetails::updateOrCreate(
            ['order_id' => $order->id],
            [
                'status_date' => now()->format('Y-m-d'),
                'postponed_date' => now()->format('Y-m-d'),
                'postponed' => (($order->order_details?->postponed ?? 0) + 1),
            ]
        );

        tracking::create([
            'order_id' => $order->id,
            'user_id' => null,
            'action' => 'تم تأجيل الطلب (واتساب - موعد مخصص)',
            'created_at' => now(),
        ]);

        return $this->whatsappService->sendMessage($phone, $msg)['success'];
    }

    private function handleCancelConfirm(Order $order, string $phone, bool $confirm): bool
    {
        $session = OrderConfirmationSession::where('customer_phone', $phone)
            ->where('order_id', $order->id)
            ->where('flow_state', 'pending_cancel_confirm')
            ->first();

        if (!$session) {
            return false;
        }

        $session->delete();

        if ($confirm) {
            $order->order_status = 'ملغي';
            $order->save();

            OrderDetails::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'canceled_date' => now()->format('Y-m-d'),
                    'status_date' => now()->format('Y-m-d'),
                ]
            );

            tracking::create([
                'order_id' => $order->id,
                'user_id' => null,
                'action' => 'تم إلغاء الطلب (واتساب)',
                'created_at' => now(),
            ]);

            $msg = "تم إلغاء طلبك بنجاح.\n\nيسعدنا خدمتك مرة أخرى في أي وقت 🤍\nفريق Magalis";
        } else {
            $msg = "تم إلغاء عملية الإلغاء.\nطلبك #{$order->id} ما زال قيد التجهيز.";
        }

        return $this->whatsappService->sendMessage($phone, $msg)['success'];
    }

    /**
     * Handle text reply when in flow (e.g. custom date).
     */
    public function handleTextReply(string $customerPhone, string $text): bool
    {
        $session = OrderConfirmationSession::where('customer_phone', $customerPhone)->first();
        if (!$session) {
            return false;
        }

        return false;
    }
}
