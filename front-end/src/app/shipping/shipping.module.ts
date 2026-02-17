import { NgModule } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';

import { ShippingRoutingModule } from './shipping-routing.module';
import { AddOrderComponent } from './add-order/add-order.component';
import { SharedModule } from '../shared/shared.module';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MatNativeDateModule } from '@angular/material/core';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { DialogOverviewExampleDialog, ListOrdersComponent } from './list-orders/list-orders.component';
import { OrderSourceComponent } from './order-source/order-source.component';
import { ShippingWayComponent } from './shipping-way/shipping-way.component';
import {MatMenuModule} from '@angular/material/menu';
import {MatIconModule} from '@angular/material/icon';
import { ShippingLinesComponent } from './shipping-lines/shipping-lines.component';
import { ShippingCompanyComponent } from './shipping-company/shipping-company.component';
import {MatDialogModule} from '@angular/material/dialog';
import { ConfirmOrderComponent } from './confirm-order/confirm-order.component';
import { OrderDetailsComponent } from './order-details/order-details.component';
import { TrackingComponent } from './tracking/tracking.component';
import { ShipOrderComponent } from './ship-order/ship-order.component';
import { AutocompleteLibModule } from 'angular-ng-autocomplete';
import { EditOrderComponent } from './edit-order/edit-order.component';

import {MatButtonModule} from '@angular/material/button';
import { MatFormField } from '@angular/material/form-field';
import { CollectOrderComponent } from './collect-order/collect-order.component';
import { MatPaginatorModule } from '@angular/material/paginator';
import { NgxPaginationModule } from 'ngx-pagination';
import { ShippingcompanyDetailsComponent } from './shippingcompany-details/shippingcompany-details.component';
import { CompaniesComponent } from './companies/companies.component';
import { DialogAddCompanyComponent } from './dialog-add-company/dialog-add-company.component';
import { PartCollectComponent } from './part-collect/part-collect.component';
import { CustomerCompanyDetailsComponent } from './customer-company-details/customer-company-details.component';
import { DialogNotificationNoteComponent } from './dialog-notification-note/dialog-notification-note.component';
import { DialogOrderNotificationComponent } from './dialog-order-notification/dialog-order-notification.component';
import { DialogCancelRefuseOrderComponent } from './dialog-cancel-refuse-order/dialog-cancel-refuse-order.component';
import { WhatsAppModule } from '../whatsapp/whatsapp.module';
import { CustomerCompanyBalanceComponent } from './customer-company-balance/customer-company-balance.component';
import { DialogCollectFromCustomerCompanyComponent } from './dialog-collect-from-customer-company/dialog-collect-from-customer-company.component';
import { PrintInvoiceComponent } from './print-invoice/print-invoice.component';
import { StickerComponent } from './sticker/sticker.component';
import { CustomDatePipe } from '../pipes/custom-date.pipe';
import { ShippingCompanyLinesComponent } from './shipping-company-lines/shipping-company-lines.component';
import { FullCalendarModule } from '@fullcalendar/angular';
import { ShippingLineStatementComponent } from './shipping-line-statement/shipping-line-statement.component';
// import { FormsModule } from '@angular/forms';
@NgModule({
  declarations: [
    AddOrderComponent,
    ListOrdersComponent,
    OrderSourceComponent,
    ShippingWayComponent,
    ShippingLinesComponent,
    ShippingCompanyComponent,
    ConfirmOrderComponent,
    OrderDetailsComponent,
    TrackingComponent,
    ShipOrderComponent,
    EditOrderComponent,
    DialogOverviewExampleDialog,
    CollectOrderComponent,
    ShippingcompanyDetailsComponent,
    CompaniesComponent,
    DialogAddCompanyComponent,
    PartCollectComponent,
    CustomerCompanyDetailsComponent,
    DialogNotificationNoteComponent,
    DialogOrderNotificationComponent,
    DialogCancelRefuseOrderComponent,
    CustomerCompanyBalanceComponent,
    DialogCollectFromCustomerCompanyComponent,
    PrintInvoiceComponent,
    StickerComponent,
    ShippingCompanyLinesComponent,
    ShippingLineStatementComponent
  ],
  imports: [
    MatButtonModule,
    MatDialogModule,
    MatMenuModule,
    MatIconModule,
    CommonModule,
    ShippingRoutingModule,
    SharedModule,
    ReactiveFormsModule,
    MatNativeDateModule,
    MatFormFieldModule,
    MatInputModule,
    MatDatepickerModule,
    MatSelectModule,
    FormsModule,
    ReactiveFormsModule,
    AutocompleteLibModule,
    NgxPaginationModule,
    MatPaginatorModule,
    FullCalendarModule,
    WhatsAppModule
  ],
  providers: [
    DatePipe
  ]
})
export class ShippingModule { }
