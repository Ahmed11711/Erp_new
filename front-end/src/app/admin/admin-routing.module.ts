import { NgModule } from '@angular/core';
import { RouterModule, Routes } from '@angular/router';
import { AdminOrderComponent } from './admin-order/admin-order.component';
import { departmentGuard } from '../guards/department.guard';
import { assignWhatsAppNumbersGuard } from '../guards/assign-whatsapp-numbers.guard';
import { TrackingsComponent } from './trackings/trackings.component';
import { WhatsappManagementComponent } from './whatsapp-management/whatsapp-management.component';

const routes: Routes = [
  {path:'adminorder', component:AdminOrderComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:'tracking', component:TrackingsComponent,
    canActivate: [departmentGuard], data: {allowedDepartments:['Admin']}
  },
  {path:'whatsapp-management', component:WhatsappManagementComponent,
    canActivate: [assignWhatsAppNumbersGuard],
  },
];

@NgModule({
  imports: [RouterModule.forChild(routes)],
  exports: [RouterModule]
})
export class AdminRoutingModule { }
