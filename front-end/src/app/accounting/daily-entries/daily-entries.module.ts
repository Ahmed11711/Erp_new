import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MAT_AUTOCOMPLETE_DEFAULT_OPTIONS, MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatInputModule } from '@angular/material/input';
import { DailyEntriesRoutingModule } from './daily-entries-routing.module';
import { SharedModule } from '../../shared/shared.module';
import { DailyEntriesComponent } from './daily-entries.component';

@NgModule({
  declarations: [
    DailyEntriesComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    MatAutocompleteModule,
    MatInputModule,
    DailyEntriesRoutingModule,
    SharedModule
  ],
  providers: [
    {
      provide: MAT_AUTOCOMPLETE_DEFAULT_OPTIONS,
      useValue: {
        overlayPanelClass: 'daily-entry-account-panel',
      },
    },
  ],
})
export class DailyEntriesModule { }

