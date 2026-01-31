import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ClientCashPaymentsComponent } from './client-cash-payments.component';
import { SupplierCashPaymentsComponent } from './supplier-cash-payments.component';
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
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class CashRoutingModule { }
