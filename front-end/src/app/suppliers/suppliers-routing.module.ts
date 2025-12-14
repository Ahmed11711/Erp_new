import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AddSupplierComponent } from './add-supplier/add-supplier.component';
import { ListSuppliersComponent } from './list-suppliers/list-suppliers.component';
import { SupplierDetailsComponent } from './supplier-details/supplier-details.component';
import { TypesComponent } from './types/types.component';
import { departmentGuard } from '../guards/department.guard';

const routes: Routes = [
  {path: 'types', component: TypesComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Account Management','Logistics Specialist']}
  },
  {path:'add_supplier',component:AddSupplierComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Account Management','Logistics Specialist']}
  },
  {path:'list_suppliers',component:ListSuppliersComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Account Management','Logistics Specialist']}
  },
  {path:'supplier_details/:id',component:SupplierDetailsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Account Management','Logistics Specialist']}
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class SuppliersRoutingModule { }
