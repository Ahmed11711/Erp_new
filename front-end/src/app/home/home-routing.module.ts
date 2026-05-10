import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { HomeComponent } from './home.component';
import { CategoriesReportComponent } from './categories-report/categories-report.component';
import { departmentGuard } from '../guards/department.guard';

const routes: Routes = [
  {path:'' , component:HomeComponent},
  {path:'categoriesreports' , component:CategoriesReportComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Data Entry', 'Account Management', 'Logistics Specialist', 'Financial Accounts'] }
  },

];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class HomeRoutingModule { }
