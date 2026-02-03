import { Component } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BanksService } from 'src/app/financial/services/banks.service';
import { OrderService } from '../services/order.service';
import Swal from 'sweetalert2';
import { ServiceAccountsService } from 'src/app/financial/services/service-accounts.service'; // Import
import { SafeService } from 'src/app/accounting/services/safe.service'; // Import

@Component({
  selector: 'app-collect-order',
  templateUrl: './collect-order.component.html',
  styleUrls: ['./collect-order.component.css']
})
export class CollectOrderComponent {
  line!: string;
  company!: string;
  banks !: any[];
  safes!: any[]; // Safes
  serviceAccounts!: any[]; // Service Accounts
  total_balance !: number;
  order_type !: string;
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
    this.order.getOrderById(id).subscribe((res: any) => {
      this.line = res.order_details.shipping_line.name;
      this.company = res.order_details.shipping_company?.name;
      this.total_balance = res.net_total;
      this.order_type = res.order_type;
    })

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
          this.order.collectOrder(id, body).subscribe((res: any) => {
            console.log(res);
            if (res.message == 'success') {
              this.router.navigate(['/dashboard/shipping/listorders']);
            }
          })
          console.log(true);
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
                this.order.collectOrder(id, body).subscribe((res: any) => {
                  console.log(res);
                  if (res.message == 'success') {
                    this.router.navigate(['/dashboard/shipping/listorders']);
                  }
                })

              }
              return undefined
            }
          })
          console.log(false);
        }

        console.log(body)
        return undefined
      })
    } else {
      const id = this.route.snapshot.params['id'];
      const formData = new FormData();
      formData.append('amount', this.total_balance.toString());
      formData.append('note', form.value.note);
      if (this.collectType === 'تحصيل الكتروني') {
        formData.append('reference_number', this.referenceNumber);
        if (this.selectedFile) {
          formData.append('reference_image', this.selectedFile, this.selectedFile.name);
        }
      } else {
        formData.append('bank_id', form.value.bank);
      }

      this.order.collectOrder(id, formData).subscribe((res: any) => {
        if (res.message == 'success') {
          this.router.navigate(['/dashboard/shipping/listorders']);
        }
      })
    }

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
    console.log(this.selectedFile);
  }


}
