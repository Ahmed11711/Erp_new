import { NgModule } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';

import { ManufacturingRoutingModule } from './manufacturing-routing.module';
import { AddRecipeComponent } from './add-recipe/add-recipe.component';
import { ManufacturingRecipesComponent } from './manufacturing-recipes/manufacturing-recipes.component';
import { SharedModule } from '../shared/shared.module';
import { AutocompleteLibModule } from 'angular-ng-autocomplete';
import { ManufacturingConfirmationComponent } from './manufacturing-confirmation/manufacturing-confirmation.component';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatInputModule } from '@angular/material/input';
import { ManufacturingOrdersComponent } from './manufacturing-orders/manufacturing-orders.component';
import { ManufacturingAdditionsComponent } from './manufacturing-additions/manufacturing-additions.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';


@NgModule({
  declarations: [
    AddRecipeComponent,
    ManufacturingRecipesComponent,
    ManufacturingConfirmationComponent,
    ManufacturingOrdersComponent,
    ManufacturingAdditionsComponent
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
    MatNativeDateModule,
    MatInputModule,
  ],
  providers: [
    DatePipe
  ]
})
export class ManufacturingModule { }
