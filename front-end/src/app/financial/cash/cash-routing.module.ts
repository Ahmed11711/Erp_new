import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ClientCashPaymentsComponent } from './client-cash-payments.component';
import { SupplierCashPaymentsComponent } from './supplier-cash-payments.component';
import { CashReceiveFromClientComponent } from './receive-from-client/receive-from-client.component';
import { CashGiveToClientComponent } from './give-to-client/give-to-client.component';
import { CashReceiveFromSupplierComponent } from './receive-from-supplier/receive-from-supplier.component';
import { CashPayToSupplierComponent } from './pay-to-supplier/pay-to-supplier.component';
import { departmentGuard } from '../../guards/department.guard';

const routes: Routes = [
    {
        path: 'previous/clients',
        component: ClientCashPaymentsComponent,
        canActivate: [departmentGuard],
        data: { allowedDepartments: ['Admin', 'Account Management'] }
    },
    {
        path: 'previous/suppliers',
        component: SupplierCashPaymentsComponent,
        canActivate: [departmentGuard],
        data: { allowedDepartments: ['Admin', 'Account Management'] }
    },
    {
        path: 'receive-from-client',
        component: CashReceiveFromClientComponent,
        canActivate: [departmentGuard],
        data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
    },
    {
        path: 'give-to-client',
        component: CashGiveToClientComponent,
        canActivate: [departmentGuard],
        data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
    },
    {
        path: 'receive-from-supplier',
        component: CashReceiveFromSupplierComponent,
        canActivate: [departmentGuard],
        data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
    },
    {
        path: 'pay-to-supplier',
        component: CashPayToSupplierComponent,
        canActivate: [departmentGuard],
        data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class CashRoutingModule { }
