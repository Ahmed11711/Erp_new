import { HttpClient } from '@angular/common/http';
import { Component, Inject, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { WhatsAppService } from '../../services/whatsapp.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-dialog-whatsapp-message',
  templateUrl: './dialog-whatsapp-message.component.html',
  styleUrls: ['./dialog-whatsapp-message.component.css']
})
export class DialogWhatsAppMessageComponent implements OnInit {
  form: FormGroup = new FormGroup({
    'message': new FormControl(null, [Validators.required]),
    'template_id': new FormControl(null),
  });

  templates: any[] = [];
  metaTemplates: any[] = [];
  selectedMetaTemplate: any = null;
  userPhoneNumbers: any[] = [];
  selectedPhoneNumberId: string | null = null;
  loading = false;
  sendingMeta = false;

  constructor(
    public dialogRef: MatDialogRef<DialogWhatsAppMessageComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private whatsappService: WhatsAppService
  ) {}

  ngOnInit() {
    this.loadTemplates();
    this.loadUserPhoneNumbers();
    
    // If order data is provided, set default message
    if (this.data.order && this.data.order.customer_phone_1) {
      const defaultMessage = `مرحباً ${this.data.order.customer_name}، رقم الطلب: ${this.data.order.id}`;
      this.form.patchValue({ message: defaultMessage });
    }
  }

  loadUserPhoneNumbers() {
    this.whatsappService.getUserPhoneNumbers().subscribe({
      next: (res: any) => {
        const data = res?.data ?? res;
        this.userPhoneNumbers = Array.isArray(data) ? data : [];
        if (this.userPhoneNumbers.length === 1) {
          this.selectedPhoneNumberId = this.userPhoneNumbers[0]?.id ?? null;
        }
        this.loadMetaTemplates();
      },
      error: () => {
        this.userPhoneNumbers = [];
        this.loadMetaTemplates();
      }
    });
  }

  /** عرض لغة القالب بالعربية + ترتيب: نفس الاسم يظهر العربي قبل الإنجليزي */
  metaTemplateLanguageLabel(lang: string | undefined): string {
    const l = (lang || '').toLowerCase().split(/[-_]/)[0];
    if (l === 'ar') {
      return 'عربي';
    }
    if (l === 'en') {
      return 'إنجليزي';
    }
    return lang || '';
  }

  /** ترتيب: تجهيز الطلب → تأكيد الطلب → تقييم العميل؛ داخل المجموعة العربي قبل الإنجليزي */
  sortMetaTemplates(list: any[]): any[] {
    const groupOrder = (name: string): number => {
      const n = String(name || '');
      if (n === 'order_confirmation_flow' || n === 'order_flow') {
        return 0;
      }
      if (n === 'confirm_order') {
        return 1;
      }
      if (n === 'client_review') {
        return 2;
      }
      return 9;
    };
    const langPrio = (x: any): number => {
      const p = String(x?.language || '').toLowerCase().split(/[-_]/)[0];
      return p === 'ar' ? 0 : p === 'en' ? 1 : 2;
    };
    return [...list].sort((a, b) => {
      const ga = groupOrder(a?.name);
      const gb = groupOrder(b?.name);
      if (ga !== gb) {
        return ga - gb;
      }
      const na = String(a?.name || '');
      const nb = String(b?.name || '');
      if (na !== nb) {
        return na.localeCompare(nb);
      }
      return langPrio(a) - langPrio(b);
    });
  }

  loadMetaTemplates() {
    this.whatsappService.getMetaTemplates(this.selectedPhoneNumberId ?? undefined).subscribe({
      next: (res: any) => {
        const data = res?.data ?? res;
        const raw = Array.isArray(data) ? data : [];
        this.metaTemplates = this.sortMetaTemplates(raw);
      },
      error: (err) => {
        console.warn('Failed to load Meta templates:', err?.status, err?.error);
        this.metaTemplates = [];
      }
    });
  }

  onPhoneNumberChange() {
    this.loadMetaTemplates();
  }

  loadTemplates() {
    this.whatsappService.getTemplates().subscribe(
      (res: any) => {
        if (res.success) {
          this.templates = res.data;
        }
      },
      (error) => {
        console.error('Error loading templates:', error);
      }
    );
  }

  onCloseClick(): void {
    this.dialogRef.close();
  }

  useTemplate(templateId: number) {
    const template = this.templates.find(t => t.id === templateId);
    if (template) {
      let content = template.content;
      
      // Replace placeholders if order is available
      if (this.data.order) {
        content = content.replace(/{order_id}/g, this.data.order.id);
        content = content.replace(/{customer_name}/g, this.data.order.customer_name);
        content = content.replace(/{order_status}/g, this.data.order.order_status || '');
        content = content.replace(/{net_total}/g, this.data.order.net_total || '');
      }
      
      this.form.patchValue({ message: content });
    }
  }

  sendMetaTemplate() {
    const order = this.data.order || this.data.orders?.[0];
    if (!order?.customer_phone_1 || !this.selectedMetaTemplate?.name) {
      Swal.fire({ icon: 'warning', title: 'تحذير', text: 'اختر قالب ميتا و تأكد من وجود الطلب' });
      return;
    }
    this.sendingMeta = true;
    const keys = this.selectedMetaTemplate.body_param_keys || [];
    const bodyParameters = keys.map((key: string) => String(order[key] ?? ''));
    this.whatsappService.sendMetaTemplateFromOrder({
      order_id: order.id,
      template_name: this.selectedMetaTemplate.name,
      language_code: this.selectedMetaTemplate.language || 'ar',
      body_parameters: bodyParameters,
      phone_number_id: this.selectedPhoneNumberId ?? undefined,
    }).subscribe({
      next: (res: any) => {
        this.sendingMeta = false;
        if (res.success) {
          this.onCloseClick();
          const followupFailed = res.followup_error != null && res.followup_error !== '';
          if (followupFailed) {
            Swal.fire({
              icon: 'warning',
              title: 'تم إرسال القالب',
              text: 'لم يُرسل نص المتابعة تلقائياً (غالباً يلزم أن يكون العميل ضمن نافذة المحادثة 24 ساعة).',
            });
          } else {
            Swal.fire({ icon: 'success', title: 'تم إرسال القالب بنجاح', timer: 1500, showConfirmButton: false });
          }
          if (this.data.refreshData) this.data.refreshData();
        } else {
          Swal.fire({ icon: 'error', title: 'خطأ', text: res.error || 'فشل إرسال القالب' });
        }
      },
      error: (err) => {
        this.sendingMeta = false;
        Swal.fire({ icon: 'error', title: 'خطأ', text: err.error?.error || 'حدث خطأ أثناء الإرسال' });
      }
    });
  }

  submitform() {
    if (this.form.valid && this.form.value.message) {
      this.loading = true;
      
      const order = this.data.order || this.data.orders?.[0];
      
      if (order && order.customer_phone_1) {
        // Send message from order
        this.whatsappService.sendMessageFromOrder({
          order_id: order.id,
          message: this.form.value.message
        }).subscribe(
          (res: any) => {
            this.loading = false;
            if (res.success) {
              this.onCloseClick();
              Swal.fire({
                icon: 'success',
                title: 'تم الإرسال بنجاح',
                timer: 1500,
                showConfirmButton: false,
              });
              if (this.data.refreshData) {
                this.data.refreshData();
              }
            } else {
              Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: res.error || 'فشل إرسال الرسالة',
              });
            }
          },
          (error) => {
            this.loading = false;
            Swal.fire({
              icon: 'error',
              title: 'خطأ',
              text: error.error?.error || 'حدث خطأ أثناء الإرسال',
            });
          }
        );
      } else if (this.data.customer_phone) {
        // Send message to customer phone directly
        this.whatsappService.sendMessage({
          customer_phone: this.data.customer_phone,
          message: this.form.value.message,
          order_id: order?.id
        }).subscribe(
          (res: any) => {
            this.loading = false;
            if (res.success) {
              this.onCloseClick();
              Swal.fire({
                icon: 'success',
                title: 'تم الإرسال بنجاح',
                timer: 1500,
                showConfirmButton: false,
              });
              if (this.data.refreshData) {
                this.data.refreshData();
              }
            } else {
              Swal.fire({
                icon: 'error',
                title: 'خطأ',
                text: res.error || 'فشل إرسال الرسالة',
              });
            }
          },
          (error) => {
            this.loading = false;
            Swal.fire({
              icon: 'error',
              title: 'خطأ',
              text: error.error?.error || 'حدث خطأ أثناء الإرسال',
            });
          }
        );
      } else {
        this.loading = false;
        Swal.fire({
          icon: 'error',
          title: 'خطأ',
          text: 'لم يتم العثور على رقم الهاتف',
        });
      }
    }
  }
}
