import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Offer2DetailsComponent } from './offer2-details.component';

describe('Offer2DetailsComponent', () => {
  let component: Offer2DetailsComponent;
  let fixture: ComponentFixture<Offer2DetailsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [Offer2DetailsComponent]
    });
    fixture = TestBed.createComponent(Offer2DetailsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
