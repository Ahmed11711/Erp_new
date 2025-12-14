import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ProductSalesComponent } from './product-sales.component';

describe('ProductSalesComponent', () => {
  let component: ProductSalesComponent;
  let fixture: ComponentFixture<ProductSalesComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ProductSalesComponent]
    });
    fixture = TestBed.createComponent(ProductSalesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
