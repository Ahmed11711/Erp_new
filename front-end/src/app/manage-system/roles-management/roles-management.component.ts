import { Component, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { AuthService } from 'src/app/auth/auth.service';
import { environment } from 'src/env/env';
import { rbacModuleLabelAr, rbacPermissionLabelAr } from './rbac-display-labels';

interface RbacRoleRow {
  id: number;
  name: string;
  slug?: string;
  description?: string | null;
  permissions_count?: number;
}

@Component({
  selector: 'app-roles-management',
  templateUrl: './roles-management.component.html',
  styleUrls: ['./roles-management.component.css']
})
export class RolesManagementComponent implements OnInit {

  loading = false;
  saving = false;
  savingMeta = false;
  roles: RbacRoleRow[] = [];
  selectedRoleId: number | null = null;
  roleDetail: any = null;

  /** مسودات تبويب معلومات الدور */
  roleNameDraft = '';
  roleDescriptionDraft = '';

  permissionGroups: Record<string, Array<{ id: number; name: string; slug: string; module?: string }>> = {};
  search = '';
  roleSearch = '';
  assigned = new Set<number>();

  /** لوحات الصلاحيات مفتوحة أو مطوية دفعة واحدة */
  panelsExpanded = true;

  message = '';
  error = '';

  constructor(private http: HttpClient, private auth: AuthService) {}

  moduleDisplayLabel(moduleKey: string): string {
    return rbacModuleLabelAr(moduleKey);
  }

  permissionDisplayLabel(slug: string, fallbackName: string): string {
    return rbacPermissionLabelAr(slug, fallbackName);
  }

  private httpErr(err: any, ctx: string): string {
    const st = err?.status;
    const apiMsg = typeof err?.error?.message === 'string' ? err.error.message.trim() : '';
    const hint403 =
      ' — الوصول مرفوض (403): تأكد أن قسم المستخدم هو Admin وأعد تسجيل الدخول؛ وراجع عنوان API في env.ts.';
    const hint401 = ' — انتهت الجلسة (401): سجّل الدخول مجدداً.';
    const hint0 =
      ' — لا اتصال بالخادم: تحقق من تشغيل Laravel ومن أن Url في env.ts صحيح (8000 أو مسار public لـ XAMPP).';

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
    this.reloadRoles();
    this.loadPermissionsCatalog();
  }

  reloadRoles(): void {
    this.loading = true;
    this.http.get<{ roles: RbacRoleRow[] }>(`${environment.Url}/rbac/roles`).subscribe({
      next: (res) => {
        this.roles = res.roles || [];
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.error = this.httpErr(err, 'تعذر تحميل قائمة الأدوار');
      }
    });
  }

  loadPermissionsCatalog(): void {
    this.http.get<{ grouped: Record<string, any[]> }>(`${environment.Url}/rbac/permissions`).subscribe({
      next: (res) => {
        this.permissionGroups = res.grouped || {};
      },
      error: (err) => {
        const piece = this.httpErr(err, 'تعذر تحميل قائمة الصلاحيات');
        this.error = this.error ? `${this.error} — ${piece}` : piece;
      }
    });
  }

  filteredRoles(): RbacRoleRow[] {
    const q = this.roleSearch.trim().toLowerCase();
    if (!q) {
      return this.roles;
    }
    return this.roles.filter(
      (r) =>
        (r.name || '').toLowerCase().includes(q) ||
        (r.slug || '').toLowerCase().includes(q) ||
        (r.description || '').toLowerCase().includes(q),
    );
  }

  selectRole(id: number): void {
    this.selectedRoleId = id;
    this.message = '';
    this.error = '';
    this.http.get<any>(`${environment.Url}/rbac/roles/${id}`).subscribe({
      next: (role) => {
        this.roleDetail = role;
        this.assigned = new Set<number>((role.permissions || []).map((p: any) => p.id));
        this.roleNameDraft = role.name || '';
        this.roleDescriptionDraft = role.description || '';
      },
      error: (err) => {
        this.error = this.httpErr(err, 'تعذر تحميل تفاصيل الدور');
      }
    });
  }

  togglePermission(id: number, checked: boolean): void {
    if (checked) {
      this.assigned.add(id);
    } else {
      this.assigned.delete(id);
    }
  }

  isAssigned(id: number): boolean {
    return this.assigned.has(id);
  }

  toggleModule(module: string, checked: boolean): void {
    const rows = this.filteredGroups()[module] || [];
    for (const p of rows) {
      this.togglePermission(p.id, checked);
    }
  }

  moduleAllChecked(module: string): boolean {
    const rows = this.filteredGroups()[module] || [];
    if (!rows.length) {
      return false;
    }
    return rows.every((p) => this.assigned.has(p.id));
  }

  modulePartial(module: string): boolean {
    const rows = this.filteredGroups()[module] || [];
    const n = rows.filter((p) => this.assigned.has(p.id)).length;
    return n > 0 && n < rows.length;
  }

  globalToggle(checked: boolean): void {
    const groups = this.filteredGroups();
    Object.keys(groups).forEach((m) => this.toggleModule(m, checked));
  }

  expandAll(): void {
    this.panelsExpanded = true;
  }

  collapseAll(): void {
    this.panelsExpanded = false;
  }

  filteredGroups(): Record<string, Array<{ id: number; name: string; slug: string; module?: string }>> {
    const q = this.search.trim().toLowerCase();
    const out: Record<string, typeof this.permissionGroups[string]> = {};
    for (const [module, list] of Object.entries(this.permissionGroups)) {
      const filtered = !q
        ? list
        : list.filter((p) =>
            (p.name || '').toLowerCase().includes(q) ||
            (p.slug || '').toLowerCase().includes(q) ||
            (module || '').toLowerCase().includes(q)
          );
      if (filtered.length) {
        out[module] = filtered as any;
      }
    }
    return out;
  }

  totalCatalogPermissions(): number {
    let n = 0;
    for (const list of Object.values(this.permissionGroups)) {
      n += list.length;
    }
    return n;
  }

  /** عدد الصلاحيات المفعّلة لهذا الدور ضمن الكتالوج الكامل */
  grantedInCatalog(): number {
    let n = 0;
    for (const list of Object.values(this.permissionGroups)) {
      for (const p of list) {
        if (this.assigned.has(p.id)) {
          n++;
        }
      }
    }
    return n;
  }

  notGrantedInCatalog(): number {
    return Math.max(0, this.totalCatalogPermissions() - this.grantedInCatalog());
  }

  saveRolePermissions(): void {
    if (!this.selectedRoleId) {
      return;
    }
    this.saving = true;
    this.message = '';
    this.error = '';
    const ids = Array.from(this.assigned.values());
    this.http.put<{ ok: boolean }>(`${environment.Url}/rbac/roles/${this.selectedRoleId}/permissions`, {
      permission_ids: ids
    }).subscribe({
      next: () => {
        this.saving = false;
        this.message = 'تم حفظ صلاحيات الدور';
        // تحديث الكوكي والقائمة الجانبية فوراً حسب الصلاحيات الفعلية للمستخدم الحالي
        this.auth.syncSessionPermissionsFromServer().subscribe({ complete: () => {} });
        this.reloadRoles();
        if (this.selectedRoleId) {
          this.selectRole(this.selectedRoleId);
        }
      },
      error: (err) => {
        this.saving = false;
        this.error = err?.error?.message || 'فشل الحفظ';
      }
    });
  }

  saveRoleInfo(): void {
    if (!this.selectedRoleId) {
      return;
    }
    this.savingMeta = true;
    this.message = '';
    this.error = '';
    this.http.put(`${environment.Url}/rbac/roles/${this.selectedRoleId}`, {
      name: this.roleNameDraft.trim(),
      description: this.roleDescriptionDraft.trim() || null,
    }).subscribe({
      next: () => {
        this.savingMeta = false;
        this.message = 'تم تحديث معلومات الدور';
        this.reloadRoles();
        if (this.selectedRoleId) {
          this.selectRole(this.selectedRoleId);
        }
      },
      error: (err) => {
        this.savingMeta = false;
        this.error = err?.error?.message || 'فشل تحديث معلومات الدور';
      }
    });
  }

  createRole(): void {
    const name = window.prompt('اسم الدور الجديد', '');
    if (!name?.trim()) {
      return;
    }
    const description = window.prompt('وصف الدور (اختياري)', '') || undefined;
    this.http.post(`${environment.Url}/rbac/roles`, { name: name.trim(), description }).subscribe({
      next: (role: any) => {
        this.message = 'تم إنشاء الدور';
        this.reloadRoles();
        if (role?.id) {
          this.selectRole(role.id);
        }
      },
      error: (err) => {
        this.error = err?.error?.message || 'فشل إنشاء الدور';
      }
    });
  }

  exportRoleJson(): void {
    if (!this.roleDetail) {
      return;
    }
    const payload = {
      exported_at: new Date().toISOString(),
      role: this.roleDetail,
      permission_ids: Array.from(this.assigned.values()),
    };
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `role-${this.roleDetail.slug || this.roleDetail.id}.json`;
    a.click();
    URL.revokeObjectURL(url);
    this.message = 'تم تصدير ملف JSON';
  }

  cloneRole(): void {
    if (!this.selectedRoleId || !this.roleDetail) {
      return;
    }
    const name = window.prompt('اسم الدور الجديد', `${this.roleDetail.name} (نسخة)`);
    if (!name || !name.trim()) {
      return;
    }
    this.http.post(`${environment.Url}/rbac/roles/${this.selectedRoleId}/clone`, { name: name.trim() }).subscribe({
      next: () => {
        this.message = 'تم استنساخ الدور';
        this.reloadRoles();
      },
      error: () => {
        this.error = 'فشل الاستنساخ';
      }
    });
  }

  deleteRole(): void {
    if (!this.selectedRoleId || !this.roleDetail) {
      return;
    }
    if (!window.confirm(`حذف الدور "${this.roleDetail.name}" نهائياً؟`)) {
      return;
    }
    this.http.delete(`${environment.Url}/rbac/roles/${this.selectedRoleId}`).subscribe({
      next: () => {
        this.selectedRoleId = null;
        this.roleDetail = null;
        this.assigned.clear();
        this.roleNameDraft = '';
        this.roleDescriptionDraft = '';
        this.message = 'تم حذف الدور';
        this.reloadRoles();
      },
      error: () => {
        this.error = 'تعذر الحذف — قد يكون الدور مرتبطاً بمستخدمين';
      }
    });
  }

  modulesCount(): number {
    return Object.keys(this.filteredGroups()).length;
  }

  assignedVisibleCount(): number {
    let n = 0;
    for (const list of Object.values(this.filteredGroups())) {
      n += list.filter((p) => this.assigned.has(p.id)).length;
    }
    return n;
  }
}
