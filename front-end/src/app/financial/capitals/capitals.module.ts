import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CapitalsRoutingModule } from './capitals-routing.module';
import { ListCapitalsComponent } from './list-capitals.component';
import { AddCapitalComponent } from './add-capital.component';
import { SharedModule } from 'src/app/shared/shared.module';
import { FormsModule } from '@angular/forms';
import { NgxPaginationModule } from 'ngx-pagination';

@NgModule({
    declarations: [
        ListCapitalsComponent,
        AddCapitalComponent
    ],
    imports: [
        CommonModule,
        CapitalsRoutingModule,
        SharedModule,
        FormsModule,
        NgxPaginationModule
    ]
})
export class CapitalsModule { }
