import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AddOrderComponent } from './add-order/add-order.component';
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
import { ShippingLineStatementComponent } from './shipping-line-statement/shipping-line-statement.component';

const routes: Routes = [
  {path:'addorder' , component:AddOrderComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Data Entry']}
  },
  {path:'listorders' , component:ListOrdersComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Data Entry','Review Management','Shipping Management','Operation Management', 'Finance and operations management','Operation Specialist','Logistics Specialist','Customer Service']}
  },
  {path:'ordersource' , component:OrderSourceComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:'shippingway' , component:ShippingWayComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:'shippingline' , component:ShippingLinesComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:'companies' , component:CompaniesComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management', 'Finance and operations management','Account Management','Logistics Specialist']}
  },
  {path:'shippingcompany' , component:ShippingCompanyComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management', 'Finance and operations management','Operation Specialist','Account Management','Logistics Specialist']}
  },
  {path:'confirmOrder/:id',component:ConfirmOrderComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management', 'Finance and operations management','Shipping Management','Customer Service']}
  },
  {path:'orderdetails/:id' , component:OrderDetailsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Data Entry','Review Management','Shipping Management','Operation Management', 'Finance and operations management','Operation Specialist','Logistics Specialist','Customer Service']}
  },
  {path:'shipOrder/:id',component:ShipOrderComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management', 'Finance and operations management','Operation Specialist','Logistics Specialist']}
  },
  {path:'editorder/:id' , component:EditOrderComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management', 'Finance and operations management','Shipping Management']}
  },
  {path:'collectorder/:id' , component:CollectOrderComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management', 'Finance and operations management','Operation Specialist','Logistics Specialist']}
  },
  {path:'collectpart/:id' , component:PartCollectComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management', 'Finance and operations management']}
  },
  {path:'shippingcompanydetails/:id' , component:ShippingcompanyDetailsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management', 'Finance and operations management','Operation Specialist','Account Management','Logistics Specialist']}
  },
  {path:'shippingcompanylines/:id' , component:ShippingCompanyLinesComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:'companydetails/:id' , component:CustomerCompanyDetailsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management', 'Finance and operations management','Account Management','Logistics Specialist']}
  },
  {path:'shipping-line-statement' , component:ShippingLineStatementComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin','Operation Management', 'Finance and operations management','Account Management','Logistics Specialist']}
  },
  {path:'companybalance/:id' , component:CustomerCompanyBalanceComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },

]


@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class ShippingRoutingModule { }
