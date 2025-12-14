import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ReceiveMoneyComponent } from './receive-money.component';

describe('ReceiveMoneyComponent', () => {
  let component: ReceiveMoneyComponent;
  let fixture: ComponentFixture<ReceiveMoneyComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ReceiveMoneyComponent]
    });
    fixture = TestBed.createComponent(ReceiveMoneyComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
