import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ManufacturingRecipesComponent } from './manufacturing-recipes.component';

describe('ManufacturingRecipesComponent', () => {
  let component: ManufacturingRecipesComponent;
  let fixture: ComponentFixture<ManufacturingRecipesComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ManufacturingRecipesComponent]
    });
    fixture = TestBed.createComponent(ManufacturingRecipesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
