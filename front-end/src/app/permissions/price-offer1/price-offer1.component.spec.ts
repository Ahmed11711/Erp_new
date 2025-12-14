import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PriceOffer1Component } from './price-offer1.component';

describe('PriceOffer1Component', () => {
  let component: PriceOffer1Component;
  let fixture: ComponentFixture<PriceOffer1Component>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [PriceOffer1Component]
    });
    fixture = TestBed.createComponent(PriceOffer1Component);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
