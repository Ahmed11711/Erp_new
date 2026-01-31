import { NgModule } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';

import { HrRoutingModule } from './hr-routing.module';
import { AddEmployeeComponent } from './add-employee/add-employee.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { SharedModule } from '../shared/shared.module';
import { EmployeeComponent } from './employee/employee.component';
import { SalaryCashingComponent } from './salary-cashing/salary-cashing.component';
import { AutocompleteLibModule } from 'angular-ng-autocomplete';
import { MatNativeDateModule } from '@angular/material/core';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { PayrollComponent } from './payroll/payroll.component';
import { AddMeritComponent } from './add-merit/add-merit.component';
import { AddSubtractionComponent } from './add-subtraction/add-subtraction.component';
import { AdvancePaymentComponent } from './advance-payment/advance-payment.component';
import { AccountStatementComponent } from './account-statement/account-statement.component';
import { ReviewAbsencesComponent } from './review-absences/review-absences.component';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatPaginatorModule } from '@angular/material/paginator';
import { NgxPaginationModule } from 'ngx-pagination';
import { EditEmployeeComponent } from './edit-employee/edit-employee.component';
import { EmployeeDetailsComponent } from './employee-details/employee-details.component';
import { PrintEmpolyeeDetailsComponent } from './print-empolyee-details/print-empolyee-details.component';
import { ExtraHoursComponent } from './extra-hours/extra-hours.component';
import { WorkingHoursComponent } from './working-hours/working-hours.component';
import { WorkingHoursDetailsComponent } from './working-hours-details/working-hours-details.component';



@NgModule({
  declarations: [
    AddEmployeeComponent,
    EmployeeComponent,
    SalaryCashingComponent,
    PayrollComponent,
    AddMeritComponent,
    AddSubtractionComponent,
    AdvancePaymentComponent,
    AccountStatementComponent,
    ReviewAbsencesComponent,
    EditEmployeeComponent,
    EmployeeDetailsComponent,
    PrintEmpolyeeDetailsComponent,
    ExtraHoursComponent,
    WorkingHoursComponent,
    WorkingHoursDetailsComponent
  ],
  imports: [
    CommonModule,
    HrRoutingModule,
    ReactiveFormsModule,
    FormsModule,
    SharedModule,
    AutocompleteLibModule,
    MatNativeDateModule,
    MatFormFieldModule,
    MatInputModule,
    MatDatepickerModule,
    MatIconModule,
    MatMenuModule,
    NgxPaginationModule,
    MatPaginatorModule,
  ],
  providers:[
    DatePipe
  ]
})
export class HrModule { }
