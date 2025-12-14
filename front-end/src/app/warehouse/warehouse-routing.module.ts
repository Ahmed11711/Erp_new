import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { CatDetailsComponent } from './cat-details/cat-details.component';
import { CatComponent } from './cat/cat.component';
import { ListWarehouseComponent } from './list-warehouse/list-warehouse.component';
import { WarehouseDetailsComponent } from './warehouse-details/warehouse-details.component';
import { MonthlyInventoryComponent } from './monthly-inventory/monthly-inventory.component';
import { departmentGuard } from '../guards/department.guard';

const routes: Routes = [
  {path: 'list',component:ListWarehouseComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Account Management','Logistics Specialist']}
  },
  {path: 'listwarhouse',component:ListWarehouseComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Account Management','Logistics Specialist']}
  },
  {path:'cat',component:CatComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Account Management','Logistics Specialist']}
  },
  {path:'cat_details/:id',component:CatDetailsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Account Management','Logistics Specialist']}
  },
  {path:'warehousedetails',component:WarehouseDetailsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Account Management','Logistics Specialist']}
  },
  {path:'monthlyinventory',component:MonthlyInventoryComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Account Management','Logistics Specialist']}
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class WarehouseRoutingModule { }
