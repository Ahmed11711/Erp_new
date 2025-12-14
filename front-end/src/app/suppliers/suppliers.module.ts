import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { SuppliersRoutingModule } from './suppliers-routing.module';
import { TypesComponent } from './types/types.component';
import { AddTypeComponent } from './add-type/add-type.component';
import { FormsModule } from '@angular/forms';
import { AddSupplierComponent } from './add-supplier/add-supplier.component';
import {MatSnackBarModule} from '@angular/material/snack-bar';
import { ListSuppliersComponent } from './list-suppliers/list-suppliers.component';
import {MatAutocompleteModule} from '@angular/material/autocomplete';
import { ReactiveFormsModule} from '@angular/forms';
import { MatPaginatorModule} from '@angular/material/paginator';
import { SharedModule } from '../shared/shared.module';
import {MatMenuModule} from '@angular/material/menu';
import { MatIconModule } from '@angular/material/icon';
import { SupplierDetailsComponent } from './supplier-details/supplier-details.component';
import { DialogPayMoneyForSupplierComponent } from './dialog-pay-money-for-supplier/dialog-pay-money-for-supplier.component';
import { MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';

@NgModule({
  declarations: [
    TypesComponent,
    AddTypeComponent,
    AddSupplierComponent,
    ListSuppliersComponent,
    SupplierDetailsComponent,
    DialogPayMoneyForSupplierComponent
  ],
  imports: [
    MatIconModule,
    MatMenuModule,
    MatPaginatorModule,
    FormsModule,
    ReactiveFormsModule,
    MatAutocompleteModule,
    MatSnackBarModule,
    CommonModule,
    SuppliersRoutingModule,
    SharedModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
  ]
})
export class SuppliersModule { }
