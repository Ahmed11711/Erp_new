import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PriceOffer2Component } from './price-offer2.component';

describe('PriceOffer2Component', () => {
  let component: PriceOffer2Component;
  let fixture: ComponentFixture<PriceOffer2Component>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [PriceOffer2Component]
    });
    fixture = TestBed.createComponent(PriceOffer2Component);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
