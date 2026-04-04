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
import { MatSnackBarModule } from '@angular/material/snack-bar';
import { ConfirmDialogComponent } from './confirm-dialog/confirm-dialog.component';

@NgModule({
  declarations: [
    TimeComponent,
    ArabicDayDatePipe,
    CustomNumberPipe,
    CustomDatePipe,
    CustomDayNamePipe,
    FixedTimePipe,
    AngularEditorComponent,
    ConfirmDialogComponent,
  ],
  imports: [
    CommonModule,
    AngularEditorModule,
    MatDialogModule,
    MatSnackBarModule,
    FormsModule,
  ],
  exports: [
    TimeComponent,
    CustomNumberPipe,
    CustomDatePipe,
    CustomDayNamePipe,
    FixedTimePipe,
    AngularEditorComponent,
    MatDialogModule,
    MatSnackBarModule,
    ConfirmDialogComponent,
  ],
})
export class SharedModule {}
