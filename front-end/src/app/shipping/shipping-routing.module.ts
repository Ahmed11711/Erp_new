import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AddOrderComponent } from './add-order/add-order.component';
import { ConvertOfferToOrderComponent } from './convert-offer-to-order/convert-offer-to-order.component';
import { OfferOrdersComponent } from './offer-orders/offer-orders.component';
import { ListOrdersComponent } from './list-orders/list-orders.component';
import { OrderSourceComponent } from './order-source/order-source.component';
import { ShippingWayComponent } from './shipping-way/shipping-way.component';
import { ShippingLinesComponent } from './shipping-lines/shipping-lines.component';
import { ShippingCompanyComponent } from './shipping-company/shipping-company.component';

import { ConfirmOrderComponent } from './confirm-order/confirm-order.component';

import { OrderDetailsComponent } from './order-details/order-details.component';
import { ShipOrderComponent } from './ship-order/ship-order.component';
import { EditOrderComponent } from './edit-order/edit-order.component';
import { CollectOrderComponent } from './collect-order/collect-order.component';
import { ShippingcompanyDetailsComponent } from './shippingcompany-details/shippingcompany-details.component';
import { CompaniesComponent } from './companies/companies.component';
import { PartCollectComponent } from './part-collect/part-collect.component';
import { CustomerCompanyDetailsComponent } from './customer-company-details/customer-company-details.component';
import { CustomerCompanyBalanceComponent } from './customer-company-balance/customer-company-balance.component';
import { ShippingCompanyLinesComponent } from './shipping-company-lines/shipping-company-lines.component';
import { departmentGuard } from '../guards/department.guard';
import { RBAC_ROUTE } from '../guards/rbac-route-data';
import { ShippingLineStatementComponent } from './shipping-line-statement/shipping-line-statement.component';
import { ShippingAccountsReportComponent } from './shipping-accounts-report/shipping-accounts-report.component';
import { CollectionCompaniesComponent } from './collection-companies/collection-companies.component';
import { CollectionAccountsReportComponent } from './collection-accounts-report/collection-accounts-report.component';

const cr = [...RBAC_ROUTE.ordersCreate];
const convertOffer = [...RBAC_ROUTE.ordersConvertFromOffer];
const vw = [...RBAC_ROUTE.ordersView];
const ed = [...RBAC_ROUTE.ordersEdit];
const ff = [...RBAC_ROUTE.ordersFulfillment];
const ms = [...RBAC_ROUTE.shippingMaster];
const scc = [...RBAC_ROUTE.shippingCompanyCrud];
const scs = [...RBAC_ROUTE.shippingCompanyStatement];
const ccv = [...RBAC_ROUTE.customerCompaniesView];
const ccs = [...RBAC_ROUTE.customerCompaniesStatement];
const adm = [...RBAC_ROUTE.systemAdmin];
const sar = [...RBAC_ROUTE.shippingAccountsReport];

const routes: Routes = [
  { path: 'addorder', component: AddOrderComponent, canActivate: [departmentGuard], data: { rbacPermissions: cr } },
  {
    path: 'convert-offer/:offerId',
    component: ConvertOfferToOrderComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: convertOffer },
  },
  {
    path: 'offer-orders',
    component: OfferOrdersComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: convertOffer },
  },
  { path: 'listorders', component: ListOrdersComponent, canActivate: [departmentGuard], data: { rbacPermissions: vw } },
  { path: 'ordersource', component: OrderSourceComponent, canActivate: [departmentGuard], data: { rbacPermissions: ms } },
  { path: 'shippingway', component: ShippingWayComponent, canActivate: [departmentGuard], data: { rbacPermissions: ms } },
  { path: 'shippingline', component: ShippingLinesComponent, canActivate: [departmentGuard], data: { rbacPermissions: ms } },
  { path: 'companies', component: CompaniesComponent, canActivate: [departmentGuard], data: { rbacPermissions: ccv } },
  {
    path: 'shippingcompany',
    component: ShippingCompanyComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: scc },
  },
  {
    path: 'confirmOrder/:id',
    component: ConfirmOrderComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: vw },
  },
  {
    path: 'orderdetails/:id',
    component: OrderDetailsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: vw },
  },
  {
    path: 'shipOrder/:id',
    component: ShipOrderComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: ff },
  },
  {
    path: 'editorder/:id',
    component: EditOrderComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: ed },
  },
  {
    path: 'collectorder/:id',
    component: CollectOrderComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: ff },
  },
  {
    path: 'collectpart/:id',
    component: PartCollectComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: ff },
  },
  {
    path: 'shippingcompanydetails/:id',
    component: ShippingcompanyDetailsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: scs },
  },
  {
    path: 'shippingcompanylines/:id',
    component: ShippingCompanyLinesComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: adm },
  },
  {
    path: 'companydetails/:id',
    component: CustomerCompanyDetailsComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: vw },
  },
  {
    path: 'shipping-line-statement',
    component: ShippingLineStatementComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: vw },
  },
  {
    path: 'companybalance/:id',
    component: CustomerCompanyBalanceComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: ccs },
  },
  {
    path: 'shipping-accounts-report',
    component: ShippingAccountsReportComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: sar },
  },
  {
    path: 'collection-companies',
    component: CollectionCompaniesComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: scc },
  },
  {
    path: 'collection-accounts-report',
    component: CollectionAccountsReportComponent,
    canActivate: [departmentGuard],
    data: { rbacPermissions: sar },
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule],
})
export class ShippingRoutingModule {}
