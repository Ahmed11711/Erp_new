import { Injectable } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

@Injectable({ providedIn: 'root' })
export class ToastService {
  constructor(private snackBar: MatSnackBar) {}

  show(message: string, type: ToastType = 'info', duration = 3500): void {
    const panelClass = `toast-${type}`;
    this.snackBar.open(message, '✕', {
      duration,
      horizontalPosition: 'start',
      verticalPosition: 'bottom',
      panelClass: [panelClass],
      direction: 'rtl',
    });
  }

  success(message: string, duration = 3000): void {
    this.show(message, 'success', duration);
  }

  error(message: string, duration = 5000): void {
    this.show(message, 'error', duration);
  }

  warning(message: string, duration = 4000): void {
    this.show(message, 'warning', duration);
  }

  info(message: string, duration = 3500): void {
    this.show(message, 'info', duration);
  }
}
