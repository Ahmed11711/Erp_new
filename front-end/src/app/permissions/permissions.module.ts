import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { PermissionsRoutingModule } from './permissions-routing.module';
import { ReceiveComponent } from './receive/receive.component';
import { ReceiveCheckComponent } from './receive-check/receive-check.component';
import { ReceiveMoneyComponent } from './receive-money/receive-money.component';
import { SharedModule } from '../shared/shared.module';
import { MatIconModule } from '@angular/material/icon';
import { PriceOffersComponent } from './price-offers/price-offers.component';
import { OfferConditionsComponent } from './offer-conditions/offer-conditions.component';
import { BanksAcountsComponent } from './banks-acounts/banks-acounts.component';
import { DescriptionsComponent } from './descriptions/descriptions.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { OrderCodingComponent } from './order-coding/order-coding.component';
import { OrderDocumentComponent } from './order-document/order-document.component';
import { MatNativeDateModule } from '@angular/material/core';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { AutocompleteLibModule } from 'angular-ng-autocomplete';
import { OrderPrintComponent } from './order-print/order-print.component';
import { PriceOffer1Component } from './price-offer1/price-offer1.component';
import { PriceOffer2Component } from './price-offer2/price-offer2.component';
import { MatPaginatorModule } from '@angular/material/paginator';
import { NgxPaginationModule } from 'ngx-pagination';
import { MatMenuModule } from '@angular/material/menu';
import { Offer1DetailsComponent } from './offer1-details/offer1-details.component';
import { Offer2DetailsComponent } from './offer2-details/offer2-details.component';


@NgModule({
  declarations: [
    ReceiveComponent,
    ReceiveCheckComponent,
    ReceiveMoneyComponent,
    PriceOffersComponent,
    OfferConditionsComponent,
    BanksAcountsComponent,
    DescriptionsComponent,
    OrderCodingComponent,
    OrderDocumentComponent,
    OrderPrintComponent,
    PriceOffer1Component,
    PriceOffer2Component,
    Offer1DetailsComponent,
    Offer2DetailsComponent
  ],
  imports: [
    CommonModule,
    PermissionsRoutingModule,
    SharedModule,
    MatIconModule,
    FormsModule,
    ReactiveFormsModule,
    AutocompleteLibModule,
    MatNativeDateModule,
    MatFormFieldModule,
    MatInputModule,
    NgxPaginationModule,
    MatPaginatorModule,
    MatMenuModule,
  ]
})
export class PermissionsModule { }
