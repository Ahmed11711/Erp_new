import { Component, Inject } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';

export interface ConfirmDialogData {
  title: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  type?: 'danger' | 'warning' | 'info';
}

@Component({
  selector: 'app-confirm-dialog',
  template: `
    <div class="confirm-dialog" dir="rtl">
      <div class="confirm-dialog__icon" [ngClass]="'confirm-dialog__icon--' + (data.type || 'warning')">
        <i class="fas" [ngClass]="{
          'fa-trash-alt': data.type === 'danger',
          'fa-exclamation-triangle': data.type === 'warning' || !data.type,
          'fa-info-circle': data.type === 'info'
        }"></i>
      </div>
      <h3 class="confirm-dialog__title">{{ data.title }}</h3>
      <p class="confirm-dialog__message">{{ data.message }}</p>
      <div class="confirm-dialog__actions">
        <button class="btn btn-outline-secondary" (click)="onCancel()">
          {{ data.cancelText || 'إلغاء' }}
        </button>
        <button class="btn" [ngClass]="{
          'btn-danger': data.type === 'danger',
          'btn-warning': data.type === 'warning' || !data.type,
          'btn-primary': data.type === 'info'
        }" (click)="onConfirm()">
          {{ data.confirmText || 'تأكيد' }}
        </button>
      </div>
    </div>
  `,
  styles: [`
    .confirm-dialog {
      text-align: center;
      padding: 24px 32px;
      min-width: 320px;
      max-width: 420px;
    }
    .confirm-dialog__icon {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 16px;
      font-size: 28px;
    }
    .confirm-dialog__icon--danger {
      background: #fef2f2;
      color: #dc3545;
    }
    .confirm-dialog__icon--warning {
      background: #fffbeb;
      color: #f59e0b;
    }
    .confirm-dialog__icon--info {
      background: #eff6ff;
      color: #3b82f6;
    }
    .confirm-dialog__title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 8px;
      color: #1f2937;
    }
    .confirm-dialog__message {
      font-size: 14px;
      color: #6b7280;
      margin-bottom: 24px;
      line-height: 1.6;
    }
    .confirm-dialog__actions {
      display: flex;
      gap: 12px;
      justify-content: center;
    }
    .confirm-dialog__actions .btn {
      min-width: 100px;
      padding: 8px 20px;
      border-radius: 8px;
      font-weight: 500;
    }
  `]
})
export class ConfirmDialogComponent {
  constructor(
    public dialogRef: MatDialogRef<ConfirmDialogComponent>,
    @Inject(MAT_DIALOG_DATA) public data: ConfirmDialogData
  ) {}

  onCancel(): void {
    this.dialogRef.close(false);
  }

  onConfirm(): void {
    this.dialogRef.close(true);
  }
}
