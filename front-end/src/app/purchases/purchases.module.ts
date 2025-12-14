import { NgModule } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';

import { PurchasesRoutingModule } from './purchases-routing.module';
import { AddInvoiceComponent } from './add-invoice/add-invoice.component';
import { FormsModule } from '@angular/forms';
import {AutocompleteLibModule} from 'angular-ng-autocomplete';
import {MatDatepickerModule} from '@angular/material/datepicker';
import {MatInputModule} from '@angular/material/input';
import {MatFormFieldModule} from '@angular/material/form-field';
import {MatNativeDateModule} from '@angular/material/core';
import { ListInvoiceComponent } from './list-invoice/list-invoice.component';
import { MatPaginatorModule } from '@angular/material/paginator';
import { NgxPaginationModule } from 'ngx-pagination';
import { MatButtonModule } from '@angular/material/button';
import { MatDialogModule } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { SharedModule } from '../shared/shared.module';
import { PurchaseDetailsComponent } from './purchase-details/purchase-details.component';
// import { FormsModule } from '@angular/forms';
@NgModule({
  declarations: [
    AddInvoiceComponent,
    ListInvoiceComponent,
    PurchaseDetailsComponent
  ],
  imports: [
    MatNativeDateModule,
    MatFormFieldModule,
    MatInputModule,
    MatDatepickerModule,
    AutocompleteLibModule,
    FormsModule,
    CommonModule,
    PurchasesRoutingModule,
    AutocompleteLibModule,
    NgxPaginationModule,
    MatPaginatorModule,
    MatButtonModule,
    MatDialogModule,
    MatMenuModule,
    MatIconModule,
    SharedModule
  ],
  providers: [DatePipe],
})
export class PurchasesModule { }
