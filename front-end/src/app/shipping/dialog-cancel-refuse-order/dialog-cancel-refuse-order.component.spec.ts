import { ComponentFixture, TestBed } from '@angular/core/testing';

import { DialogCancelRefuseOrderComponent } from './dialog-cancel-refuse-order.component';

describe('DialogCancelRefuseOrderComponent', () => {
  let component: DialogCancelRefuseOrderComponent;
  let fixture: ComponentFixture<DialogCancelRefuseOrderComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [DialogCancelRefuseOrderComponent]
    });
    fixture = TestBed.createComponent(DialogCancelRefuseOrderComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
