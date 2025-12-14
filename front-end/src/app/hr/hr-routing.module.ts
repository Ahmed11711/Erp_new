import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AddEmployeeComponent } from './add-employee/add-employee.component';
import { EmployeeComponent } from './employee/employee.component';
// import { SalaryCashingComponent } from './salary-cashing/salary-cashing.component';
import { PayrollComponent } from './payroll/payroll.component';
// import { AddMeritComponent } from './add-merit/add-merit.component';
// import { AddSubtractionComponent } from './add-subtraction/add-subtraction.component';
// import { AdvancePaymentComponent } from './advance-payment/advance-payment.component';
// import { AccountStatementComponent } from './account-statement/account-statement.component';
import { ReviewAbsencesComponent } from './review-absences/review-absences.component';
import { EditEmployeeComponent } from './edit-employee/edit-employee.component';
import { EmployeeDetailsComponent } from './employee-details/employee-details.component';
// import { ExtraHoursComponent } from './extra-hours/extra-hours.component';
import { WorkingHoursComponent } from './working-hours/working-hours.component';
import { WorkingHoursDetailsComponent } from './working-hours-details/working-hours-details.component';
import { departmentGuard } from '../guards/department.guard';

const routes: Routes = [
  {path:'addemployee', component:AddEmployeeComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management']}
  },
  {path:'employee', component:EmployeeComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management']}
  },
  // {path:'salarycashing', component:SalaryCashingComponent},
  {path:'payroll', component:PayrollComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management']}
  },
  // {path:'addmerit', component:AddMeritComponent},
  // {path:'addsubtraction', component:AddSubtractionComponent},
  // {path:'advancepayment', component:AdvancePaymentComponent},
  // {path:'accountstatment', component:AccountStatementComponent},
  {path:'reviewabsencess', component:ReviewAbsencesComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management','Account Management','Logistics Specialist']}
  },
  {path:'employee/edit/:id', component:EditEmployeeComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management']}
  },
  {path:'employee/details/:id', component:EmployeeDetailsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management']}
  },
  // {path:'extrahours', component:ExtraHoursComponent},
  {path:'workinghours', component:WorkingHoursComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management']}
  },
  {path:'workinghoursdetails/:id', component:WorkingHoursDetailsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management']}
  },


];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class HrRoutingModule { }
