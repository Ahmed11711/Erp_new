import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AddRecipeComponent } from './add-recipe/add-recipe.component';
import { ManufacturingRecipesComponent } from './manufacturing-recipes/manufacturing-recipes.component';
import { ManufacturingConfirmationComponent } from './manufacturing-confirmation/manufacturing-confirmation.component';
import { ManufacturingOrdersComponent } from './manufacturing-orders/manufacturing-orders.component';
import { ManufacturingAdditionsComponent } from './manufacturing-additions/manufacturing-additions.component';
import { departmentGuard } from '../guards/department.guard';

const routes: Routes = [
  {path:'addrecipe' , component:AddRecipeComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:'recipes' , component:ManufacturingRecipesComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:'confirmation' , component:ManufacturingConfirmationComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:'orders' , component:ManufacturingOrdersComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:'additions' , component:ManufacturingAdditionsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ManufacturingRoutingModule { }
