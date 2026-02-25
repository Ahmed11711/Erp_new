import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { ReactiveFormsModule, FormsModule } from '@angular/forms';
import { MatNativeDateModule } from '@angular/material/core';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { AutocompleteLibModule } from 'angular-ng-autocomplete';
import { SharedModule } from '../shared/shared.module';
import { CorparatesSalesRoutingModule } from './corparates-sales-routing.module';
import { LeadListComponent } from './lead-list/lead-list.component';
import { LeadAddEditComponent } from './lead-add-edit/lead-add-edit.component';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatPaginatorModule } from '@angular/material/paginator';
import { NgxPaginationModule } from 'ngx-pagination';
import { LeadDetailsComponent } from './lead-details/lead-details.component';
import { LeadAddContactComponent } from './lead-add-contact/lead-add-contact.component';
import { LeadStatusManagementComponent } from '../lead-status-management/lead-status-management.component';
import { FollowUpLeadsComponent } from './follow-up-leads/follow-up-leads.component';


@NgModule({
  declarations: [
    LeadListComponent,
    LeadAddEditComponent,
    LeadDetailsComponent,
    LeadAddContactComponent,
    LeadStatusManagementComponent,
    FollowUpLeadsComponent
  ],
  imports: [
    CommonModule,
    CorparatesSalesRoutingModule,
    SharedModule,
    MatIconModule,
    FormsModule,
    ReactiveFormsModule,
    AutocompleteLibModule,
    MatNativeDateModule,
    MatFormFieldModule,
    MatInputModule,
    NgxPaginationModule,
    MatPaginatorModule,
    MatMenuModule,
  ]
})
export class CorparatesSalesModule { }
