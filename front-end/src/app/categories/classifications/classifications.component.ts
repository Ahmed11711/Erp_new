import { Component } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import Swal from 'sweetalert2';
import { ItemClassificationService } from '../services/item-classification.service';

@Component({
  selector: 'app-classifications',
  templateUrl: './classifications.component.html',
  styleUrls: ['./classifications.component.css'],
})
export class ClassificationsComponent {
  tableData: any[] = [];

  constructor(public matDialog: MatDialog, private classificationService: ItemClassificationService) {}

  ngOnInit() {
    this.getClassifications();
  }

  getClassifications() {
    this.classificationService.getClassifications().subscribe((res: any) => {
      this.tableData = Array.isArray(res) ? res : [];
    });
  }

  addClassification() {
    Swal.fire({
      title: 'إضافة تصنيف جديد',
      html:
        `<select class="form-control" name="warehouse" id="field1" style="direction: rtl;">
          <option selected disabled value="">المخزن</option>
          <option value="مخزن مواد خام">مخزن مواد خام</option>
          <option value="مخزن منتج تحت التشغيل">مخزن منتج تحت التشغيل</option>
          <option value="مخزن منتج تام">مخزن منتج تام</option>
        </select>` +
        '<div class="form-group"><input style="direction: rtl;" id="field2" class="form-control mt-2" placeholder="اسم التصنيف"></div>',
      showCancelButton: true,
      confirmButtonText: 'حفظ',
      cancelButtonText: 'إلغاء',
      preConfirm: () => {
        const field1Element = document.getElementById('field1') as HTMLSelectElement;
        const selectedValue = field1Element.value;
        const field2Value = (<HTMLInputElement>document.getElementById('field2')).value.trim();

        if (!selectedValue || !field2Value) {
          Swal.showValidationMessage('يجب اختيار المخزن وإدخال اسم التصنيف');
          return false;
        }
        return { warehouse: selectedValue, name: field2Value };
      },
    }).then((result) => {
      if (!result.isConfirmed || !result.value) {
        return;
      }
      this.classificationService
        .addClassification(result.value.warehouse, result.value.name)
        .subscribe({
          next: () => {
            this.getClassifications();
            Swal.fire({ icon: 'success', title: 'تمت الإضافة', timer: 1500, showConfirmButton: false });
          },
          error: (err) => {
            const msg = err?.error?.message ?? 'تعذّر إضافة التصنيف';
            Swal.fire({ icon: 'error', title: 'خطأ', text: String(msg) });
          },
        });
    });
  }

  deleteClassification(id: number) {
    this.classificationService.deleteClassification(id).subscribe(() => {
      this.getClassifications();
    });
  }
}
