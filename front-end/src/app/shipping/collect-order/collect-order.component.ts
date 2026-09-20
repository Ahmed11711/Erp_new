import { Component } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BanksService } from 'src/app/financial/services/banks.service';
import { OrderService } from '../services/order.service';
import Swal from 'sweetalert2';
import { ServiceAccountsService } from 'src/app/financial/services/service-accounts.service'; // Import
import { SafeService } from 'src/app/accounting/services/safe.service'; // Import
import {
  allowsManualOrderCollection,
  collectAmountBreakdownLabel,
  manualCollectionBlockedMessage,
  orderCollectAmount,
} from '../utils/order-collect-eligibility.utils';

@Component({
  selector: 'app-collect-order',
  templateUrl: './collect-order.component.html',
  styleUrls: ['./collect-order.component.css']
})
export class CollectOrderComponent {
  line = '—';
  company = '—';
  banks: any[] = [];
  safes: any[] = [];
  serviceAccounts: any[] = [];
  total_balance: number = 0;
  collectBreakdownLabel: string | null = null;
  order_type = '';
  isOfferOrder = false;
  collectType: string = 'تحصيل في الخزينة';
  paymentType: string = 'bank'; // Default to bank
  imgtext: string = "صورة الايصال";
  referenceNumber: string = '';
  fileopend: boolean = false;

  constructor(private order: OrderService, private route: ActivatedRoute, private bank: BanksService,
    private router: Router,
    private safeService: SafeService, // Inject
    private serviceAccountsService: ServiceAccountsService // Inject
  ) { }

  ngOnInit(): void {
    const id = this.route.snapshot.params['id'];
    this.order.getOrderById(id).subscribe({
      next: (res: any) => {
        const details = res?.order_details;
        this.line = details?.shipping_line?.name ?? '—';
        this.company = details?.shipping_company?.name ?? 'بدون شركة شحن — التحصيل من العميل';
        this.isOfferOrder = !!(res?.offer_id || res?.offer_debt_posted);
        if (res?.order_status === 'تم التحصيل') {
          Swal.fire({ icon: 'info', title: 'تم تحصيل هذا الطلب مسبقاً' }).then(() =>
            this.router.navigate([this.afterCollectPath()])
          );
          return;
        }

        if (!allowsManualOrderCollection(res)) {
          Swal.fire({
            icon: 'info',
            title: 'لا يمكن تحصيل هذا الطلب من هنا',
            text: manualCollectionBlockedMessage(res),
          }).then(() => this.router.navigate([this.afterCollectPath()]));
          return;
        }

        this.total_balance = orderCollectAmount(res);
        this.collectBreakdownLabel = collectAmountBreakdownLabel(res);
        this.order_type = res?.order_type ?? '';
      },
      error: () => {
        Swal.fire({ icon: 'error', title: 'تعذر تحميل بيانات الطلب' });
      },
    });

    this.bank.bankSelect().subscribe((res: any) => {
      this.banks = res;
    });
    this.safeService.getAll().subscribe((res: any) => {
      this.safes = res.data || res;
    });
    this.serviceAccountsService.index().subscribe((res: any) => {
      this.serviceAccounts = res;
    });
  }


  collectOrder(form: any) {
    if (!this.canSubmitCollection(form)) {
      return;
    }

    if (this.order_type == 'طلب مرتجع' || this.order_type == 'طلب استبدال') {
      Swal.fire({
        title: 'هل تم استلام المنتج',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'نعم',
        cancelButtonText: 'لا',
      }).then((result) => {

        const id = this.route.snapshot.params['id'];
        let body = { amount: this.total_balance, bank_id: form.value.bank, note: form.value.note }
        if (result.isConfirmed) {
          body['receivedOrder'] = true;
          this.order.collectOrder(id, body).subscribe({
            next: (res: any) => {
              if (res.message == 'success') {
                this.router.navigate([this.afterCollectPath()]);
              }
            },
            error: (err) => {
              const msg = err?.error?.message || 'تعذر إتمام التحصيل';
              Swal.fire({ icon: 'warning', title: msg });
            },
          });
        } else if (result.isDismissed) {
          body['receivedOrder'] = false;
          Swal.fire({
            icon: 'info',
            input: 'text',
            inputPlaceholder: 'السبب',
            showCancelButton: true,
            inputValidator: (value) => {
              if (!value) {
                return 'يجب ادخال ملاحظة'
              }
              if (value !== '') {
                body['reason'] = value;
                this.order.collectOrder(id, body).subscribe({
                  next: (res: any) => {
                    if (res.message == 'success') {
                      this.router.navigate([this.afterCollectPath()]);
                    }
                  },
                  error: (err) => {
                    const msg = err?.error?.message || 'تعذر إتمام التحصيل';
                    Swal.fire({ icon: 'warning', title: msg });
                  },
                });

              }
              return undefined
            }
          })
        }

        return undefined
      })
    } else {
      const id = this.route.snapshot.params['id'];
      const formData = new FormData();
      formData.append('amount', String(this.total_balance ?? 0));
      formData.append('note', form.value.note ?? '');
      if (this.collectType === 'تحصيل الكتروني') {
        formData.append('reference_number', this.referenceNumber);
        if (this.selectedFile) {
          formData.append('reference_image', this.selectedFile, this.selectedFile.name);
        }
      }
      const paymentType = form.value.paymentType || 'bank';
      formData.append('payment_type', paymentType);
      if (paymentType === 'safe') {
        formData.append('safe_id', form.value.safe);
      } else if (paymentType === 'service_account') {
        formData.append('service_account_id', form.value.service_account);
      } else {
        formData.append('bank_id', form.value.bank);
      }

      this.order.collectOrder(id, formData).subscribe({
        next: (res: any) => {
          if (res.message == 'success') {
            this.router.navigate([this.afterCollectPath()]);
          }
        },
        error: (err) => {
          const msg = err?.error?.message || 'تعذر إتمام التحصيل';
          Swal.fire({ icon: 'warning', title: msg });
        },
      });
    }

  }

  private afterCollectPath(): string {
    return this.isOfferOrder
      ? '/dashboard/shipping/offer-orders'
      : '/dashboard/shipping/listorders';
  }

  openFileInput() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
      fileInput.click();
      this.fileopend = true;
    }
  }
  selectedFile: any;
  onFileChanged(event: any) {
    this.selectedFile = event.target.files[0];
    this.imgtext = this.selectedFile?.name || 'No image selected';
  }

  private canSubmitCollection(form: any): boolean {
    const amount = Number(this.total_balance ?? 0);
    if (!Number.isFinite(amount) || amount < 0) {
      Swal.fire({ icon: 'warning', title: 'مبلغ التحصيل غير صالح' });
      return false;
    }

    if (this.collectType !== 'تحصيل في الخزينة') {
      return true;
    }

    const paymentType = form?.value?.paymentType || this.paymentType || 'bank';
    if (paymentType === 'safe' && !form?.value?.safe) {
      Swal.fire({ icon: 'warning', title: 'اختر الخزينة' });
      return false;
    }
    if (paymentType === 'service_account' && !form?.value?.service_account) {
      Swal.fire({ icon: 'warning', title: 'اختر الحساب الخدمي' });
      return false;
    }
    if (paymentType === 'bank' && !form?.value?.bank) {
      Swal.fire({ icon: 'warning', title: 'اختر البنك أو الخزينة' });
      return false;
    }

    return true;
  }
}
