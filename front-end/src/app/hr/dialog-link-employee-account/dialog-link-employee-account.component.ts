import { Component, Inject } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { EmployeeService } from '../services/employee.service';
import Swal from 'sweetalert2';

export interface LinkEmployeeAccountDialogData {
  employeeId: number;
  employeeName: string;
  payableTreeAccountId: number | null;
}

@Component({
  selector: 'app-dialog-link-employee-account',
  templateUrl: './dialog-link-employee-account.component.html',
  styleUrls: ['./dialog-link-employee-account.component.css'],
})
export class DialogLinkEmployeeAccountComponent {
  accountId: number | null;
  saving = false;

  constructor(
    private dialogRef: MatDialogRef<DialogLinkEmployeeAccountComponent>,
    @Inject(MAT_DIALOG_DATA) public data: LinkEmployeeAccountDialogData,
    private employeeService: EmployeeService,
  ) {
    this.accountId = data.payableTreeAccountId ?? null;
  }

  save(): void {
    this.saving = true;
    this.employeeService.updatePayableAccount(this.data.employeeId, this.accountId).subscribe({
      next: (res) => {
        this.saving = false;
        this.dialogRef.close(res);
      },
      error: (err) => {
        this.saving = false;
        Swal.fire({
          icon: 'error',
          title: err.error?.message || 'تعذر حفظ الربط',
          timer: 2500,
          showConfirmButton: false,
        });
      },
    });
  }

  cancel(): void {
    this.dialogRef.close();
  }
}
