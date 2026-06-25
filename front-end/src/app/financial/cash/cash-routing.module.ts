import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { PreviousCashPaymentsComponent } from './previous-cash-payments/previous-cash-payments.component';
import { ReceivePaymentComponent } from './receive-payment/receive-payment.component';
import { CashGiveToClientComponent } from './give-to-client/give-to-client.component';
import { CashPayToSupplierComponent } from './pay-to-supplier/pay-to-supplier.component';
import { departmentGuard } from '../../guards/department.guard';
import { RBAC_ROUTE } from '../../guards/rbac-route-data';

const finCore = [...RBAC_ROUTE.financeTeam];

const routes: Routes = [
  {
    path: 'previous',
    component: PreviousCashPaymentsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: finCore },
  },
  { path: 'previous/clients', redirectTo: 'previous', pathMatch: 'full' },
  { path: 'previous/suppliers', redirectTo: 'previous', pathMatch: 'full' },
  {
    path: 'receive',
    component: ReceivePaymentComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: finCore },
  },
  { path: 'receive-from-client', redirectTo: 'receive', pathMatch: 'full' },
  {
    path: 'receive-from-supplier',
    component: ReceivePaymentComponent,
    canActivate: [departmentGuard],
    data: {
      rbacPermissions: finCore,
      defaultParty: 'supplier',
    },
  },
  {
    path: 'pay-shipping-partner',
    component: ReceivePaymentComponent,
    canActivate: [departmentGuard],
    data: {
      rbacPermissions: finCore,
      defaultParty: 'shipping_company',
      defaultVoucherType: 'payment',
    },
  },
  {
    path: 'pay-collection-company',
    component: ReceivePaymentComponent,
    canActivate: [departmentGuard],
    data: {
      rbacPermissions: finCore,
      defaultParty: 'collection_company',
      defaultVoucherType: 'payment',
    },
  },
  {
    path: 'give-to-client',
    component: CashGiveToClientComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: finCore },
  },
  {
    path: 'pay-to-supplier',
    component: CashPayToSupplierComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: finCore },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class CashRoutingModule {}
