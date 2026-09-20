import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { SelectivePreloadStrategy } from './core/selective-preload.strategy';
import { DashboardComponent } from './dashboard/dashboard.component';
import { AdminGuard } from './guards/admin.guard';
import { SystemLockGuard } from './system-lock/system-lock.guard';

/** تحميل lazy module مع إعادة تحميل الصفحة مرة واحدة عند فشل الـ chunk بعد نشر جديد */
function lazyLoad<T>(
  loader: () => Promise<T>,
  moduleName: string
): () => Promise<T> {
  return () =>
    loader().catch((err: unknown): Promise<T> => {
      const message = String((err as { message?: string })?.message ?? err ?? '');
      const name = String((err as { name?: string })?.name ?? '');
      const isChunkError =
        name === 'ChunkLoadError' ||
        /loading chunk|failed to fetch|dynamically imported module/i.test(message);

      console.error(`${moduleName} lazy-load failed`, err);

      if (isChunkError && typeof window !== 'undefined') {
        const key = `lazy-reload:${moduleName}`;
        if (!sessionStorage.getItem(key)) {
          sessionStorage.setItem(key, '1');
          window.location.reload();
          // الصفحة تُعاد تحميلها — نُبقي الـ Promise معلّقاً
          return new Promise<T>(() => undefined);
        }
        sessionStorage.removeItem(key);
      }

      throw err;
    });
}

const routes: Routes = [
  {path:'', loadChildren: () => import('./auth/auth.module').then(m => m.AuthModule)},
  {path:'dashboard',component:DashboardComponent, canActivate:[AdminGuard], canActivateChild:[SystemLockGuard],
  children:[
    {path:'unavailable', redirectTo: 'shipping/listorders', pathMatch: 'full'},
    {path:'', loadChildren: () => import('./home/home.module').then(m => m.HomeModule)},
    {path:'categories', data: { preload: true }, loadChildren: () => import('./categories/categories.module').then(m => m.CategoriesModule)},
    {path:'suppliers', loadChildren: () => import('./suppliers/suppliers.module').then(m => m.SuppliersModule)},
    {path: 'warehouse', data: { preload: true }, loadChildren: () => import('./warehouse/warehouse.module').then(m => m.WarehouseModule)},
    {path: 'purchases', loadChildren: () => import('./purchases/purchases.module').then(m => m.PurchasesModule)},
    {path: 'financial', data: { preload: true }, loadChildren: () => import('./financial/financial.module').then(m => m.FinancialModule)},
  {path: 'accounting', loadChildren: () => import('./accounting/accounting.module').then(m => m.AccountingModule)},
    {path: 'shipping', data: { preload: true }, loadChildren: () => import('./shipping/shipping.module').then(m => m.ShippingModule)},
    {path: 'manufacturing', loadChildren: () => import('./manufacturing/manufacturing.module').then(m => m.ManufacturingModule)},
    {path: 'processing', loadChildren: () => import('./processing/processing.module').then(m => m.ProcessingModule)},
    {path: 'hr', data: { preload: true }, loadChildren: () => import('./hr/hr.module').then(m => m.HrModule)},
    {path: 'system', loadChildren: () => import('./manage-system/manage-system.module').then(m => m.ManageSystemModule)},
    {path: 'reports', loadChildren: () => import('./reports/reports.module').then(m => m.ReportsModule)},
    {
      path: 'permissions',
      data: { preload: true },
      loadChildren: lazyLoad(
        () => import('./permissions/permissions.module').then((m) => m.PermissionsModule),
        'PermissionsModule'
      ),
    },
    {path: 'admin', loadChildren: () => import('./admin/admin.module').then(m => m.AdminModule)},
    {path: 'notification', loadChildren: () => import('./notification/notification.module').then(m => m.NotificationModule)},
    {path: 'whatsapp', loadChildren: () => import('./whatsapp/whatsapp.module').then(m => m.WhatsAppModule)},
    {path: 'approvals', loadChildren: () => import('./approvals/approvals.module').then(m => m.ApprovalsModule)},
    {path: 'corparates-sales', loadChildren: () => import('./corparates-sales/corparates-sales.module').then(m => m.CorparatesSalesModule)},
    {path: 'shopify', loadChildren: () => import('./shopify-integration/shopify-integration.module').then(m => m.ShopifyIntegrationModule)},
  ]
},
  { path: '**', redirectTo: '' },
];

@NgModule({
  imports: [RouterModule.forRoot(routes, { preloadingStrategy: SelectivePreloadStrategy })],
  exports: [RouterModule]
})
export class AppRoutingModule { }
