import { ComponentFixture, TestBed } from '@angular/core/testing';

import { DescriptionsComponent } from './descriptions.component';

describe('DescriptionsComponent', () => {
  let component: DescriptionsComponent;
  let fixture: ComponentFixture<DescriptionsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [DescriptionsComponent]
    });
    fixture = TestBed.createComponent(DescriptionsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
