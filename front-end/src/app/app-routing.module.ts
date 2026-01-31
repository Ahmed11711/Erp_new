import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { DashboardComponent } from './dashboard/dashboard.component';
import { AdminGuard } from './guards/admin.guard';


const routes: Routes = [
  {path:'', loadChildren: () => import('./auth/auth.module').then(m => m.AuthModule)},
  {path:'dashboard',component:DashboardComponent, canActivate:[AdminGuard],
  children:[
    {path:'', loadChildren: () => import('./home/home.module').then(m => m.HomeModule)},
    {path:'categories', loadChildren: () => import('./categories/categories.module').then(m => m.CategoriesModule)},
    {path:'suppliers', loadChildren: () => import('./suppliers/suppliers.module').then(m => m.SuppliersModule)},
    {path: 'warehouse', loadChildren: () => import('./warehouse/warehouse.module').then(m => m.WarehouseModule)},
    {path: 'purchases', loadChildren: () => import('./purchases/purchases.module').then(m => m.PurchasesModule)},
    {path: 'financial', loadChildren: () => import('./financial/financial.module').then(m => m.FinancialModule)},
  {path: 'accounting', loadChildren: () => import('./accounting/accounting.module').then(m => m.AccountingModule)},
    {path: 'shipping', loadChildren: () => import('./shipping/shipping.module').then(m => m.ShippingModule)},
    {path: 'manufacturing', loadChildren: () => import('./manufacturing/manufacturing.module').then(m => m.ManufacturingModule)},
    {path: 'hr', loadChildren: () => import('./hr/hr.module').then(m => m.HrModule)},
    {path: 'system', loadChildren: () => import('./manage-system/manage-system.module').then(m => m.ManageSystemModule)},
    {path: 'reports', loadChildren: () => import('./reports/reports.module').then(m => m.ReportsModule)},
    {path: 'permissions', loadChildren: () => import('./permissions/permissions.module').then(m => m.PermissionsModule)},
    {path: 'admin', loadChildren: () => import('./admin/admin.module').then(m => m.AdminModule)},
    {path: 'notification', loadChildren: () => import('./notification/notification.module').then(m => m.NotificationModule)},
    {path: 'approvals', loadChildren: () => import('./approvals/approvals.module').then(m => m.ApprovalsModule)},
    {path: 'corparates-sales', loadChildren: () => import('./corparates-sales/corparates-sales.module').then(m => m.CorparatesSalesModule)},
  ]
},
];

@NgModule({
  imports: [RouterModule.forRoot(routes)],
  exports: [RouterModule]
})
export class AppRoutingModule { }
