import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { FixedAssetsComponent } from './fixed-assets.component';
import { CreateFixedAssetComponent } from './create-fixed-asset/create-fixed-asset.component';
import { DepreciationFixedAssetComponent } from './depreciation-fixed-asset/depreciation-fixed-asset.component';
import { departmentGuard } from '../../guards/department.guard';

const routes: Routes = [
  {
    path: '',
    component: FixedAssetsComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  },
  {
    path: 'create',
    component: CreateFixedAssetComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  },
  {
    path: 'depreciation',
    component: DepreciationFixedAssetComponent,
    canActivate: [departmentGuard],
    data: { allowedDepartments: ['Admin', 'Account Management'] }
  }
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class FixedAssetsRoutingModule { }

