import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
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
    DailyEntriesRoutingModule,
    SharedModule
  ]
})
export class DailyEntriesModule { }

