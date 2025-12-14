import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Offer1DetailsComponent } from './offer1-details.component';

describe('Offer1DetailsComponent', () => {
  let component: Offer1DetailsComponent;
  let fixture: ComponentFixture<Offer1DetailsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [Offer1DetailsComponent]
    });
    fixture = TestBed.createComponent(Offer1DetailsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
