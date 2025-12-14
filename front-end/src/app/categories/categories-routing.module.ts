import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AddCategoryComponent } from './add-category/add-category.component';
import { ProductionComponent } from './production/production.component';
import { UnitsComponent } from './units/units.component';
import { ListCategoriesComponent } from './list-categories/list-categories.component';
import { EditCategoryComponent } from './edit-category/edit-category.component';
import { departmentGuard } from '../guards/department.guard';

const routes: Routes = [
  {path:'units',component:UnitsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Data Entry','Account Management','Logistics Specialist']}
  },
  {path:"production",component:ProductionComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Data Entry','Account Management','Logistics Specialist']}
  },
  {path:'add_category',component:AddCategoryComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Data Entry','Account Management','Logistics Specialist']}
  },
  {path:'edit_category/:id',component:EditCategoryComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Data Entry','Account Management','Logistics Specialist']}
  },
  {path:'all_categories',component:ListCategoriesComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Data Entry','Account Management','Logistics Specialist']}
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class CategoriesRoutingModule { }
