import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BanksRoutingModule } from './banks-routing.module';
import { SharedModule } from '../../shared/shared.module';
import { BanksComponent } from './banks.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { BankTransferComponent } from './bank-transfer.component';
import { BankSafeTransferComponent } from './bank-safe-transfer.component';
import { BankDepositWithdrawComponent } from './bank-deposit-withdraw.component';

@NgModule({
  declarations: [
    BanksComponent,
    BankTransferComponent,
    BankSafeTransferComponent,
    BankDepositWithdrawComponent
  ],
  imports: [
    CommonModule,
    BanksRoutingModule,
    SharedModule,
    FormsModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule
  ]
})
export class BanksModule { }

