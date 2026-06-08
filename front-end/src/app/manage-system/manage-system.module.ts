import { NgModule } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';

import { ManageSystemRoutingModule } from './manage-system-routing.module';
import { AddUserComponent } from './add-user/add-user.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { SharedModule } from '../shared/shared.module';
import { UsersComponent } from './users/users.component';
import { PowersComponent } from './powers/powers.component';
import { MatNativeDateModule } from '@angular/material/core';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { AutocompleteLibModule } from 'angular-ng-autocomplete';
import { FollowUsersComponent } from './follow-users/follow-users.component';
import { SettingsComponent } from './settings/settings.component';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatCardModule } from '@angular/material/card';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatDividerModule } from '@angular/material/divider';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatListModule } from '@angular/material/list';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTabsModule } from '@angular/material/tabs';
import { MatChipsModule } from '@angular/material/chips';
import { MatButtonToggleModule } from '@angular/material/button-toggle';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { RolesManagementComponent } from './roles-management/roles-management.component';


@NgModule({
  declarations: [
    AddUserComponent,
    UsersComponent,
    PowersComponent,
    FollowUsersComponent,
    SettingsComponent,
    RolesManagementComponent,
  ],
  imports: [
    CommonModule,
    ManageSystemRoutingModule,
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
    MatCardModule,
    MatSelectModule,
    MatButtonModule,
    MatExpansionModule,
    MatDividerModule,
    MatCheckboxModule,
    MatTooltipModule,
    MatListModule,
    MatProgressSpinnerModule,
    MatTabsModule,
    MatChipsModule,
    MatButtonToggleModule,
    MatSlideToggleModule,
  ],
  providers: [
    DatePipe
  ],
})
export class ManageSystemModule { }
