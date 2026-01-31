import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ReceiveFromClientComponent } from './receive-from-client/receive-from-client.component';
import { PayToSupplierComponent } from './pay-to-supplier/pay-to-supplier.component';
import { departmentGuard } from '../../guards/department.guard';

const routes: Routes = [
    {
        path: 'receive-from-client',
        component: ReceiveFromClientComponent,
        canActivate: [departmentGuard],
        data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
    },
    {
        path: 'pay-to-supplier',
        component: PayToSupplierComponent,
        canActivate: [departmentGuard],
        data: { allowedDepartments: ['Admin', 'Account Management', 'Logistics Specialist'] }
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class BankRoutingModule { }
