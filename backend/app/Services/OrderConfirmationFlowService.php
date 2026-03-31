<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Order;
use App\Models\OrderConfirmationSession;
use Illuminate\Support\Facades\Log;

/**
 * فلو أزرار قوالب واتساب (تأكيد / تأجيل / إلغاء / رفض استلام): ردود رسائل فقط — لا تعديل على الطلبات في ERP.
 */
class OrderConfirmationFlowService
{
    public function __construct(
        private MetaWhatsAppService $whatsappService
    ) {}

    private function normalizePhoneKey(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    /**
     * استنتاج لغة الفلو من أزرار قالب order_flow (en) مقابل القوالب العربية.
     */
    private function detectFlowLocale(?string $buttonId, ?string $buttonTitle): string
    {
        $title = $this->normalizeButtonTitle($buttonTitle);
        if ($title !== null) {
            if (preg_match('/^(recharge|reship(ping)?|confirm\s+preparation|confirm\s+shipping|delay\s+shipping|within\s+3\s+days|schedule\s+a\s+date)$/iu', $title)) {
                return 'en';
            }
            if (preg_match('/^edit\s+order$/iu', $title)) {
                return 'en';
            }
            if (preg_match('/^cancel\s+order$/iu', $title)) {
                return 'en';
            }
            if (preg_match('/\p{Arabic}/u', $title)) {
                return 'ar';
            }
        }
        $id = $this->normalizeButtonPayloadId($buttonId);
        if ($id !== null && preg_match('/^(recharge|reship|edit_order|cancel_order|shipdelay_3days|shipdelay_schedule)$/i', $id)) {
            return 'en';
        }

        return 'ar';
    }

    /**
     * توحيد Unicode (NFC) لتطابق مفاتيح العربية مع ما ترسله ميتا.
     */
    private function normalizeUnicodeNfc(string $s): string
    {
        if (class_exists(\Normalizer::class)) {
            $n = \Normalizer::normalize($s, \Normalizer::FORM_C);
            if ($n !== false) {
                return $n;
            }
        }

        return $s;
    }

    /**
     * تنظيف عنوان الزر (مسافات، أحرف عرض صفرية قد تمنع المطابقة).
     */
    private function normalizeButtonTitle(?string $title): ?string
    {
        if ($title === null || $title === '') {
            return null;
        }
        $t = trim($title);
        $t = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $t);
        $t = $this->normalizeUnicodeNfc($t);

        return $t === '' ? null : $t;
    }

    /**
     * معرّف الزر من ميتا: لا تستخدم strtolower على العربية (تفسد UTF-8).
     * للمعرّفات اللاتينية فقط (confirm_order…) يُطبَّق strtolower.
     */
    private function normalizeButtonPayloadId(?string $buttonId): ?string
    {
        if ($buttonId === null || trim($buttonId) === '') {
            return null;
        }
        $t = trim($buttonId);
        $t = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $t);
        $t = $this->normalizeUnicodeNfc($t);
        if (preg_match('/^[a-zA-Z0-9_\-\.]+$/', $t)) {
            return strtolower($t);
        }

        return $t;
    }

    /**
     * Map button ID or title to internal action (قالب أولي أو خطوات فرعية).
     */
    private function mapButtonToAction(?string $buttonId, ?string $buttonTitle): ?string
    {
        $id = $this->normalizeButtonPayloadId($buttonId);
        $title = $this->normalizeButtonTitle($buttonTitle);

        /** @var array<string, string> */
        $titleMap = [
            // أولوية العنوان: ميتا قد ترسل نفس id (مثل confirm_order) لقوالب مراحل مختلفة
            'Confirm Preparation' => 'confirm_preparation',
            'Confirm Shipping' => 'confirm_shipping',
            'Delay Shipping' => 'postpone_shipping_delay',
            'Edit Order' => 'postpone_order',
            'تأكيد التجهيز' => 'confirm_preparation',
            'تأكيد الشحن' => 'confirm_shipping',
            'تعديل الطلب' => 'postpone_order',
            'تأجيل الطلب' => 'postpone_shipping_delay',
            'تاجيل الطلب' => 'postpone_shipping_delay',
            'خلال 3 أيام' => 'shipdelay_3days',
            'تحديد موعد' => 'shipdelay_schedule',
            'Within 3 days' => 'shipdelay_3days',
            'Schedule a date' => 'shipdelay_schedule',
            'تأكيد الطلب' => 'confirm_preparation',
            'إلغاء الطلب' => 'cancel_order',
            // قالب عربي يعرض "إعادة الشحن" بدل التأكيد — اعتبره تأكيد تجهيز/متابعة
            'إعادة الشحن' => 'confirm_preparation',
            'نعم إلغاء الطلب' => 'cancel_yes_confirm',
            'لا أريد تأكيد الطلب' => 'cancel_no_confirm',
            'لا أريد إلغاء الطلب' => 'cancel_no_confirm',
            'بعد يومين' => 'postpone_2days',
            'بعد أسبوع' => 'postpone_week',
            'تحديد موعد آخر' => 'postpone_custom',
            'أريد إعادة الشحن' => 'reject_reship',
            'أريد تعديل الطلب' => 'reject_modify',
            'أريد إلغاء الطلب' => 'reject_cancel_from_rejection',
            'رفض الاستلام' => 'delivery_rejection_start',
            'In two days' => 'postpone_2days',
            'In one week' => 'postpone_week',
            'Another date' => 'postpone_custom',
            'Yes, cancel order' => 'cancel_yes_confirm',
            'No, keep order' => 'cancel_no_confirm',
            'I want reshipping' => 'reject_reship',
            'I want to edit order' => 'reject_modify',
            'I want to cancel' => 'reject_cancel_from_rejection',
            'Reship order' => 'reject_reship',
            'Change order' => 'reject_modify',
            'Cancel delivery' => 'reject_cancel_from_rejection',
        ];

        foreach ($titleMap as $key => $mappedAction) {
            $nk = $this->normalizeUnicodeNfc($key);
            if ($title !== null && $title === $nk) {
                return $mappedAction;
            }
        }

        $idMap = [
            'confirm_order' => 'confirm_preparation',
            'postpone_order' => 'postpone_order',
            'cancel_order' => 'cancel_order',
            'modify_order' => 'postpone_order',
            'reship' => 'confirm_preparation',
            'reshipping' => 'confirm_preparation',
            'delivery_rejection' => 'delivery_rejection_start',
            'delivery_rejection_flow' => 'delivery_rejection_start',
            'rejection_flow' => 'delivery_rejection_start',
            'postpone_2days' => 'postpone_2days',
            'postpone_week' => 'postpone_week',
            'postpone_custom' => 'postpone_custom',
            'shipdelay_3days' => 'shipdelay_3days',
            'shipdelay_schedule' => 'shipdelay_schedule',
            'cancel_yes' => 'cancel_yes_confirm',
            'cancel_no' => 'cancel_no_confirm',
            'reject_reship' => 'reject_reship',
            'reject_modify' => 'reject_modify',
            'reject_cancel_from_rejection' => 'reject_cancel_from_rejection',
        ];

        if ($id !== null && isset($idMap[$id])) {
            return $idMap[$id];
        }

        foreach ($titleMap as $key => $mappedAction) {
            $nk = $this->normalizeUnicodeNfc($key);
            if ($id !== null && $id === $nk) {
                return $mappedAction;
            }
        }

        $fb = $this->mapButtonTitleFallback($title);
        if ($fb !== null) {
            return $fb;
        }

        $fbId = $this->mapButtonTitleFallback($id);
        if ($fbId !== null) {
            return $fbId;
        }

        return $this->mapArabicButtonHeuristic($id, $title);
    }

    /**
     * مطابقة أخيرة لأزرار القالب العربية (ميتا قد ترسل أشكالاً مختلفة قليلاً عن نص الملف).
     */
    private function mapArabicButtonHeuristic(?string $id, ?string $title): ?string
    {
        foreach ([$id, $title] as $s) {
            if ($s === null || $s === '') {
                continue;
            }
            if (mb_strpos($s, 'إلغاء') !== false && mb_strpos($s, 'طلب') !== false) {
                return 'cancel_order';
            }
            if (mb_strpos($s, 'تعديل') !== false && mb_strpos($s, 'طلب') !== false) {
                return 'postpone_order';
            }
            if ((mb_strpos($s, 'تأجيل') !== false || mb_strpos($s, 'تاجيل') !== false) && mb_strpos($s, 'طلب') !== false) {
                return 'postpone_shipping_delay';
            }
            if (mb_strpos($s, 'تأكيد') !== false && mb_strpos($s, 'التجهيز') !== false) {
                return 'confirm_preparation';
            }
            if (mb_strpos($s, 'تأكيد') !== false && mb_strpos($s, 'الشحن') !== false) {
                return 'confirm_shipping';
            }
        }

        return null;
    }

    /**
     * مطابقة احتياطية لعناوين أزرار القالب (اختلاف حروف/مسافات عن الجدول).
     */
    private function mapButtonTitleFallback(?string $title): ?string
    {
        if ($title === null || $title === '') {
            return null;
        }
        $t = $this->normalizeButtonTitle($title);
        if ($t === null) {
            return null;
        }

        if (preg_match('/إعادة\s*الشحن/u', $t)) {
            return 'confirm_preparation';
        }
        if (preg_match('/تعديل/u', $t) && preg_match('/طلب/u', $t)) {
            return 'postpone_order';
        }
        if ((preg_match('/تأجيل/u', $t) || preg_match('/تاجيل/u', $t)) && preg_match('/طلب/u', $t)) {
            return 'postpone_shipping_delay';
        }
        if (preg_match('/إلغاء/u', $t) && preg_match('/طلب/u', $t)) {
            return 'cancel_order';
        }

        // قالب إنجليزي (مثلاً order_flow): أزرار Quick Reply
        if (preg_match('/^recharge$/iu', $t) || preg_match('/^reship(ping)?$/iu', $t)) {
            return 'confirm_preparation';
        }
        if (preg_match('/^confirm\s+preparation$/iu', $t)) {
            return 'confirm_preparation';
        }
        if (preg_match('/^confirm\s+shipping$/iu', $t)) {
            return 'confirm_shipping';
        }
        if (preg_match('/^delay\s+shipping$/iu', $t)) {
            return 'postpone_shipping_delay';
        }
        if (preg_match('/^edit\s+order$/iu', $t)) {
            return 'postpone_order';
        }
        if (preg_match('/^cancel\s+order$/iu', $t)) {
            return 'cancel_order';
        }

        return null;
    }

    /**
     * مطابقة نص حر لخطوات التأجيل / الإلغاء.
     */
    private function mapTextToPostponeChoice(string $text, string $locale = 'ar'): ?string
    {
        $t = mb_strtolower(trim($text));
        $mapAr = [
            '1' => 'postpone_2days',
            '1️⃣' => 'postpone_2days',
            'بعد يومين' => 'postpone_2days',
            '2' => 'postpone_week',
            '2️⃣' => 'postpone_week',
            'بعد أسبوع' => 'postpone_week',
            '3' => 'postpone_custom',
            '3️⃣' => 'postpone_custom',
            'تحديد موعد آخر' => 'postpone_custom',
            'موعد آخر' => 'postpone_custom',
        ];
        $mapEn = [
            '1' => 'postpone_2days',
            '1️⃣' => 'postpone_2days',
            '2' => 'postpone_week',
            '2️⃣' => 'postpone_week',
            '3' => 'postpone_custom',
            '3️⃣' => 'postpone_custom',
            'in two days' => 'postpone_2days',
            'after two days' => 'postpone_2days',
            'in one week' => 'postpone_week',
            'after one week' => 'postpone_week',
            'another date' => 'postpone_custom',
            'pick another date' => 'postpone_custom',
        ];
        $map = $locale === 'en' ? array_merge($mapAr, $mapEn) : $mapAr;
        foreach ($map as $key => $action) {
            if ($t === mb_strtolower($key)) {
                return $action;
            }
        }

        return null;
    }

    /**
     * خيارا تأجيل الشحن (يومين/موعد) — نص حر أو رقم الخيار.
     */
    private function mapTextToShippingDelayChoice(string $text): ?string
    {
        $t = $this->normalizeUnicodeNfc(trim($text));
        $tLower = mb_strtolower($t);
        $map = [
            '1' => 'shipdelay_3days',
            '1️⃣' => 'shipdelay_3days',
            '2' => 'shipdelay_schedule',
            '2️⃣' => 'shipdelay_schedule',
            'within 3 days' => 'shipdelay_3days',
            'schedule a date' => 'shipdelay_schedule',
            'خلال 3 أيام' => 'shipdelay_3days',
            'تحديد موعد' => 'shipdelay_schedule',
        ];
        foreach ($map as $key => $action) {
            if ($tLower === mb_strtolower($key)) {
                return $action;
            }
        }

        return null;
    }

    private function mapTextToCancelChoice(string $text, string $locale = 'ar'): ?string
    {
        $t = trim($text);
        $tLower = mb_strtolower($t);
        if ($locale === 'en') {
            if (in_array($tLower, ['1', '1️⃣', 'yes', 'yes, cancel order', 'yes cancel'], true)) {
                return 'cancel_yes_confirm';
            }
            if (in_array($tLower, ['2', '2️⃣', 'no', 'no, keep order', 'no keep order'], true)) {
                return 'cancel_no_confirm';
            }

            return null;
        }
        if (in_array($t, ['1', '1️⃣', 'نعم', 'نعم إلغاء الطلب'], true)) {
            return 'cancel_yes_confirm';
        }
        if (in_array($t, ['2', '2️⃣', 'لا', 'لا أريد تأكيد الطلب', 'لا أريد إلغاء الطلب'], true)) {
            return 'cancel_no_confirm';
        }

        return null;
    }

    private function mapTextToDeliveryChoice(string $text, string $locale = 'ar'): ?string
    {
        $t = mb_strtolower(trim($text));
        $pairs = [
            'reject_reship' => ['1', '1️⃣', 'أريد إعادة الشحن', 'إعادة الشحن'],
            'reject_modify' => ['2', '2️⃣', 'أريد تعديل الطلب', 'تعديل الطلب'],
            'reject_cancel_from_rejection' => ['3', '3️⃣', 'أريد إلغاء الطلب'],
        ];
        if ($locale === 'en') {
            $pairs['reject_reship'] = array_merge($pairs['reject_reship'], ['i want reshipping', 'reship', 'reshipping', 'reship order']);
            $pairs['reject_modify'] = array_merge($pairs['reject_modify'], ['i want to edit order', 'change order']);
            $pairs['reject_cancel_from_rejection'] = array_merge($pairs['reject_cancel_from_rejection'], ['i want to cancel', 'cancel delivery']);
        }
        foreach ($pairs as $action => $needles) {
            foreach ($needles as $n) {
                if ($t === mb_strtolower($n)) {
                    return $action;
                }
            }
        }

        return null;
    }

    public function handleButtonReply(
        string $customerPhone,
        ?string $buttonId,
        ?string $contextMessageId,
        ?string $phoneNumberId,
        ?string $buttonTitle = null
    ): bool {
        $phoneKey = $this->normalizePhoneKey($customerPhone);
        $order = $this->resolveOrder($customerPhone, $contextMessageId);

        $session = OrderConfirmationSession::where('customer_phone', $phoneKey)
            ->orderByDesc('updated_at')
            ->first();

        if ($session && $order && (int) $session->order_id !== (int) $order->id) {
            $session->delete();
            $session = null;
        }

        if ($session && ! $order) {
            $order = Order::find($session->order_id);
        }

        $localeHint = $this->detectFlowLocale($buttonId, $buttonTitle);

        if (! $order) {
            Log::warning('OrderConfirmationFlow: Could not resolve order', [
                'phone' => $customerPhone,
                'button_id' => $buttonId,
            ]);
            $err = $localeHint === 'en'
                ? "Sorry, we couldn't find your order. Please contact us."
                : 'عذراً، لم نتمكن من العثور على الطلب. يرجى التواصل معنا.';
            $this->whatsappService->sendMessage($customerPhone, $err);

            return true;
        }

        $action = $this->mapButtonToAction($buttonId, $buttonTitle);

        if ($session && (int) $session->order_id === (int) $order->id) {
            $state = $session->flow_state;
            // فقط أفعال الخطوة الفرعية — لا تمرّر postpone_order أو cancel_order من القالب الرئيسي هنا
            if ($state === 'pending_cancel_confirm' && $action && in_array($action, ['cancel_yes_confirm', 'cancel_no_confirm'], true)) {
                return $this->stepCancelConfirm($session, $order, $customerPhone, $action);
            }
            if ($state === 'pending_shipping_delay_choice' && $action && in_array($action, ['shipdelay_3days', 'shipdelay_schedule'], true)) {
                return $this->stepShippingDelayChoice($session, $order, $customerPhone, $action);
            }
            if ($state === 'pending_postpone_choice' && $action && in_array($action, ['postpone_2days', 'postpone_week', 'postpone_custom'], true)) {
                return $this->stepPostponeChoice($session, $order, $customerPhone, $action);
            }
            if ($state === 'pending_delivery_rejection_choice' && $action && in_array($action, ['reject_reship', 'reject_modify', 'reject_cancel_from_rejection'], true)) {
                return $this->stepDeliveryRejection($session, $order, $customerPhone, $action);
            }
        }

        if (! $action) {
            Log::warning('OrderConfirmationFlow: Unknown button', [
                'button_id' => $buttonId,
                'button_title' => $buttonTitle,
            ]);

            return false;
        }

        // جلسة قديمة لنفس الطلب: بدء خطوة جديدة من القالب الرئيسي يُلغي الجلسة
        $sessionRefresh = OrderConfirmationSession::where('customer_phone', $phoneKey)
            ->where('order_id', $order->id)
            ->first();
        if ($sessionRefresh && in_array($action, [
            'confirm_preparation', 'confirm_shipping', 'postpone_order', 'postpone_shipping_delay', 'cancel_order', 'delivery_rejection_start',
        ], true)) {
            $sessionRefresh->delete();
        }

        return match ($action) {
            'confirm_preparation' => $this->handleConfirmPreparation($order, $customerPhone, $localeHint),
            'confirm_shipping' => $this->handleConfirmShipping($order, $customerPhone, $localeHint),
            'postpone_order' => $this->handlePostponeStart($order, $customerPhone, $localeHint),
            'postpone_shipping_delay' => $this->handleShippingDelayStart($order, $customerPhone, $localeHint),
            'cancel_order' => $this->handleCancelStart($order, $customerPhone, $localeHint),
            'delivery_rejection_start' => $this->handleDeliveryRejectionStart($order, $customerPhone, $localeHint),
            default => false,
        };
    }

    /**
     * ردود نصية ضمن جلسة (بدون زر)، مثل 1/2/3 أو نسخ العبارات يدوياً.
     */
    public function handleTextReply(string $customerPhone, string $text, ?string $contextMessageId = null): bool
    {
        $phoneKey = $this->normalizePhoneKey($customerPhone);
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        $session = OrderConfirmationSession::where('customer_phone', $phoneKey)
            ->orderByDesc('updated_at')
            ->first();

        if (! $session) {
            return false;
        }

        $order = Order::find($session->order_id);
        if (! $order) {
            $session->delete();

            return false;
        }

        $flowLocale = $session->flow_locale ?? 'ar';

        if ($session->flow_state === 'pending_shipping_delay_choice') {
            $a = $this->mapTextToShippingDelayChoice($text);
            if ($a) {
                return $this->stepShippingDelayChoice($session, $order, $customerPhone, $a);
            }

            return false;
        }

        if ($session->flow_state === 'pending_postpone_choice') {
            $a = $this->mapTextToPostponeChoice($text, $flowLocale);
            if ($a) {
                return $this->stepPostponeChoice($session, $order, $customerPhone, $a);
            }

            return false;
        }

        if ($session->flow_state === 'pending_cancel_confirm') {
            $a = $this->mapTextToCancelChoice($text, $flowLocale);
            if ($a) {
                return $this->stepCancelConfirm($session, $order, $customerPhone, $a);
            }

            return false;
        }

        if ($session->flow_state === 'pending_delivery_rejection_choice') {
            $a = $this->mapTextToDeliveryChoice($text, $flowLocale);
            if ($a) {
                return $this->stepDeliveryRejection($session, $order, $customerPhone, $a);
            }

            return false;
        }

        return false;
    }

    private function stepCancelConfirm(OrderConfirmationSession $session, Order $order, string $phone, string $action): bool
    {
        $loc = $session->flow_locale ?? 'ar';
        if ($action === 'cancel_yes_confirm') {
            $session->delete();
            $msg = $loc === 'en'
                ? "Your order has been cancelled.\n\nWe'd love to serve you again anytime 🤍\nMagalis team"
                : "تم إلغاء طلبك بنجاح.\n\nيسعدنا خدمتك مرة أخرى في أي وقت 🤍\nفريق Magalis";

            return $this->whatsappService->sendMessage($phone, $msg)['success'] ?? false;
        }
        if ($action === 'cancel_no_confirm') {
            $session->delete();
            $msg = $loc === 'en'
                ? "All good 👍\nOrder #{$order->id} was not cancelled. You can reach us anytime.\nMagalis team"
                : "تمام 👍\nلم يُلغَ الطلب #{$order->id}. يمكنك التواصل معنا في أي وقت.\nفريق Magalis";

            return $this->whatsappService->sendMessage($phone, $msg)['success'] ?? false;
        }

        return false;
    }

    private function stepShippingDelayChoice(OrderConfirmationSession $session, Order $order, string $phone, string $action): bool
    {
        if (! in_array($action, ['shipdelay_3days', 'shipdelay_schedule'], true)) {
            return false;
        }
        $loc = $session->flow_locale ?? 'ar';
        $session->delete();

        if ($action === 'shipdelay_3days') {
            $msg = $loc === 'en'
                ? "Thank you. We'll follow up within 3 days about shipping for order #{$order->id}.\nMagalis team"
                : "شكرًا لك. سنتواصل معك خلال 3 أيام بخصوص شحن الطلب #{$order->id}.\nفريق Magalis";
        } else {
            $msg = $loc === 'en'
                ? "Thank you. We'll contact you to set a convenient shipping date for order #{$order->id}.\nMagalis team"
                : "شكرًا لك. سنتواصل معك لتحديد موعد مناسب لشحن الطلب #{$order->id}.\nفريق Magalis";
        }

        return $this->whatsappService->sendMessage($phone, $msg)['success'] ?? false;
    }

    private function stepPostponeChoice(OrderConfirmationSession $session, Order $order, string $phone, string $action): bool
    {
        if (! in_array($action, ['postpone_2days', 'postpone_week', 'postpone_custom'], true)) {
            return false;
        }
        $loc = $session->flow_locale ?? 'ar';
        $session->delete();

        $msg = $loc === 'en'
            ? "Your order has been postponed successfully ✔️\n"
                ."We'll contact you at the scheduled time to confirm shipping.\n\n"
                ."Order number: #{$order->id}"
            : "تم تأجيل طلبك بنجاح ✔️\n"
                ."وسنقوم بالتواصل معك في الموعد المحدد لتأكيد الشحن.\n\n"
                ."رقم الطلب: #{$order->id}";

        return $this->whatsappService->sendMessage($phone, $msg)['success'] ?? false;
    }

    private function stepDeliveryRejection(OrderConfirmationSession $session, Order $order, string $phone, string $action): bool
    {
        $loc = $session->flow_locale ?? 'ar';
        $session->delete();

        if ($action === 'reject_reship') {
            $msg = $loc === 'en'
                ? "No problem 👍\n"
                    ."We'll arrange another delivery attempt.\n\n"
                    ."We'll contact you to confirm the address and delivery time."
                : "لا مشكلة 👍\n"
                    ."سنقوم بترتيب إعادة شحن الطلب مرة أخرى.\n\n"
                    .'سيتم التواصل معك لتأكيد العنوان وموعد التسليم.';

            return $this->whatsappService->sendMessage($phone, $msg)['success'] ?? false;
        }
        if ($action === 'reject_modify') {
            $msg = $loc === 'en'
                ? "Got it 👍\nTo change order #{$order->id}, please contact our customer support team directly.\nMagalis team"
                : "تمام 👍\nلمساعدتك في تعديل الطلب #{$order->id}، يرجى التواصل مع فريق خدمة العملاء مباشرة.\nفريق Magalis";

            return $this->whatsappService->sendMessage($phone, $msg)['success'] ?? false;
        }
        if ($action === 'reject_cancel_from_rejection') {
            $msg = $loc === 'en'
                ? "We’ve noted your request.\nTo cancel order #{$order->id}, please contact customer support — cancellation isn’t completed automatically in chat.\nMagalis team"
                : "تم استلام طلبك.\nلإلغاء الطلب #{$order->id} يرجى التواصل مع فريق خدمة العملاء — لا يتم إلغاء الطلب تلقائياً من المحادثة.\nفريق Magalis";

            return $this->whatsappService->sendMessage($phone, $msg)['success'] ?? false;
        }

        return false;
    }

    /**
     * أزرار تفاعلية (جلسة 24 ساعة) — عند الفشل يُرسل النص الكامل كما كان سابقاً.
     *
     * @param  array<int, array{id: string, title: string}>  $buttons
     */
    private function sendInteractiveWithTextFallback(string $phone, string $bodyInteractive, array $buttons, string $fallbackPlain): bool
    {
        $res = $this->whatsappService->sendInteractiveButtons($phone, $bodyInteractive, $buttons);
        if ($res['success'] ?? false) {
            return true;
        }
        Log::warning('OrderConfirmationFlow: interactive buttons failed, fallback to plain text', [
            'error' => $res['error'] ?? null,
        ]);

        return $this->whatsappService->sendMessage($phone, $fallbackPlain)['success'] ?? false;
    }

    private function handleConfirmPreparation(Order $order, string $phone, string $locale = 'ar'): bool
    {
        if ($locale === 'en') {
            $msg = "Your order preparation has been confirmed 👌\n"
                ."Our team has started processing it.\n"
                ."Order number: #{$order->id}\n\n"
                ."We'll notify you once it's ready for shipping.";
        } else {
            $msg = "تم تأكيد بدء تجهيز طلبك 👌\n"
                ."فريقنا بدأ التنفيذ الآن.\n"
                ."رقم الطلب: #{$order->id}\n\n"
                .'هنبلغك أول ما يكون جاهز للشحن';
        }

        return $this->whatsappService->sendMessage($phone, $msg)['success'] ?? false;
    }

    private function handleConfirmShipping(Order $order, string $phone, string $locale = 'ar'): bool
    {
        if ($locale === 'en') {
            $msg = "Your shipment has been confirmed 🚚\n"
                ."Your order is being prepared for dispatch.\n"
                ."Order number: #{$order->id}";
        } else {
            $msg = "تم تأكيد شحن طلبك 🚚\n"
                ."جارٍ تجهيز الشحنة للخروج.\n"
                ."رقم الطلب: #{$order->id}";
        }

        return $this->whatsappService->sendMessage($phone, $msg)['success'] ?? false;
    }

    private function handlePostponeStart(Order $order, string $phone, string $locale = 'ar'): bool
    {
        OrderConfirmationSession::updateOrCreate(
            [
                'customer_phone' => $this->normalizePhoneKey($phone),
                'order_id' => $order->id,
            ],
            [
                'flow_state' => 'pending_postpone_choice',
                'flow_locale' => $locale,
            ]
        );

        if ($locale === 'en') {
            $body = "Sounds good 👍\n"
                ."Would you like to postpone the order?\n\n"
                .'Choose a time that works for you:';
            $buttons = [
                ['id' => 'postpone_2days', 'title' => 'In two days'],
                ['id' => 'postpone_week', 'title' => 'In one week'],
                ['id' => 'postpone_custom', 'title' => 'Another date'],
            ];
            $fallback = "Sounds good 👍\n"
                ."Would you like to postpone the order?\n\n"
                ."Choose a time that works for you:\n\n"
                ."1️⃣ In two days\n2️⃣ In one week\n3️⃣ Another date";
        } else {
            $body = "تمام 👍\n"
                ."هل تود تأجيل الطلب؟\n\n"
                .'اختر الوقت المناسب لك:';
            $buttons = [
                ['id' => 'postpone_2days', 'title' => 'بعد يومين'],
                ['id' => 'postpone_week', 'title' => 'بعد أسبوع'],
                ['id' => 'postpone_custom', 'title' => 'تحديد موعد آخر'],
            ];
            $fallback = "تمام 👍\n"
                ."هل تود تأجيل الطلب؟\n\n"
                ."اختر الوقت المناسب لك:\n\n"
                ."1️⃣ بعد يومين\n2️⃣ بعد أسبوع\n3️⃣ تحديد موعد آخر";
        }

        return $this->sendInteractiveWithTextFallback($phone, $body, $buttons, $fallback);
    }

    private function handleShippingDelayStart(Order $order, string $phone, string $locale = 'ar'): bool
    {
        OrderConfirmationSession::updateOrCreate(
            [
                'customer_phone' => $this->normalizePhoneKey($phone),
                'order_id' => $order->id,
            ],
            [
                'flow_state' => 'pending_shipping_delay_choice',
                'flow_locale' => $locale,
            ]
        );

        if ($locale === 'en') {
            $body = "Got it 👍 Shipping has been delayed\n"
                .'Would you like to schedule a new date?';
            $buttons = [
                ['id' => 'shipdelay_3days', 'title' => 'Within 3 days'],
                ['id' => 'shipdelay_schedule', 'title' => 'Schedule a date'],
            ];
            $fallback = "Got it 👍 Shipping has been delayed\n"
                ."Would you like to schedule a new date?\n\n"
                ."1️⃣ Within 3 days\n2️⃣ Schedule a date";
        } else {
            $body = "تمام 👍 تم تأجيل الشحن\n"
                .'هل نحدد موعد جديد؟';
            $buttons = [
                ['id' => 'shipdelay_3days', 'title' => 'خلال 3 أيام'],
                ['id' => 'shipdelay_schedule', 'title' => 'تحديد موعد'],
            ];
            $fallback = "تمام 👍 تم تأجيل الشحن\n"
                ."هل نحدد موعد جديد؟\n\n"
                ."1️⃣ خلال 3 أيام\n2️⃣ تحديد موعد";
        }

        return $this->sendInteractiveWithTextFallback($phone, $body, $buttons, $fallback);
    }

    private function handleCancelStart(Order $order, string $phone, string $locale = 'ar'): bool
    {
        OrderConfirmationSession::updateOrCreate(
            [
                'customer_phone' => $this->normalizePhoneKey($phone),
                'order_id' => $order->id,
            ],
            [
                'flow_state' => 'pending_cancel_confirm',
                'flow_locale' => $locale,
            ]
        );

        if ($locale === 'en') {
            $body = "Are you sure you want to cancel order #{$order->id}?";
            $buttons = [
                ['id' => 'cancel_yes', 'title' => 'Yes, cancel order'],
                ['id' => 'cancel_no', 'title' => 'No, keep order'],
            ];
            $fallback = "Are you sure you want to cancel order #{$order->id}?\n\n"
                ."1️⃣ Yes, cancel order\n2️⃣ No, keep order";
        } else {
            $body = "هل أنت متأكد من إلغاء الطلب #{$order->id}؟";
            $buttons = [
                ['id' => 'cancel_yes', 'title' => 'نعم إلغاء الطلب'],
                ['id' => 'cancel_no', 'title' => 'لا أريد تأكيد الطلب'],
            ];
            $fallback = "هل أنت متأكد من إلغاء الطلب #{$order->id}؟\n\n"
                ."1️⃣ نعم إلغاء الطلب\n2️⃣ لا أريد تأكيد الطلب";
        }

        return $this->sendInteractiveWithTextFallback($phone, $body, $buttons, $fallback);
    }

    private function handleDeliveryRejectionStart(Order $order, string $phone, string $locale = 'ar'): bool
    {
        OrderConfirmationSession::updateOrCreate(
            [
                'customer_phone' => $this->normalizePhoneKey($phone),
                'order_id' => $order->id,
            ],
            [
                'flow_state' => 'pending_delivery_rejection_choice',
                'flow_locale' => $locale,
            ]
        );

        if ($locale === 'en') {
            $body = "We noticed the delivery for order #{$order->id} was refused.\n\n"
                .'Let us know how we can help:';
            // عناوين مختلفة عن «Edit Order» في القالب الرئيسي حتى لا يُفسَّر الضغط كـ postpone_order.
            $buttons = [
                ['id' => 'reject_reship', 'title' => 'Reship order'],
                ['id' => 'reject_modify', 'title' => 'Change order'],
                ['id' => 'reject_cancel_from_rejection', 'title' => 'Cancel delivery'],
            ];
            $fallback = "We noticed the delivery for order #{$order->id} was refused.\n\n"
                ."Let us know how we can help:\n\n"
                ."1️⃣ Reship order\n2️⃣ Change order\n3️⃣ Cancel delivery";
        } else {
            $body = "لاحظنا أنه تم رفض استلام الطلب #{$order->id} عند التسليم.\n\n"
                .'نحب نتأكد فقط من السبب حتى نقدر نساعدك:';
            $buttons = [
                ['id' => 'reject_reship', 'title' => 'أريد إعادة الشحن'],
                ['id' => 'reject_modify', 'title' => 'أريد تعديل الطلب'],
                ['id' => 'reject_cancel_from_rejection', 'title' => 'أريد إلغاء الطلب'],
            ];
            $fallback = "لاحظنا أنه تم رفض استلام الطلب #{$order->id} عند التسليم.\n\n"
                ."نحب نتأكد فقط من السبب حتى نقدر نساعدك:\n\n"
                ."1️⃣ أريد إعادة الشحن\n2️⃣ أريد تعديل الطلب\n3️⃣ أريد إلغاء الطلب";
        }

        return $this->sendInteractiveWithTextFallback($phone, $body, $buttons, $fallback);
    }

    private function resolveOrder(string $customerPhone, ?string $contextMessageId): ?Order
    {
        if ($contextMessageId) {
            $msg = Message::where('twilio_message_sid', $contextMessageId)->first();
            if ($msg && $msg->order_id) {
                $order = Order::find($msg->order_id);
                if ($order) {
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

        return Order::where(function ($q) use ($digits, $mobilePart) {
            $q->where('customer_phone_1', 'like', "%{$digits}%")
                ->orWhere('customer_phone_1', 'like', "%{$mobilePart}%");
        })
            ->orderByDesc('id')
            ->first();
    }
}
