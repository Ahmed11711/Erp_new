import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ClientCashPaymentsComponent } from './client-cash-payments.component';
import { SupplierCashPaymentsComponent } from './supplier-cash-payments.component';
import { ReceivePaymentComponent } from './receive-payment/receive-payment.component';
import { CashGiveToClientComponent } from './give-to-client/give-to-client.component';
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
        path: 'receive',
        component: ReceivePaymentComponent,
        canActivate: [departmentGuard],
        data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
    },
    {
        path: 'receive-from-client',
        redirectTo: 'receive',
        pathMatch: 'full'
    },
    {
        path: 'receive-from-supplier',
        component: ReceivePaymentComponent,
        canActivate: [departmentGuard],
        data: {
            allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'],
            defaultParty: 'supplier'
        }
    },
    {
        path: 'give-to-client',
        component: CashGiveToClientComponent,
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
