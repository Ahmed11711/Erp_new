import { NgModule } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';

import { ManufacturingRoutingModule } from './manufacturing-routing.module';
import { AddRecipeComponent } from './add-recipe/add-recipe.component';
import { ManufacturingRecipesComponent } from './manufacturing-recipes/manufacturing-recipes.component';
import { ManufacturingBomListComponent } from './manufacturing-bom-list/manufacturing-bom-list.component';
import { SharedModule } from '../shared/shared.module';
import { AutocompleteLibModule } from 'angular-ng-autocomplete';
import { ManufacturingConfirmationComponent } from './manufacturing-confirmation/manufacturing-confirmation.component';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatInputModule } from '@angular/material/input';
import { MatExpansionModule } from '@angular/material/expansion';
import { ManufacturingOrdersComponent } from './manufacturing-orders/manufacturing-orders.component';
import { ManufacturingAdditionsComponent } from './manufacturing-additions/manufacturing-additions.component';
import { ItemsWithoutRecipeReportComponent } from './items-without-recipe-report/items-without-recipe-report.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';


@NgModule({
  declarations: [
    AddRecipeComponent,
    ManufacturingRecipesComponent,
    ManufacturingBomListComponent,
    ManufacturingConfirmationComponent,
    ManufacturingOrdersComponent,
    ManufacturingAdditionsComponent,
    ItemsWithoutRecipeReportComponent
  ],
  imports: [
    CommonModule,
    ManufacturingRoutingModule,
    SharedModule,
    ReactiveFormsModule,
    FormsModule,
    AutocompleteLibModule,
    MatFormFieldModule,
    MatDatepickerModule,
    MatInputModule,
    MatExpansionModule,
  ],
  providers: [
    DatePipe
  ]
})
export class ManufacturingModule { }
