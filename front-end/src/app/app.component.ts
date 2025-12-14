import { ChangeDetectionStrategy, ChangeDetectorRef, Component, NgZone } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { filter } from 'rxjs';
import { LoadingService } from './loading.service';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush

})
export class AppComponent {
  title = 'front-end';

  backBtn:any[]=[
    '/dashboard/shipping/orderdetails',
    '/dashboard/shipping/shippingcompanydetails',
    '/dashboard/purchases/invoice',
    '/dashboard/suppliers/supplier_details',
    '/dashboard/financial/expense_details',
    '/dashboard/financial/bank_details',
    '/dashboard/shipping/confirmOrder',
    '/dashboard/shipping/shipOrder',
    '/dashboard/warehouse/cat_details',
    '/dashboard/shipping/editorder',
    '/dashboard/shipping/collectorder',
    '/dashboard/shipping/collectpart',
    '/dashboard/shipping/companydetails',
    '/dashboard/shipping/companybalance',
    '/dashboard/notification',
    '/dashboard/notification/recieved',
    '/dashboard/notification/sent',
    '/dashboard/notification/moves',
    '/dashboard/hr/employee/edit',
    '/dashboard/hr/employee/details',
    '/dashboard/categories/edit_category',
    '/dashboard/permissions/offer1',
    '/dashboard/permissions/offer2',
    '/dashboard/categoriesreports',
    '/dashboard/hr/workinghoursdetails',
    '/dashboard/approvals',
  ];

  constructor(private route:Router, public loadingService:LoadingService , private cdr: ChangeDetectorRef ,private ngZone: NgZone) {}

  ngOnInit() {

    this.route.events
    .pipe(filter(event => event instanceof NavigationEnd))
    .subscribe(() => {

    const back = document.getElementById('back');
    const currentUrl = this.route.url;
    const lastSlashIndex = currentUrl.lastIndexOf('/');
    let checkUrl;

    const modifiedUrl = currentUrl.substring(0, lastSlashIndex);

    if (Number(currentUrl.slice(lastSlashIndex+1))) {
      checkUrl = modifiedUrl;
    }
    else{
      checkUrl = currentUrl;
    }

    if (this.backBtn.includes(checkUrl)) {
      back?.classList.remove('d-none');
    } else {
      back?.classList.add('d-none');
    }



      setTimeout(()=>{
        this.disableAutocompleteForInputs();
      },0)
    });

    setTimeout(()=>{
      this.disableAutocompleteForInputs();
    },0);

  }

  disableAutocompleteForInputs() {
    const inputElements = document.querySelectorAll('input');
    inputElements.forEach((input: HTMLInputElement) => {
      input.setAttribute('autocomplete', 'new-password');
    });
  }

  backPage(){
    window.history.back();
  }

  ngAfterViewInit() {
    this.route.events
      .pipe(filter(event => event instanceof NavigationEnd))
      .subscribe(() => {
        this.ngZone.run(() => {
          this.cdr.detectChanges();
        });
      });
  }

}
