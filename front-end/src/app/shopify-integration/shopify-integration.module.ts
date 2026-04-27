import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ShopifyIntegrationRoutingModule } from './shopify-integration-routing.module';
import { ShopifyIntegrationDashboardComponent } from './shopify-integration-dashboard/shopify-integration-dashboard.component';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatPaginatorModule } from '@angular/material/paginator';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTabsModule } from '@angular/material/tabs';
import { MatInputModule } from '@angular/material/input';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatTooltipModule } from '@angular/material/tooltip';

@NgModule({
  declarations: [ShopifyIntegrationDashboardComponent],
  imports: [
    CommonModule,
    FormsModule,
    ShopifyIntegrationRoutingModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatPaginatorModule,
    MatChipsModule,
    MatProgressSpinnerModule,
    MatTabsModule,
    MatInputModule,
    MatFormFieldModule,
    MatTooltipModule,
  ],
})
export class ShopifyIntegrationModule {}
