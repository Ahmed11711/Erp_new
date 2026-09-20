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
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MatDialogModule } from '@angular/material/dialog';
import { MatSnackBarModule } from '@angular/material/snack-bar';
import { ConfirmDialogComponent } from './confirm-dialog/confirm-dialog.component';
import { RequirePermissionDirective } from './require-permission.directive';
import { AppDateInputComponent } from './app-date-input/app-date-input.component';
import { MatDatepickerModule } from '@angular/material/datepicker';
import {
  DateAdapter,
  MAT_DATE_FORMATS,
  MAT_DATE_LOCALE,
} from '@angular/material/core';
import { MatInputModule } from '@angular/material/input';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { CustomDateAdapter } from './date/custom-date-adapter';
import { APP_DATE_FORMATS } from './date/date-formats';

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
    RequirePermissionDirective,
    AppDateInputComponent,
  ],
  imports: [
    CommonModule,
    AngularEditorModule,
    MatDialogModule,
    MatSnackBarModule,
    FormsModule,
    ReactiveFormsModule,
    MatDatepickerModule,
    MatInputModule,
    MatIconModule,
    MatFormFieldModule,
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
    RequirePermissionDirective,
    AppDateInputComponent,
    MatDatepickerModule,
    MatInputModule,
    MatIconModule,
    MatFormFieldModule,
  ],
  providers: [
    { provide: MAT_DATE_LOCALE, useValue: 'en-GB' },
    { provide: DateAdapter, useClass: CustomDateAdapter },
    { provide: MAT_DATE_FORMATS, useValue: APP_DATE_FORMATS },
  ],
})
export class SharedModule {}
