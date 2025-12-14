import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';

import { HomeRoutingModule } from './home-routing.module';
import { HomeComponent } from './home.component';
import { MatIconModule } from '@angular/material/icon';
import { SharedModule } from '../shared/shared.module';
import { MatPaginatorModule } from '@angular/material/paginator';
import { NgxPaginationModule } from 'ngx-pagination';
import { CategoriesReportComponent } from './categories-report/categories-report.component';
import { ReportCategoriesChartComponent } from './charts/report-categories-chart/report-categories-chart.component';
import { AngularEditorComponent } from '@kolkov/angular-editor';


@NgModule({
  declarations: [
    HomeComponent,
    CategoriesReportComponent,
    ReportCategoriesChartComponent,
  ],
  imports: [
    CommonModule,
    HomeRoutingModule,
    MatIconModule,
    SharedModule,
    NgxPaginationModule,
    MatPaginatorModule,
  ]
})
export class HomeModule { }
