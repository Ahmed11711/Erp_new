import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CategoriesRoutingModule } from './categories-routing.module';
import { UnitsComponent } from './units/units.component';
import {MatDialogModule} from '@angular/material/dialog';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { FormsModule } from '@angular/forms';
import { ProductionComponent } from './production/production.component';
import { AddCategoryComponent } from './add-category/add-category.component';
import { ListCategoriesComponent } from './list-categories/list-categories.component';
import { MatPaginatorModule} from '@angular/material/paginator';
import {NgxPaginationModule} from 'ngx-pagination'; // <-- import the module
import {MatAutocompleteModule} from '@angular/material/autocomplete';
import {MatFormFieldModule} from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { ReactiveFormsModule} from '@angular/forms';
 import {SharedModule } from '../shared/shared.module'
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { EditCategoryComponent } from './edit-category/edit-category.component';
import { AutocompleteLibModule } from 'angular-ng-autocomplete';

@NgModule({
  declarations: [
    UnitsComponent,
    ProductionComponent,
    AddCategoryComponent,
    ListCategoriesComponent,
    EditCategoryComponent,
  ],
  imports: [
    SharedModule,
    MatInputModule,
    ReactiveFormsModule,
    MatFormFieldModule,
    MatAutocompleteModule,
    NgxPaginationModule,
    MatPaginatorModule,
    CommonModule,
    FormsModule,
    MatDialogModule,
    MatSlideToggleModule,
    CategoriesRoutingModule,
    MatMenuModule,
    MatIconModule,
    AutocompleteLibModule
  ]
})
export class CategoriesModule { }
