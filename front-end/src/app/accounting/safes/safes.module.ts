import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SafesRoutingModule } from './safes-routing.module';
import { SharedModule } from '../../shared/shared.module';
import { SafesComponent } from './safes.component';
import { SafeDepositWithdrawComponent } from './safe-deposit-withdraw.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MatDialogModule } from '@angular/material/dialog';
import { MatSnackBarModule } from '@angular/material/snack-bar';

@NgModule({
  declarations: [
    SafesComponent,
    SafeDepositWithdrawComponent
  ],
  imports: [
    CommonModule,
    SafesRoutingModule,
    SharedModule,
    FormsModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatSnackBarModule
  ]
})
export class SafesModule {}
