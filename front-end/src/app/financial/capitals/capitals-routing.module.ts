import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { ListCapitalsComponent } from './list-capitals.component';
import { AddCapitalComponent } from './add-capital.component';
import { departmentGuard } from '../../guards/department.guard';

const routes: Routes = [
    {
        path: '',
        component: ListCapitalsComponent,
        canActivate: [departmentGuard],
        data: { allowedDepartments: ['Admin'] }
    },
    {
        path: 'create',
        component: AddCapitalComponent,
        canActivate: [departmentGuard],
        data: { allowedDepartments: ['Admin'] }
    }
];

@NgModule({
    imports: [RouterModule.forChild(routes)],
    exports: [RouterModule]
})
export class CapitalsRoutingModule { }
