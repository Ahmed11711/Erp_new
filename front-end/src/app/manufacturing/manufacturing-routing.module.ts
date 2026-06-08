import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AddRecipeComponent } from './add-recipe/add-recipe.component';
import { ManufacturingRecipesComponent } from './manufacturing-recipes/manufacturing-recipes.component';
import { ManufacturingBomListComponent } from './manufacturing-bom-list/manufacturing-bom-list.component';
import { ManufacturingConfirmationComponent } from './manufacturing-confirmation/manufacturing-confirmation.component';
import { ManufacturingOrdersComponent } from './manufacturing-orders/manufacturing-orders.component';
import { ManufacturingAdditionsComponent } from './manufacturing-additions/manufacturing-additions.component';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';

const mfg = [...RBAC_ROUTE.manufacturingAll];

const routes: Routes = [
  { path: 'addrecipe', component: AddRecipeComponent, canActivate: [departmentGuard], data: { rbacPermissions: mfg } },
  { path: 'recipes', component: ManufacturingRecipesComponent, canActivate: [departmentGuard], data: { rbacPermissions: mfg } },
  { path: 'bom', component: ManufacturingBomListComponent, canActivate: [departmentGuard], data: { rbacPermissions: mfg } },
  {
    path: 'confirmation',
    component: ManufacturingConfirmationComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: mfg },
  },
  { path: 'orders', component: ManufacturingOrdersComponent, canActivate: [departmentGuard], data: { rbacPermissions: mfg } },
  {
    path: 'additions',
    component: ManufacturingAdditionsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: mfg },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ManufacturingRoutingModule {}
