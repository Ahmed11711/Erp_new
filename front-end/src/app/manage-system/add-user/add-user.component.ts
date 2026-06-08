import { Component, OnInit } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { UserService } from '../services/user.service';
import { Router } from '@angular/router';
import { environment } from 'src/env/env';

@Component({
  selector: 'app-add-user',
  templateUrl: './add-user.component.html',
  styleUrls: ['./add-user.component.css']
})
export class AddUserComponent implements OnInit {

  errorform = false;
  errorMessage!: string;
  rolesCatalog: Array<{ id: number; name: string }> = [];

  form: FormGroup = new FormGroup({
    name: new FormControl(null, [Validators.required]),
    department: new FormControl(null, [Validators.required]),
    email: new FormControl(null, [Validators.required]),
    password: new FormControl(null, [Validators.required]),
    role_ids: new FormControl<number[]>([]),
  });

  constructor(
    private userService: UserService,
    private route: Router,
    private http: HttpClient,
  ) {}

  ngOnInit(): void {
    this.http.get<{ roles: Array<{ id: number; name: string }> }>(`${environment.Url}/rbac/roles`).subscribe({
      next: (res) => {
        this.rolesCatalog = res.roles || [];
      },
      error: () => {
        /* صامت — لا يمنع إنشاء مستخدم */
      },
    });
  }

  submitform(): void {
    if (!this.form.valid) {
      return;
    }
    const raw = this.form.value;
    const payload = {
      name: raw.name,
      email: raw.email,
      password: raw.password,
      department: raw.department,
      role_ids: Array.isArray(raw.role_ids) ? raw.role_ids : [],
    };

    this.userService.add(payload).subscribe({
      next: (result: any) => {
        if (result?.user || result?.message || result?.access_token) {
          this.route.navigate(['/dashboard/system/users']);
        }
      },
      error: (error: any) => {
        if (error.status === 422) {
          this.errorform = true;
          const msg = error.error?.message;
          const errs = error.error?.errors;
          if (typeof msg === 'string') {
            this.errorMessage = msg.includes('email') ? 'هذا الإيميل مستخدم مسبقاً أو غير صالح' : msg;
          } else if (errs?.email) {
            this.errorMessage = Array.isArray(errs.email) ? errs.email[0] : String(errs.email);
          } else {
            this.errorMessage = 'بيانات غير صالحة';
          }
        }
      },
    });
  }
}
