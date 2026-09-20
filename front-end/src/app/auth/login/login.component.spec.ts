import { ComponentFixture, TestBed } from '@angular/core/testing';
import { RouterTestingModule } from '@angular/router/testing';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../auth.service';
import { LoginComponent } from './login.component';

describe('LoginComponent', () => {
  let component: LoginComponent;
  let fixture: ComponentFixture<LoginComponent>;
  let auth: jasmine.SpyObj<AuthService>;

  beforeEach(() => {
    auth = jasmine.createSpyObj<AuthService>('AuthService', [
      'getToken',
      'consumeSessionExpiredNotice',
      'login',
      'saveTolocalStorage',
    ]);
    auth.getToken.and.returnValue(false);
    auth.consumeSessionExpiredNotice.and.returnValue(false);

    TestBed.configureTestingModule({
      declarations: [LoginComponent],
      imports: [FormsModule, RouterTestingModule.withRoutes([])],
      providers: [{ provide: AuthService, useValue: auth }],
    });
    fixture = TestBed.createComponent(LoginComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('shows a session-expired message when the flag is set', () => {
    auth.consumeSessionExpiredNotice.and.returnValue(true);
    component.ngOnInit();
    expect(component.errorMessage).toBe('انتهت الجلسة. سجّل الدخول من جديد.');
    expect(component.Display).toBeTrue();
  });
});
