import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute, ParamMap } from '@angular/router';
import { AuthService } from 'src/app/auth/auth.service';
import { UserService } from '../services/user.service';
import { environment } from 'src/env/env';
import Swal from 'sweetalert2';

type AccessChoice = 'inherit' | 'allow' | 'deny';

interface MatrixRow {
  id: number;
  name: string;
  slug: string;
  module?: string;
  state: string;
  effective: boolean;
  choice: AccessChoice;
}

@Component({
  selector: 'app-powers',
  templateUrl: './powers.component.html',
  styleUrls: ['./powers.component.css']
})
export class PowersComponent implements OnInit {

  products: any[] = [];
  catword = 'name';

  selectedUserId: number | null = null;
  selectedUserName = '';
  loading = false;
  saving = false;

  roleIds: number[] = [];
  rolesCatalog: Array<{ id: number; name: string; slug?: string }> = [];

  editableGroups: Record<string, MatrixRow[]> = {};

  /** تبويب مصفوفة الصلاحيات (مثل الواجهة المرجعية) */
  matrixTab: 'all' | 'inherited' | 'direct_allow' | 'direct_deny' | 'neutral' = 'all';

  selectedUserDept = '';

  message = '';
  error = '';

  constructor(
    private userService: UserService,
    private http: HttpClient,
    private route: ActivatedRoute,
    private auth: AuthService,
  ) {}

  /** رسالة عربية مفيدة حسب رمز الاستجابة */
  private httpErr(err: any, ctx: string): string {
    const st = err?.status;
    const apiMsg = typeof err?.error?.message === 'string' ? err.error.message.trim() : '';
    const hint403 =
      ' — الوصول مرفوض (403): تأكد أن قسم المستخدم في قاعدة البيانات هو «Admin» (بدون مسافات زائدة) ثم أعد تسجيل الدخول.';
    const hint401 = ' — انتهت الجلسة (401): سجّل الدخول من جديد.';
    const hint0 =
      ' — لا يوجد اتصال بالخادم: تأكد أن Laravel يعمل وأن الرابط في src/env/env.ts يطابق العنوان (مثال artisan serve: http://127.0.0.1:8000/api أو مسار public مع XAMPP).';

    if (apiMsg) {
      return `${ctx}: ${apiMsg}`;
    }
    if (st === 403) {
      return `${ctx}${hint403}`;
    }
    if (st === 401) {
      return `${ctx}${hint401}`;
    }
    if (st === 0 || st === undefined) {
      return `${ctx}${hint0}`;
    }
    return `${ctx} (HTTP ${st}).`;
  }

  ngOnInit(): void {
    this.http.get<{ roles: Array<{ id: number; name: string; slug?: string }> }>(`${environment.Url}/rbac/roles`).subscribe({
      next: (res) => {
        this.rolesCatalog = res.roles || [];
      },
      error: (err) => {
        this.error = this.httpErr(err, 'تعذر تحميل قائمة الأدوار');
      },
    });

    this.userService.data().subscribe({
      next: (list) => {
        this.products = list;
        this.trySelectFromQuery(this.route.snapshot.queryParamMap);
      },
      error: (err) => {
        this.error = this.httpErr(err, 'تعذر تحميل المستخدمين');
      },
    });

    this.route.queryParamMap.subscribe((map: ParamMap) => {
      if (!this.products.length) {
        return;
      }
      this.trySelectFromQuery(map);
    });
  }

  private trySelectFromQuery(map: ParamMap): void {
    const raw = map.get('userId');
    if (!raw) {
      return;
    }
    const id = +raw;
    if (!id || Number.isNaN(id)) {
      return;
    }
    const u = this.products.find((x: any) => x.id === id);
    if (u) {
      this.productChange(u);
    }
  }

  productChange(e: any): void {
    if (!e?.id) {
      return;
    }
    this.selectedUserId = e.id;
    this.selectedUserName = e.name || '';
    this.selectedUserDept = e.department || '';
    this.matrixTab = 'all';
    this.message = '';
    this.error = '';
    this.loadMatrix();
  }

  resetData(): void {
    this.selectedUserId = null;
    this.selectedUserName = '';
    this.selectedUserDept = '';
    this.roleIds = [];
    this.editableGroups = {};
    this.message = '';
  }

  stateToChoice(state: string): AccessChoice {
    if (state === 'denied') {
      return 'deny';
    }
    if (state === 'allowed_override') {
      return 'allow';
    }
    return 'inherit';
  }

  loadMatrix(): void {
    if (!this.selectedUserId) {
      return;
    }
    this.loading = true;
    this.http.get<any>(`${environment.Url}/rbac/users/${this.selectedUserId}/matrix`).subscribe({
      next: (res) => {
        this.roleIds = [...(res.assigned_role_ids || [])];
        this.editableGroups = {};
        const groups = res.groups || {};
        for (const mod of Object.keys(groups)) {
          const rows = groups[mod] as any[];
          this.editableGroups[mod] = rows.map((r) => ({
            ...r,
            choice: this.stateToChoice(r.state),
          }));
        }
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.error = this.httpErr(err, 'تعذر تحميل مصفوفة الصلاحيات');
      },
    });
  }

  saveAccess(): void {
    if (!this.selectedUserId) {
      return;
    }
    const overrides: Array<{ permission_id: number; type: string }> = [];
    for (const rows of Object.values(this.editableGroups)) {
      for (const row of rows) {
        if (row.choice === 'allow') {
          overrides.push({ permission_id: row.id, type: 'allow' });
        }
        if (row.choice === 'deny') {
          overrides.push({ permission_id: row.id, type: 'deny' });
        }
      }
    }

    this.saving = true;
    this.http.put(`${environment.Url}/rbac/users/${this.selectedUserId}/access`, {
      role_ids: this.roleIds,
      overrides,
    }).subscribe({
      next: () => {
        this.saving = false;
        this.message = 'تم حفظ الأدوار والصلاحيات بنجاح';
        Swal.fire({ icon: 'success', title: 'تم الحفظ', timer: 1800, showConfirmButton: false });
        this.auth.syncSessionPermissionsFromServer().subscribe();
        this.loadMatrix();
      },
      error: (err) => {
        this.saving = false;
        const msg = err?.error?.message || 'فشل الحفظ';
        Swal.fire({ icon: 'error', title: String(msg) });
      },
    });
  }

  openAddPermission(): void {
    Swal.fire({
      title: 'إضافة صلاحية جديدة',
      html: `
        <label class="swal2-label text-start d-block mb-1">الاسم المعروض</label>
        <input id="p-name" class="swal2-input" placeholder="مثال: تصدير الطلبات">
        <label class="swal2-label text-start d-block mb-1 mt-2">المعرّف slug (اختياري)</label>
        <input id="p-slug" class="swal2-input" placeholder="مثال: orders.export">
        <label class="swal2-label text-start d-block mb-1 mt-2">الوحدة module (اختياري)</label>
        <input id="p-module" class="swal2-input" placeholder="مثال: orders">
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'إنشاء',
      cancelButtonText: 'إلغاء',
      preConfirm: () => {
        const name = (document.getElementById('p-name') as HTMLInputElement)?.value?.trim();
        const slug = (document.getElementById('p-slug') as HTMLInputElement)?.value?.trim();
        const module = (document.getElementById('p-module') as HTMLInputElement)?.value?.trim();
        if (!name) {
          Swal.showValidationMessage('اسم الصلاحية مطلوب');
          return false as any;
        }
        return { name, slug: slug || undefined, module: module || undefined };
      },
    }).then((result) => {
      if (!result.isConfirmed || !result.value) {
        return;
      }
      this.http.post(`${environment.Url}/rbac/permissions`, result.value).subscribe({
        next: () => {
          Swal.fire({ icon: 'success', title: 'تم إنشاء الصلاحية', timer: 1600, showConfirmButton: false });
          if (this.selectedUserId) {
            this.loadMatrix();
          }
        },
        error: (err) => {
          const msg = err?.error?.message || err?.error?.errors ? JSON.stringify(err.error.errors) : 'فشل الإنشاء';
          Swal.fire({ icon: 'error', title: String(msg) });
        },
      });
    });
  }

  stateBadgeClass(state: string): string {
    switch (state) {
      case 'inherited_role':
        return 'bg-info';
      case 'inherited_department':
        return 'bg-secondary';
      case 'allowed_override':
        return 'bg-success';
      case 'denied':
        return 'bg-danger';
      default:
        return 'bg-light text-dark';
    }
  }

  stateLabel(state: string): string {
    const map: Record<string, string> = {
      inherited_role: 'من الدور',
      inherited_department: 'من قالب القسم',
      allowed_override: 'مسموح يدوياً',
      denied: 'مرفوض يدوياً',
      neutral: 'بدون وراثة',
    };
    return map[state] || state;
  }

  matchesMatrixTab(row: MatrixRow): boolean {
    switch (this.matrixTab) {
      case 'all':
        return true;
      case 'inherited':
        return row.choice === 'inherit'
          && (row.state === 'inherited_role' || row.state === 'inherited_department');
      case 'direct_allow':
        return row.choice === 'allow';
      case 'direct_deny':
        return row.choice === 'deny';
      case 'neutral':
        return row.choice === 'inherit' && row.state === 'neutral';
      default:
        return true;
    }
  }

  filteredRows(module: string): MatrixRow[] {
    return (this.editableGroups[module] || []).filter((r) => this.matchesMatrixTab(r));
  }

  filteredModulesKeys(): string[] {
    return this.modulesKeys().filter((m) => this.filteredRows(m).length > 0);
  }

  rowVisualClass(row: MatrixRow): Record<string, boolean> {
    return {
      'pwr-row': true,
      'pwr-row--deny': row.choice === 'deny',
      'pwr-row--allow': row.choice === 'allow',
      'pwr-row--inherit':
        row.choice === 'inherit'
        && (row.state === 'inherited_role' || row.state === 'inherited_department'),
      'pwr-row--neutral': row.choice === 'inherit' && row.state === 'neutral',
    };
  }

  modulesKeys(): string[] {
    return Object.keys(this.editableGroups).sort();
  }
}
