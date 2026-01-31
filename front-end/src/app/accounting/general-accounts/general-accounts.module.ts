import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { GeneralAccountsRoutingModule } from './general-accounts-routing.module';
import { SharedModule } from '../../shared/shared.module';
import { GeneralAccountsComponent } from './general-accounts.component';
import { FormsModule } from '@angular/forms';

@NgModule({
  declarations: [
    GeneralAccountsComponent
  ],
  imports: [
    CommonModule,
    GeneralAccountsRoutingModule,
    SharedModule,
    FormsModule
  ]

})
export class GeneralAccountsModule { }

