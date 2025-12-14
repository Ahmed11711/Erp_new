import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PartCollectComponent } from './part-collect.component';

describe('PartCollectComponent', () => {
  let component: PartCollectComponent;
  let fixture: ComponentFixture<PartCollectComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [PartCollectComponent]
    });
    fixture = TestBed.createComponent(PartCollectComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
