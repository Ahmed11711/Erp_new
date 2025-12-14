import { ComponentFixture, TestBed } from '@angular/core/testing';

import { BanksAcountsComponent } from './banks-acounts.component';

describe('BanksAcountsComponent', () => {
  let component: BanksAcountsComponent;
  let fixture: ComponentFixture<BanksAcountsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [BanksAcountsComponent]
    });
    fixture = TestBed.createComponent(BanksAcountsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
