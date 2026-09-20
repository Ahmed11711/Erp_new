import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { FixedAssetsComponent } from './fixed-assets.component';
import { CreateFixedAssetComponent } from './create-fixed-asset/create-fixed-asset.component';
import { DepreciationFixedAssetComponent } from './depreciation-fixed-asset/depreciation-fixed-asset.component';
import { departmentGuard } from '../../guards/department.guard';
import { RBAC_ROUTE } from '../../guards/rbac-route-data';

const fin = [...RBAC_ROUTE.financeTeam];

const routes: Routes = [
  {
    path: '',
    component: FixedAssetsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin },
  },
  {
    path: 'create',
    component: CreateFixedAssetComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin },
  },
  {
    path: 'depreciation',
    component: DepreciationFixedAssetComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: fin },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class FixedAssetsRoutingModule {}
