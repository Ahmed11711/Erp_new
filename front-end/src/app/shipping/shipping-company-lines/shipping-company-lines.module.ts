import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FullCalendarModule } from '@fullcalendar/angular';
import { SharedModule } from '../../shared/shared.module';
import { ShippingCompanyLinesComponent } from './shipping-company-lines.component';
import { ShippingCompanyLinesRoutingModule } from './shipping-company-lines-routing.module';

@NgModule({
  declarations: [ShippingCompanyLinesComponent],
  imports: [CommonModule, SharedModule, FullCalendarModule, ShippingCompanyLinesRoutingModule],
})
export class ShippingCompanyLinesModule {}
