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
  loading = false;

  constructor(
    public dialogRef: MatDialogRef<DialogWhatsAppMessageComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private whatsappService: WhatsAppService
  ) {}

  ngOnInit() {
    this.loadTemplates();
    
    // If order data is provided, set default message
    if (this.data.order && this.data.order.customer_phone_1) {
      const defaultMessage = `مرحباً ${this.data.order.customer_name}، رقم الطلب: ${this.data.order.id}`;
      this.form.patchValue({ message: defaultMessage });
    }
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
