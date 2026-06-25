import { Component, Inject, OnInit } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { SuppliersService } from '../services/suppliers.service';
import { TypesService } from '../services/types.service';
import Swal from 'sweetalert2';

export interface SupplierEditDialogData {
  supplierId: number;
  refreshData?: () => void;
}

@Component({
  selector: 'app-dialog-edit-supplier',
  templateUrl: './dialog-edit-supplier.component.html',
  styleUrls: ['./dialog-edit-supplier.component.css'],
})
export class DialogEditSupplierComponent implements OnInit {
  types: any[] = [];
  loading = true;
  submitting = false;
  errorMessage: string | null = null;

  formModel = {
    supplier_name: '',
    supplier_phone: '',
    supplier_address: '',
    supplier_type: '' as string | number,
    supplier_rate: null as number | null,
    price_rate: null as number | null,
  };

  constructor(
    public dialogRef: MatDialogRef<DialogEditSupplierComponent>,
    @Inject(MAT_DIALOG_DATA) public data: SupplierEditDialogData,
    private suppliers: SuppliersService,
    private typesService: TypesService,
  ) {}

  ngOnInit(): void {
    this.typesService.getTypes().subscribe((res: any) => {
      this.types = res ?? [];
    });

    this.suppliers.getSupplier(this.data.supplierId).subscribe({
      next: (supplier: any) => {
        const typeId =
          supplier?.supplierType?.id ??
          (typeof supplier?.supplier_type === 'object' ? supplier?.supplier_type?.id : supplier?.supplier_type) ??
          '';

        this.formModel = {
          supplier_name: supplier?.supplier_name ?? '',
          supplier_phone: supplier?.supplier_phone ?? '',
          supplier_address: supplier?.supplier_address ?? '',
          supplier_type: typeId !== null && typeId !== undefined ? typeId : '',
          supplier_rate: supplier?.supplier_rate ?? null,
          price_rate: supplier?.price_rate ?? null,
        };
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        const msg = err?.error?.message ?? 'تعذر تحميل بيانات المورد';
        Swal.fire({ icon: 'error', title: 'خطأ', text: String(msg) });
        this.dialogRef.close();
      },
    });
  }

  onClose(): void {
    this.dialogRef.close();
  }

  submit(form: any): void {
    if (form.invalid || this.submitting) {
      return;
    }

    this.submitting = true;
    this.errorMessage = null;

    const payload: Record<string, unknown> = {
      supplier_name: this.formModel.supplier_name.trim(),
      supplier_phone: this.formModel.supplier_phone?.trim() || null,
      supplier_address: this.formModel.supplier_address?.trim() || null,
      supplier_rate: this.formModel.supplier_rate,
      price_rate: this.formModel.price_rate,
    };

    if (this.formModel.supplier_type !== '' && this.formModel.supplier_type != null) {
      payload['supplier_type'] = Number(this.formModel.supplier_type);
    } else {
      payload['supplier_type'] = null;
    }

    this.suppliers.updateSupplier(this.data.supplierId, payload).subscribe({
      next: () => {
        this.data.refreshData?.();
        Swal.fire({ icon: 'success', title: 'تم حفظ التعديلات', timer: 2000, showConfirmButton: false });
        this.dialogRef.close(true);
      },
      error: (err) => {
        this.submitting = false;
        this.errorMessage =
          err?.error?.message ?? err?.error?.error ?? err?.message ?? 'تعذر حفظ التعديلات';
      },
    });
  }
}
