import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { CatDetailsComponent } from './cat-details/cat-details.component';
import { CatComponent } from './cat/cat.component';
import { ListWarehouseComponent } from './list-warehouse/list-warehouse.component';
import { WarehouseDetailsComponent } from './warehouse-details/warehouse-details.component';
import { MonthlyInventoryComponent } from './monthly-inventory/monthly-inventory.component';
import { InventoryImportComponent } from './inventory-import/inventory-import.component';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';

const warehouseAccess = {
  canActivate: [departmentGuard],
  data: { rbacPermissions: [...RBAC_ROUTE.inventoryWarehouse] },
};

const routes: Routes = [
  { path: 'list', component: ListWarehouseComponent, ...warehouseAccess },
  { path: 'listwarhouse', component: ListWarehouseComponent, ...warehouseAccess },
  { path: 'listwarehouse', redirectTo: 'listwarhouse', pathMatch: 'full' },
  { path: 'cat', component: CatComponent, ...warehouseAccess },
  { path: 'cat_details/:id', component: CatDetailsComponent, ...warehouseAccess },
  { path: 'warehousedetails', component: WarehouseDetailsComponent, ...warehouseAccess },
  { path: 'monthlyinventory', component: MonthlyInventoryComponent, ...warehouseAccess },
  { path: 'inventory-import', component: InventoryImportComponent, ...warehouseAccess },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class WarehouseRoutingModule {}
