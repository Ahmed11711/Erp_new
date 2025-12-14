import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TimeComponent } from './time/time.component';
import { ArabicDayDatePipe } from './time/arabic-day-date.pipe';
import { CustomNumberPipe } from '../pipes/custom-number.pipe';
import { CustomDatePipe } from '../pipes/custom-date.pipe';
import { CustomDayNamePipe } from '../pipes/custom-day-name.pipe';
import { FixedTimePipe } from '../pipes/fixed-time.pipe';
import { AngularEditorComponent } from './angular-editor/angular-editor.component';
import { AngularEditorModule } from '@kolkov/angular-editor';
import { FormsModule } from '@angular/forms';
import { MatDialogModule } from '@angular/material/dialog';


@NgModule({
  declarations: [
    TimeComponent,
    ArabicDayDatePipe,
    CustomNumberPipe,
    CustomDatePipe,
    CustomDayNamePipe,
    FixedTimePipe,
    AngularEditorComponent
  ],
  imports: [

    CommonModule,
    AngularEditorModule,
    MatDialogModule,
    FormsModule
  ],
  exports: [
    TimeComponent, // Export the component
    CustomNumberPipe,
    CustomDatePipe,
    CustomDayNamePipe,
    FixedTimePipe,
    AngularEditorComponent
  ],
})
export class SharedModule { }
