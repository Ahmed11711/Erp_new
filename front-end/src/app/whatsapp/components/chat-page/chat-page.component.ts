import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { WhatsAppService } from '../../services/whatsapp.service';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import Swal from 'sweetalert2';

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
  loading = false;
  sending = false;

  messageForm: FormGroup = new FormGroup({
    message: new FormControl('', [Validators.required])
  });

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private whatsappService: WhatsAppService
  ) {}

  ngOnInit() {
    this.route.params.subscribe(params => {
      if (params['customerId']) {
        this.customerId = +params['customerId'];
        this.loadChatMessages(this.customerId);
      } else {
        // Check if phone number is provided in query params
        this.route.queryParams.subscribe(queryParams => {
          if (queryParams['phone']) {
            this.findCustomerByPhone(queryParams['phone']);
          } else {
            this.loadCustomers();
          }
        });
      }
    });
  }

  findCustomerByPhone(phone: string) {
    this.loading = true;
    this.whatsappService.getCustomers({ per_page: 1000 }).subscribe(
      (res: any) => {
        this.loading = false;
        if (res.success) {
          const customers = res.data.data || res.data;
          const customer = customers.find((c: any) => c.phone === phone || c.phone === '+' + phone || c.phone.replace('+', '') === phone.replace('+', ''));
          
          if (customer) {
            this.selectCustomer(customer);
          } else {
            // Customer not found, show all customers
            this.customers = customers;
            Swal.fire({
              icon: 'info',
              title: 'تنبيه',
              text: 'لم يتم العثور على العميل، يرجى اختياره من القائمة',
            });
          }
        }
      },
      (error) => {
        this.loading = false;
        console.error('Error loading customers:', error);
        this.loadCustomers();
      }
    );
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

  loadChatMessages(customerId: number) {
    this.loading = true;
    this.whatsappService.getChatMessages(customerId).subscribe(
      (res: any) => {
        this.loading = false;
        if (res.success) {
          this.customer = res.customer;
          this.messages = res.messages;
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
    this.router.navigate(['/dashboard/whatsapp/chat', customer.id]);
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
