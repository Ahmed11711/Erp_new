# تشخيص مشكلة فلو واتساب

## إذا لم تستلم رسائل بعد الضغط على الأزرار

### 1. التحقق من وصول الـ Webhook (الأهم)

**مهم:** Meta يرسل الطلبات إلى عنوان URL عام. إذا كان التطبيق يعمل على `localhost`، فلن يستطيع Meta الوصول إليه.

- استخدم **ngrok** أو **Cloudflare Tunnel** للتعرض على الإنترنت:
  ```bash
  ngrok http 8000
  ```
- ثم ضع رابط الـ ngrok في إعدادات Meta:
  `https://xxxx.ngrok.io/api/meta/webhook`

### 2. مراجعة السجلات (Logs)

بعد الضغط على الزر، راجع الملف:
```
storage/logs/laravel.log
```

ابحث عن:
- `OrderConfirmationFlow: Button reply received` - هل وصل الرد؟
- `ProcessWhatsAppButtonReplyJob: Starting` - هل بدأ Job المعالجة؟
- `OrderConfirmationFlow: Order found` - هل تم العثور على الطلب؟
- `OrderConfirmationFlow: sendMessage result` - هل تم الإرسال؟
- `ProcessWhatsAppButtonReplyJob: Failed` - هل حدث خطأ؟

### 3. التحقق من رقم الطلب

تأكد أن رقم الطلب في قاعدة البيانات يحتوي على `customer_phone_1` مطابق لرقم واتساب المستخدم (مثل "01012345678" أو "201012345678").

### 4. التحقق من إعدادات Meta

- تأكد من `META_PHONE_NUMBER_ID` و `META_ACCESS_TOKEN` في ملف `.env`
- تأكد أن الـ Access Token صالح ومفعّل

### 5. استخدام Queue (اختياري)

إذا كان `QUEUE_CONNECTION=database` في `.env`، يجب تشغيل:
```bash
php artisan queue:work
```
وإلا لن تُنفّذ المهام. الافتراضي `sync` يعمل بدون تشغيل.
