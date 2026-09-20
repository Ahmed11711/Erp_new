import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { SYSTEM_LOCK_DEFAULT_MESSAGE, SystemLockService, SystemLockUser } from './system-lock.service';

@Component({
  selector: 'app-system-lock-dialog',
  standalone: true,
  imports: [CommonModule, FormsModule, MatDialogModule, MatButtonModule, MatIconModule],
  templateUrl: './system-lock-dialog.component.html',
  styleUrls: ['./system-lock-dialog.component.css']
})
export class SystemLockDialogComponent implements OnInit {
  readonly defaultMessage = SYSTEM_LOCK_DEFAULT_MESSAGE;
  message = SYSTEM_LOCK_DEFAULT_MESSAGE;
  showMessage = true;
  users: SystemLockUser[] = [];
  selectedIds = new Set<number>();
  userQuery = '';
  saving = false;
  loadingUsers = false;
  errorMessage = '';

  constructor(
    public lock: SystemLockService,
    private dialogRef: MatDialogRef<SystemLockDialogComponent>,
  ) {}

  ngOnInit(): void {
    this.message = this.lock.message || SYSTEM_LOCK_DEFAULT_MESSAGE;
    this.showMessage = this.lock.showMessage;
    this.lock.snapshot.exempt_user_ids.forEach((id) => this.selectedIds.add(id));
    if (!this.lock.canLock) {
      return;
    }
    this.loadingUsers = true;
    this.lock.fetchUsers().subscribe({
      next: (users) => {
        this.users = users;
        this.loadingUsers = false;
      },
      error: () => {
        this.loadingUsers = false;
        this.errorMessage = 'تعذر تحميل قائمة المستخدمين.';
      }
    });
  }

  get filteredUsers(): SystemLockUser[] {
    const q = this.userQuery.trim().toLowerCase();
    if (!q) {
      return this.users;
    }
    return this.users.filter((u) => {
      const hay = `${u.name || ''} ${u.email || ''} ${u.department || ''}`.toLowerCase();
      return hay.includes(q);
    });
  }

  isSelected(id: number): boolean {
    return this.selectedIds.has(id);
  }

  toggleUser(id: number, checked: boolean): void {
    if (checked) {
      this.selectedIds.add(id);
    } else {
      this.selectedIds.delete(id);
    }
  }

  close(): void {
    this.dialogRef.close();
  }

  save(locked: boolean): void {
    if (this.saving) {
      return;
    }
    if (locked && !this.lock.canLock) {
      this.errorMessage = 'غير مسموح بقفل النظام.';
      return;
    }
    if (!locked && !this.lock.canUnlock) {
      this.errorMessage = 'غير مسموح بإعادة تشغيل النظام.';
      return;
    }
    this.saving = true;
    this.errorMessage = '';
    this.lock.updateLock({
      locked,
      message: this.message.trim() || SYSTEM_LOCK_DEFAULT_MESSAGE,
      show_message: this.showMessage,
      exempt_user_ids: Array.from(this.selectedIds),
    }).subscribe({
      next: () => {
        this.saving = false;
        this.dialogRef.close(true);
      },
      error: (err) => {
        this.saving = false;
        this.errorMessage = err?.error?.message || 'تعذر حفظ حالة قفل النظام.';
      }
    });
  }
}
