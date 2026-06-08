import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AddCategoryComponent } from './add-category/add-category.component';
import { ProductionComponent } from './production/production.component';
import { UnitsComponent } from './units/units.component';
import { ListCategoriesComponent } from './list-categories/list-categories.component';
import { EditCategoryComponent } from './edit-category/edit-category.component';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';

const cat = [...RBAC_ROUTE.categoriesAll];

const routes: Routes = [
  { path: 'units', component: UnitsComponent, canActivate: [departmentGuard], data: { rbacPermissions: cat } },
  { path: 'production', component: ProductionComponent, canActivate: [departmentGuard], data: { rbacPermissions: cat } },
  { path: 'add_category', component: AddCategoryComponent, canActivate: [departmentGuard], data: { rbacPermissions: cat } },
  { path: 'edit_category/:id', component: EditCategoryComponent, canActivate: [departmentGuard], data: { rbacPermissions: cat } },
  { path: 'all_categories', component: ListCategoriesComponent, canActivate: [departmentGuard], data: { rbacPermissions: cat } },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class CategoriesRoutingModule {}
