import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ListBanksComponent } from './list-banks.component';

describe('ListBanksComponent', () => {
  let component: ListBanksComponent;
  let fixture: ComponentFixture<ListBanksComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ListBanksComponent]
    });
    fixture = TestBed.createComponent(ListBanksComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
