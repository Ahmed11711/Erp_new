import { ComponentFixture, TestBed } from '@angular/core/testing';

import { IndividualsClientsComponent } from './individuals-clients.component';

describe('IndividualsClientsComponent', () => {
  let component: IndividualsClientsComponent;
  let fixture: ComponentFixture<IndividualsClientsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [IndividualsClientsComponent]
    });
    fixture = TestBed.createComponent(IndividualsClientsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
