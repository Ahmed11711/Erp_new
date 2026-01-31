import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CostCentersRoutingModule } from './cost-centers-routing.module';
import { SharedModule } from '../../shared/shared.module';
import { CostCentersComponent } from './cost-centers.component';

@NgModule({
  declarations: [
    CostCentersComponent
  ],
  imports: [
    CommonModule,
    FormsModule,
    CostCentersRoutingModule,
    SharedModule
  ]
})
export class CostCentersModule { }

