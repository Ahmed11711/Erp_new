import { ComponentFixture, TestBed } from '@angular/core/testing';

import { OfferConditionsComponent } from './offer-conditions.component';

describe('OfferConditionsComponent', () => {
  let component: OfferConditionsComponent;
  let fixture: ComponentFixture<OfferConditionsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [OfferConditionsComponent]
    });
    fixture = TestBed.createComponent(OfferConditionsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
