import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ReceiveComponent } from './receive/receive.component';
import { ReceiveCheckComponent } from './receive-check/receive-check.component';
import { ReceiveMoneyComponent } from './receive-money/receive-money.component';
import { PriceOffersComponent } from './price-offers/price-offers.component';
import { OfferConditionsComponent } from './offer-conditions/offer-conditions.component';
import { BanksAcountsComponent } from './banks-acounts/banks-acounts.component';
import { DescriptionsComponent } from './descriptions/descriptions.component';
import { OrderCodingComponent } from './order-coding/order-coding.component';
import { OrderDocumentComponent } from './order-document/order-document.component';
import { OrderPrintComponent } from './order-print/order-print.component';
import { PriceOffer1Component } from './price-offer1/price-offer1.component';
import { PriceOffer2Component } from './price-offer2/price-offer2.component';
import { Offer1DetailsComponent } from './offer1-details/offer1-details.component';
import { departmentGuard } from '../guards/department.guard';
import { Offer2DetailsComponent } from './offer2-details/offer2-details.component';

const routes: Routes = [
  {path:"receive" , component:ReceiveComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"receivecheck" , component:ReceiveCheckComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"receivemoney" , component:ReceiveMoneyComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"priceoffer" , component:PriceOffersComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Data Entry','Shipping Management','Customer Service']}
  },
  {path:"offerconditions" , component:OfferConditionsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"banksacounts" , component:BanksAcountsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"descriptions" , component:DescriptionsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"ordercoding" , component:OrderCodingComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"orderdocument" , component:OrderDocumentComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']},
  },
  {path:"orderprint" , component:OrderPrintComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:"priceoffer1" , component:PriceOffer1Component,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Data Entry','Shipping Management','Customer Service']}
  },
  {path:"priceoffer2" , component:PriceOffer2Component,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Data Entry','Shipping Management','Customer Service']}
  },
  {path:"offer1/:id" , component:Offer1DetailsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Data Entry','Shipping Management','Customer Service']}
  },
  {path:"offer2/:id" , component:Offer2DetailsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Data Entry','Shipping Management','Customer Service']}
  },

];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class PermissionsRoutingModule { }
