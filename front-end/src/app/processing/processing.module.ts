import { NgModule } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { AutocompleteLibModule } from 'angular-ng-autocomplete';
import { MatDialogModule } from '@angular/material/dialog';
import { ProcessingRoutingModule } from './processing-routing.module';
import { SharedModule } from '../shared/shared.module';
import { SuppliersModule } from '../suppliers/suppliers.module';
import { ProcessingDashboardComponent } from './processing-dashboard/processing-dashboard.component';
import { ProcessingOrdersComponent } from './processing-orders/processing-orders.component';
import { ProcessingOrderDetailComponent } from './processing-order-detail/processing-order-detail.component';

@NgModule({
  declarations: [
    ProcessingDashboardComponent,
    ProcessingOrdersComponent,
    ProcessingOrderDetailComponent,
  ],
  imports: [
    CommonModule,
    FormsModule,
    SharedModule,
    AutocompleteLibModule,
    MatDialogModule,
    SuppliersModule,
    ProcessingRoutingModule,
  ],
})
export class ProcessingModule {}
