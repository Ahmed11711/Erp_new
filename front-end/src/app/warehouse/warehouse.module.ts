import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { WarehouseRoutingModule } from './warehouse-routing.module';
import { ListWarehouseComponent } from './list-warehouse/list-warehouse.component';
import { CatComponent } from './cat/cat.component';
import { SharedModule } from '../shared/shared.module';
import { CatDetailsComponent } from './cat-details/cat-details.component';
import { MatMenuModule } from '@angular/material/menu';
import { MatPaginatorModule } from '@angular/material/paginator';
import { MatIconModule } from '@angular/material/icon';
import { WarehouseDetailsComponent } from './warehouse-details/warehouse-details.component';
import { MonthlyInventoryComponent } from './monthly-inventory/monthly-inventory.component';
import { DialogComponent } from './dialog/dialog.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';


@NgModule({
  declarations: [
    ListWarehouseComponent,
    CatComponent,
    CatDetailsComponent,
    WarehouseDetailsComponent,
    MonthlyInventoryComponent,
    DialogComponent
  ],
  imports: [
    CommonModule,
    WarehouseRoutingModule,
     SharedModule,
     MatMenuModule,
     MatPaginatorModule,
     MatIconModule,
     FormsModule,
     ReactiveFormsModule
  ]
})
export class WarehouseModule { }
