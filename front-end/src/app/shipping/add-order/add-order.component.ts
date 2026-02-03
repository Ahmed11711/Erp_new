import { DatePipe } from '@angular/common';
import { ChangeDetectorRef, Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { OrderSourceService } from '../services/order-source.service';
import { ShippingWayService } from '../services/shipping-way.service';
import { OrderService } from '../services/order.service';
import { BanksService } from 'src/app/financial/services/banks.service';
import { HttpClient } from '@angular/common/http';
import { MatSnackBar } from '@angular/material/snack-bar';
import { SnackBarComponent } from 'src/app/shared/snack-bar/snack-bar.component';
import { Router } from '@angular/router';
import { CompaniesService } from '../services/companies.service';
import { MatDialog } from '@angular/material/dialog';
import { DialogAddCompanyComponent } from '../dialog-add-company/dialog-add-company.component';
import Swal from 'sweetalert2';
import { environment } from 'src/env/env';
import { AuthService } from 'src/app/auth/auth.service';

import { ServiceAccountsService } from 'src/app/financial/services/service-accounts.service';
import { SafeService } from 'src/app/accounting/services/safe.service'; // Import


@Component({
  selector: 'app-add-order',
  templateUrl: './add-order.component.html',
  styleUrls: ['./add-order.component.css']
})
export class AddOrderComponent implements OnInit {
  user!: string;
  shippingWays: any[] = [];
  orderSources: any[] = [];
  errormessage: boolean = false;
  errorText: string = '';
  products: any[] = [];
  numbers: any[] = [];
  banksData: any[] = [];
  safesData: any[] = [];
  serviceAccountsData: any[] = []; // Service Accounts Data
  imgUrl!: string;
  specialStatus: boolean = false;

  constructor(private orderSource: OrderSourceService,
    private shippingWay: ShippingWayService,
    private orderService: OrderService,
    private datePipe: DatePipe,
    private bankService: BanksService,
    private safeService: SafeService,
    private serviceAccountsService: ServiceAccountsService, // Injected Service
    private http: HttpClient,
    private _snackBar: MatSnackBar,
    private router: Router,
    private companyService: CompaniesService,
    private cdr: ChangeDetectorRef,
    private dialog: MatDialog,
    private authService: AuthService
  ) {
    this.imgUrl = environment.imgUrl;
  }

  ngOnInit() {
    this.user = this.authService.getUser();
    this.orderSource.data().subscribe(reuslt => this.orderSources = reuslt);
    this.orderService.getNumbers().subscribe((reuslt: any) => this.numbers = reuslt);
    this.shippingWay.data().subscribe(result => this.shippingWays = result);
    this.orderService.getProducts().subscribe((result: any) => this.products = result);
    this.bankService.bankSelect().subscribe((result: any) => this.banksData = result);
    this.serviceAccountsService.index().subscribe((result: any) => this.serviceAccountsData = result); // Fetch Service Accounts

    this.http.get('assets/egypt/governorates.json').subscribe((data: any) => this.location = data);
    this.http.get('assets/egypt/cities.json').subscribe((data: any) => {
      this.cities = data.filter((elem: any) => elem.governorate_id == 1);
    });
    this.safeService.getAll().subscribe((result: any) => this.safesData = result.data || result);

    this.form.patchValue({
      customer_type: 'افراد',
      order_type: 'جديد',
      governorate: 'المحافظة',
      order_source_id: 'مصدر الطلب',
      shipping_method_id: 'طريقة الشحن',
      city: 'المدينة'
    });
  }

  customerTypeVal: string = 'افراد';
  customerType(e: any) {
    this.customerTypeVal = e.target.value;
    if (this.customerTypeVal === 'شركة') {
      this.vatPercent = 14;
      this.form.get('customer_name')?.reset();
      this.form.get('customer_phone_1')?.reset();
      this.form.get('customer_phone_2')?.reset();
      this.form.get('address')?.reset();
      this.form.patchValue({
        'governorate': 'المحافظة',
        'city': 'المدينة'
      });
      this.getCompanies();
    } else {
      this.vatPercent = 0;
      this.changedVat = false;
      this.selectedCompany = false;
      this.form.get('customer_name')?.reset();
      this.form.get('customer_phone_1')?.reset();
      this.form.get('customer_phone_2')?.reset();
      this.form.get('address')?.reset();
      this.form.patchValue({
        'governorate': 'المحافظة',
        'city': 'المدينة'
      });
      this.governName = false;
    }
    this.companyID = 0;
    this.cdr.detectChanges();
    this.calc(arguments);
  }

  //govern and city
  location: any[] = [];
  cities: any[] = [];
  governName: boolean = false;

  govern(event) {
    if (event.target.value == "القاهرة") {
      this.governName = true;
    } else {
      this.governName = false;
    }
  }
  //end

  dateSelected = false;
  date: any;
  deliveryDate: any;

  myFilter = (d: Date | null): boolean => {
    const today = new Date();
    const selectedDate = d || today;
    const timeDifference = Math.floor((today.getTime() - selectedDate.getTime()) / (1000 * 60 * 60 * 24));
    return timeDifference >= 0 && timeDifference <= 4;
  };

  deliveryDateFilter = (d: Date | null): boolean => {
    const today = new Date();
    const selectedDate = d || today;
    const timeDifference = Math.floor((today.getTime() - selectedDate.getTime()) / (1000 * 60 * 60 * 24));
    return timeDifference <= 0;
  };

  OnDateChange(event) {
    const inputDate = new Date(event);
    this.date = this.datePipe.transform(inputDate, 'yyyy-M-d');
    this.dateSelected = true;
  }

  OnDeliveryDateChange(event) {
    const inputDate = new Date(event);
    this.deliveryDate = this.datePipe.transform(inputDate, 'yyyy-M-d');
  }

  imgtext: string = "صورة الايصال"
  fileopend: boolean = false;

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


  form: FormGroup = new FormGroup({
    'bank': new FormControl(null),
    'productprice': new FormControl(null),
    'productquantity': new FormControl(null),
    'customer_name': new FormControl(null, [Validators.required]),
    'customer_type': new FormControl(null, [Validators.required]),
    'customer_phone_1': new FormControl(null, [Validators.required, Validators.pattern('^01\\d{9}$')]),
    'customer_phone_2': new FormControl(null, [Validators.pattern('^01\\d{9}$')]),
    'tel': new FormControl(null),
    'governorate': new FormControl(null, [Validators.required]),
    'city': new FormControl(null),
    'address': new FormControl(null, [Validators.required]),
    'order_date': new FormControl(null, [Validators.required]),
    'delivery_date': new FormControl(null),
    'shipping_method_id': new FormControl(null, [Validators.required]),
    'order_source_id': new FormControl(null, [Validators.required]),
    'order_notes': new FormControl(null),
    'order_image': new FormControl(null),
    'order_type': new FormControl(null, [Validators.required]),
    'total_invoice': new FormControl(null),
    'shipping_cost': new FormControl(null),
    'prepaid_amount': new FormControl(null),
    'discount': new FormControl(null),
    'net_total': new FormControl(null),
    'name': new FormControl(null),
    'vat': new FormControl(null),
    'maintenance_cost': new FormControl(null),
    'special_details': new FormControl(null),
    'payment_type': new FormControl('bank'), // Default to bank
    'safe_id': new FormControl(null),
    'service_account_id': new FormControl(null),
  })

  async submitform() {

    if (this.form.valid) {
      let data = this.form.value;
      data.shipping_cost = this.shipping_cost ? this.shipping_cost : 0;
      data.total_invoice = this.totalInvoice;
      data.prepaid_amount = this.prepaid_amount || 0;
      data.discount = this.discount || 0;
      data.net_total = this.net_total;
      data.order_date = this.date;
      data.delivery_date = this.deliveryDate || null;
      data.vat = this.vat;
      data.companyID = this.companyID;
      const tax_authority = this.totalSum;


      const formData = new FormData();
      formData.append('customer_name', data.customer_name);
      formData.append('payment_type', data.payment_type);
      if (data.payment_type === 'bank') {
        formData.append('bank', data.bank);
      } else if (data.payment_type === 'safe') {
        formData.append('safe_id', data.safe_id);
      } else if (data.payment_type === 'service_account') {
        formData.append('service_account_id', data.service_account_id);
      }
      formData.append('customer_type', data.customer_type);
      formData.append('customer_phone_1', data.customer_phone_1);
      formData.append('customer_phone_2', data.customer_phone_2);
      formData.append('tel', data.tel);
      formData.append('governorate', data.governorate);
      if (data.city != "المدينة") {
        formData.append('city', data.city);
      }
      if (data.order_type === "طلب صيانة") {
        formData.append('maintenance_cost', data.maintenance_cost);
      }
      formData.append('address', data.address);
      formData.append('order_date', data.order_date);
      formData.append('delivery_date', data.delivery_date);
      formData.append('vat', data.vat);
      formData.append('shipping_method_id', data.shipping_method_id);
      formData.append('order_source_id', data.order_source_id);
      formData.append('order_notes', data.order_notes);
      formData.append('order_type', data.order_type);
      formData.append('shipping_cost', data.shipping_cost);
      formData.append('total_invoice', data.total_invoice);
      formData.append('prepaid_amount', data.prepaid_amount);
      formData.append('discount', data.discount);
      formData.append('net_total', data.net_total);
      // formData.append('order_details', JSON.stringify(this.order_details));
      const salesTotal = this.order_details.reduce((acc, item) => acc + Number(item.total), 0);

      // إضافة التفاصيل والمجموع للفورم داتا
      formData.append('order_details', JSON.stringify(this.order_details));
      formData.append('Sales', salesTotal.toString());
      formData.append('tax_authority', tax_authority.toString());

      if (this.selectedFile) {
        formData.append('order_image', this.selectedFile, this.selectedFile.name);
      }

      if (this.companyID !== 0) {
        formData.append('company_id', data.companyID);
      }

      if (this.user === 'Admin' && data.customer_type === 'شركة') {
        const result = await Swal.fire({
          title: 'هل الطلب خاص بالأدمن فقط؟',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'نعم',
          cancelButtonText: 'لا',
        });

        if (result.dismiss === Swal.DismissReason.backdrop) {
          return;
        }

        if (result.isConfirmed) {
          formData.append('private_order', '1');
        }
      }


      if (data.order_type == 'طلب صيانة') {
        Swal.fire({
          title: ' سبب الصيانة',
          input: 'text',
          showCancelButton: true,
          inputValidator: (value) => {
            if (!value) {
              return 'يجب ادخال قيمة'
            }
            if (value !== '') {
              formData.append('maintenReason', value);

              this.orderService.postOrder(formData).subscribe(result => {
                console.log(result);

                this.router.navigate(['/dashboard/shipping/listorders']);
              },
                (error) => {
                  this.errormessage = true
                  console.log(error);
                  this.errorText = error;
                });
            }
            return undefined
          }
        })
      } else {
        this.orderService.postOrder(formData).subscribe(result => {
          console.log(result);

          this.router.navigate(['/dashboard/shipping/listorders']);
        },
          (error) => {
            this.errormessage = true
            console.error(error);

            if (error.error && error.error.errors) {
              // Laravel Validation Errors
              const errors = error.error.errors;
              let messages: string[] = [];
              for (const key in errors) {
                if (errors.hasOwnProperty(key)) {
                  messages.push(`${key}: ${errors[key].join(', ')}`);
                }
              }
              this.errorText = messages.join(' | ');
            } else if (error.error && error.error.message) {
              // Generic API Error
              this.errorText = error.error.message;
            } else if (error.message) {
              // HTTP Error
              this.errorText = error.message;
            } else {
              // Fallback
              this.errorText = "حدث خطأ غير معروف";
            }
          });
      }

    }
  }


  getLocation() {
    fetch('http://api.ipify.org/?format=json').then(result => result.json()).then(data => {
      console.log(data.ip);
    })
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(this.showPosition, this.showError);
    } else {
      console.log("Geolocation is not supported by this browser.");
    }
  }

  showPosition(position) {
    console.log(`https://www.google.com/maps?q=${position.coords.latitude},${position.coords.longitude}`);

  }

  showError(error) {
    switch (error.code) {
      case error.PERMISSION_DENIED:
        console.log("User denied the request for Geolocation.");
        break;
      case error.POSITION_UNAVAILABLE:
        console.log("Location information is unavailable.");
        break;
      case error.TIMEOUT:
        console.log("The request to get user location timed out.");
        break;
      case error.UNKNOWN_ERROR:
        console.log("An unknown error occurred.");
        break;
    }
  }

  showmsg() {
    const snackBarRef = this._snackBar.openFromComponent(SnackBarComponent, {
      duration: 2000,
    });
    snackBarRef.instance.message = 'تم اضافة الصنف بنجاح  ';
  }

  special() {
    this.specialStatus = !this.specialStatus;
    this.form.patchValue({
      'special_details': null
    });
  }

  orderStatus: string = 'جديد';
  status(event: any) {
    this.orderStatus = event.target.value;
    this.order_details = [];
    this.productsPrice = 0;
    this.prepaid_amount = 0;
    this.discount = 0;
    this.maintenanceAmount = 0;
    this.shipping_cost = 0;
    this.calc(arguments);
  }

  // -----------------------------fill table
  category_name!: string;
  category_quantity!: number;
  category_price!: number;
  category_image!: string;
  category_id!: string;

  order_details: any[] = [];

  catword = 'category_name';
  productChange(event) {
    this.category_price = event.category_price
    this.category_image = event.category_image
    this.category_name = event.category_name
    this.category_id = event.id
  }

  catword2 = 'name';
  companies: any[] = [];
  selectedCompany: boolean = false;
  companyID: number = 0;
  getCompanies() {
    this.companyService.data().subscribe(result => this.companies = result);
  }
  companyChange(event) {
    this.selectedCompany = true;
    this.companyID = event.id;
    if (event.city) {
      this.governName = true;
      this.form.patchValue({
        'city': event.city,
      })
    } else {
      this.governName = false;
      this.form.patchValue({
        'city': 'المدينة',
      })
    }
    if (event.phone2 !== 'null') {
      this.form.patchValue({
        'customer_phone_2': event.phone2,
      })
    }
    if (event.tel !== 'null') {
      this.form.patchValue({
        'tel': event.tel,
      })
    }
    this.form.patchValue({
      'customer_name': event.name,
      'customer_phone_1': event.phone1,
      'governorate': event.governorate,
      'address': event.address,
    })
  }

  resetcompany() {
    this.selectedCompany = false;
    this.companyID = 0;

    this.form.get('customer_name')?.reset();
    this.form.get('customer_phone_1')?.reset();
    this.form.get('customer_phone_2')?.reset();
    this.form.get('address')?.reset();
    this.form.patchValue({
      'governorate': 'المحافظة',
      'city': 'المدينة'
    });
    this.governName = false;
  }

  catword3 = 'customer_phone_1';
  filteredNumbers: any = [];
  editPhone(phone) {
    this.form.patchValue({
      'customer_phone_1': phone
    });
    if (phone.length > 4) {
      this.filteredNumbers = this.numbers.filter(item =>
        item.customer_phone_1.includes(phone)
      );
    }
  }
  phoneChange(event) {
    console.log(event);

    if (event.governorate == 'القاهرة') {
      this.governName = true;
      this.form.patchValue({
        'city': event.city,
      })
    } else {
      this.governName = false;
      this.form.patchValue({
        'city': 'المدينة',
      })
    }
    this.form.patchValue({
      'customer_name': event?.customer_name,
      'customer_phone_1': event?.customer_phone_1.replace(/\s+/g, ''),
      'governorate': event?.governorate,
      'address': event?.address,
    });
    if (event.customer_phone_2 !== "null") {
      this.form.patchValue({
        'customer_phone_2': event.customer_phone_2,
      });
    }
    if (event.tel !== "null") {
      this.form.patchValue({
        'tel': event?.tel,
      });
    }
  }

  resetphone() {
    this.filteredNumbers = [];
    this.form.get('customer_name')?.reset();
    this.form.get('customer_phone_1')?.reset();
    this.form.get('customer_phone_2')?.reset();
    this.form.get('tel')?.reset();
    this.form.get('address')?.reset();
    this.form.patchValue({
      'governorate': 'المحافظة',
      'city': 'المدينة'
    });
  }

  openDialog(): void {
    const dialogRef = this.dialog.open(DialogAddCompanyComponent, {
      width: '25%', data: { refreshData: () => this.getCompanies() },
    });

    dialogRef.afterClosed().subscribe(result => {
      console.log('The dialog was closed');
    });
  }

  changeProductPrice(e: any) {
    this.category_price = e.target.value;
  }

  productsPrice: number = 0;
  addproduct(type: string) {
    if (this.category_name && this.category_quantity && this.category_price) {
      let productQuantity = 0;
      let productTotal = 0;
      if (type == 'مرتجع') {
        productQuantity = -this.category_quantity;
        productTotal = this.category_price * this.category_quantity * -1;
      } else if (type == 'طلب صيانة') {
        productQuantity = this.category_quantity;
        productTotal = 0;
      }
      else {
        productQuantity = this.category_quantity;
        productTotal = this.category_price * this.category_quantity;
      }
      const product = {
        category_id: this.category_id,
        category_name: this.category_name,
        quantity: productQuantity,
        price: this.category_price,
        imgsrc: this.category_image,
        total: productTotal,
        special_details: this.form.value.special_details
      }
      this.order_details.push(product);
      this.productsPrice = 0;
      this.order_details.forEach(elm => {
        this.productsPrice += elm.total
      })
      this.calc(arguments);
    }
  }

  totalInvoice: number = 0;
  net_total: number = 0;
  vat: number = 0;
  vatPercent: number = 0;
  shipping_cost: number = 0;
  prepaid_amount: number = 0;
  maintenanceAmount: number = 0;
  discount: number = 0;
  changedVat: boolean = false;


  calc(e: any) {
    // this.totalInvoice = (this.productsPrice + this.shipping_cost) * (1+this.vatPercent/100);
    this.totalInvoice = (this.productsPrice + this.shipping_cost + this.maintenanceAmount);
    if (e?.target?.id == 'vat') {
      this.changedVat = true;
    }
    if (!this.changedVat) {
      this.vat = this.totalInvoice * this.vatPercent / 100;
    }
    this.totalInvoice = this.totalInvoice + this.vat;
    this.net_total = this.totalInvoice - this.prepaid_amount - this.discount;
  }

  get totalSum(): number {
    if (!this.order_details) return 0;

    const sum = this.order_details.reduce((acc, product) => acc + product.total, 0);

    const afterDiscount = sum - (this.discount || 0);

    return afterDiscount * 0.01;
  }




  removeProduct(i: number) {
    this.order_details.splice(i, 1);
    this.productsPrice = 0;
    this.order_details.forEach(elm => {
      this.productsPrice += elm.total
    })
    this.calc(arguments);
  }
  //-------------------------------------------------end table


  resetInp() {
    this.form.get('productprice')?.reset();
  }

}
