import { HttpClientTestingModule } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormsModule } from '@angular/forms';

import { CovenantComponent } from './covenant.component';

describe('CovenantComponent', () => {
  let component: CovenantComponent;
  let fixture: ComponentFixture<CovenantComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule, FormsModule],
      declarations: [CovenantComponent]
    });
    fixture = TestBed.createComponent(CovenantComponent);
    component = fixture.componentInstance;
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
