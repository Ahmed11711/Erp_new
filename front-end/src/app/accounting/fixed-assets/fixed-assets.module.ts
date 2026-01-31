import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FixedAssetsRoutingModule } from './fixed-assets-routing.module';
import { SharedModule } from '../../shared/shared.module';
import { FixedAssetsComponent } from './fixed-assets.component';
import { CreateFixedAssetComponent } from './create-fixed-asset/create-fixed-asset.component';
import { DepreciationFixedAssetComponent } from './depreciation-fixed-asset/depreciation-fixed-asset.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';

@NgModule({
  declarations: [
    FixedAssetsComponent,
    CreateFixedAssetComponent,
    DepreciationFixedAssetComponent
  ],
  imports: [
    CommonModule,
    FixedAssetsRoutingModule,
    SharedModule,
    FormsModule,
    ReactiveFormsModule
  ]

})
export class FixedAssetsModule { }

