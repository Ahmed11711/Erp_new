import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { WhatsAppService } from '../../services/whatsapp.service';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import Swal from 'sweetalert2';
import { combineLatest } from 'rxjs';

@Component({
  selector: 'app-chat-page',
  templateUrl: './chat-page.component.html',
  styleUrls: ['./chat-page.component.css']
})
export class ChatPageComponent implements OnInit {
  customerId: number | null = null;
  customer: any = null;
  messages: any[] = [];
  customers: any[] = [];
  /** قوالب النظام (نص حر) — تظهر كشرائح فوق خانة الإدخال */
  templates: any[] = [];
  /** عبارات جاهزة سريعة */
  quickSnippets: { label: string; text: string }[] = [
    { label: 'ترحيب', text: 'مرحباً، شكراً لتواصلك معنا. كيف يمكننا مساعدتك اليوم؟' },
    { label: 'متابعة الطلب', text: 'تمت متابعة طلبك، وسنُعلمك بأي تحديث في أقرب وقت.' },
    { label: 'تأكيد استلام', text: 'تم استلام رسالتك، وسيقوم فريقنا بالرد قريباً.' },
    { label: 'بيانات الشحن', text: 'نحتاج تأكيد عنوان الشحن ورقم هاتفك لإتمام التوصيل.' },
  ];
  loading = false;
  sending = false;

  /** مفاتيح الرسائل الموسّعة (عرض كامل بدون حد ارتفاع) */
  private expandedMessageKeys = new Set<string>();

  messageForm: FormGroup = new FormGroup({
    message: new FormControl('', [Validators.required])
  });

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private whatsappService: WhatsAppService
  ) {}

  ngOnInit() {
    this.loadTemplates();
    combineLatest([this.route.paramMap, this.route.queryParamMap]).subscribe(([params, query]) => {
      const customerIdParam = params.get('customerId');
      if (customerIdParam) {
        this.customerId = +customerIdParam;
        this.loadChatMessages(this.customerId);
        return;
      }
      const phone = query.get('phone');
      if (phone) {
        this.findCustomerByPhone(phone);
      } else {
        this.loadCustomers();
      }
    });
  }

  findCustomerByPhone(phone: string) {
    this.loading = true;
    this.whatsappService.findCustomerByPhone(phone).subscribe({
      next: (res: any) => {
        this.loading = false;
        if (res.success && res.customer) {
          this.selectCustomer(res.customer);
          return;
        }
        this.loadCustomers();
        Swal.fire({
          icon: 'info',
          title: 'تنبيه',
          text: 'لم يتم العثور على العميل، يرجى اختياره من القائمة',
        });
      },
      error: (err) => {
        this.loading = false;
        console.error('Error resolving customer by phone:', err);
        this.loadCustomers();
      },
    });
  }

  loadCustomers() {
    this.loading = true;
    this.whatsappService.getCustomers({ per_page: 50 }).subscribe(
      (res: any) => {
        this.loading = false;
        if (res.success) {
          this.customers = res.data.data || res.data;
        }
      },
      (error) => {
        this.loading = false;
        console.error('Error loading customers:', error);
      }
    );
  }

  loadTemplates() {
    this.whatsappService.getTemplates().subscribe({
      next: (res: any) => {
        if (res.success && Array.isArray(res.data)) {
          this.templates = res.data;
        }
      },
      error: () => {
        this.templates = [];
      },
    });
  }

  /** يملأ خانة الرسالة دون إرسال — يمكن للمستخدم التعديل قبل الإرسال */
  applySuggestion(text: string) {
    if (!text) {
      return;
    }
    this.messageForm.patchValue({ message: text });
    this.messageForm.get('message')?.markAsTouched();
  }

  /** عرض أوضح لرسائل الأزرار القادمة من واتساب */
  formatBubbleContent(content: string | null | undefined): string {
    if (content == null || content === '') {
      return '';
    }
    const t = content.trim();
    if (t === '[Button Message]' || t === '[Interactive Message]') {
      return '🔘 رد تفاعلي (زر واتساب)';
    }
    return content;
  }

  private messageRowKey(message: any, index: number): string {
    if (message?.id != null) {
      return `id:${message.id}`;
    }
    return `idx:${index}`;
  }

  /** رسالة طويلة: زر اقرأ المزيد + تمرير داخلي عند الطي */
  shouldShowReadMoreToggle(message: any): boolean {
    const text = this.formatBubbleContent(message?.content) || '';
    return text.length > 220;
  }

  isMessageBodyExpanded(message: any, index: number): boolean {
    return this.expandedMessageKeys.has(this.messageRowKey(message, index));
  }

  toggleMessageBodyExpand(message: any, index: number): void {
    const key = this.messageRowKey(message, index);
    if (this.expandedMessageKeys.has(key)) {
      this.expandedMessageKeys.delete(key);
    } else {
      this.expandedMessageKeys.add(key);
    }
  }

  loadChatMessages(customerId: number) {
    this.loading = true;
    this.whatsappService.getChatMessages(customerId).subscribe(
      (res: any) => {
        this.loading = false;
        if (res.success) {
          this.customer = res.customer;
          this.messages = res.messages;
          this.expandedMessageKeys.clear();
          // Scroll to bottom
          setTimeout(() => {
            this.scrollToBottom();
          }, 100);
        }
      },
      (error) => {
        this.loading = false;
        console.error('Error loading messages:', error);
      }
    );
  }

  selectCustomer(customer: any) {
    this.customerId = customer.id;
    this.router.navigate(['/dashboard/whatsapp/chat', customer.id], { replaceUrl: true });
    this.loadChatMessages(customer.id);
  }

  sendMessage() {
    if (this.messageForm.valid && this.customerId) {
      this.sending = true;
      const message = this.messageForm.value.message;

      this.whatsappService.sendMessage({
        customer_phone: this.customer.phone,
        message: message
      }).subscribe(
        (res: any) => {
          this.sending = false;
          if (res.success) {
            this.messageForm.reset();
            // Reload messages
            this.loadChatMessages(this.customerId!);
          } else {
            Swal.fire({
              icon: 'error',
              title: 'خطأ',
              text: res.error || 'فشل إرسال الرسالة',
            });
          }
        },
        (error) => {
          this.sending = false;
          Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: error.error?.error || 'حدث خطأ أثناء الإرسال',
          });
        }
      );
    }
  }

  scrollToBottom() {
    const chatContainer = document.getElementById('chat-messages');
    if (chatContainer) {
      chatContainer.scrollTop = chatContainer.scrollHeight;
    }
  }

  formatDate(date: string): string {
    const d = new Date(date);
    const now = new Date();
    const diff = now.getTime() - d.getTime();
    const diffDays = Math.floor(diff / (1000 * 60 * 60 * 24));

    if (diffDays === 0) {
      return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
    } else if (diffDays === 1) {
      return 'أمس ' + d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
    } else if (diffDays < 7) {
      return d.toLocaleDateString('ar-EG', { weekday: 'short' }) + ' ' + d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
    } else {
      return d.toLocaleDateString('ar-EG', { day: 'numeric', month: 'short' }) + ' ' + d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
    }
  }

  openWhatsApp(customer: any) {
    const phone = customer.phone.replace('+', '');
    const url = `https://wa.me/${phone}`;
    window.open(url, '_blank');
  }
}
