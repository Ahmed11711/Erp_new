import { Component, Inject } from '@angular/core';
import { AbstractControl, FormGroup, FormControl, ValidationErrors, ValidatorFn, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { StockService } from '../services/stock.service';
import { AssetService } from 'src/app/financial/services/asset.service';

@Component({
  selector: 'app-dialog',
  templateUrl: './dialog.component.html',
  styleUrls: ['./dialog.component.css']
})
export class DialogComponent {

  assetData:any[] = [];

  constructor(@Inject(MAT_DIALOG_DATA) public data: any,
      private dialogRef: MatDialogRef<DialogComponent>,private StockService:StockService, private assetService:AssetService){}

  ngOnInit(): void {
    this.getAssets();
    const bal = this.data.balance;
    if (this.data.id) {
      this.form.patchValue({
        name: this.data.name,
        balance: bal === null || bal === undefined || bal === '' ? null : Number(bal),
        asset_id: this.data.asset_id ?? 0,
      });
    } else if (this.data.name) {
      /** صف مخزن معروف من القائمة الثابتة دون سجل في /stocks — إنشاء أول ربط */
      this.form.patchValue({
        name: this.data.name,
        balance: bal === null || bal === undefined || bal === '' ? 0 : Number(bal),
        asset_id: this.data.asset_id && this.data.asset_id > 0 ? this.data.asset_id : 0,
      });
    }
  }

  getAssets(){
    this.assetService.getMainAssets().subscribe(res=>{
      this.assetData = res.data;
    })
  }

  /** يقبل 0 كرصيد صالح (لا يعتبره فارغاً مثل required مع بعض مدخلات الرقم). */
  private static balancePresent(): ValidatorFn {
    return (control: AbstractControl): ValidationErrors | null => {
      const v = control.value;
      if (v === null || v === undefined || v === '') {
        return { required: true };
      }
      return null;
    };
  }

  form: FormGroup = new FormGroup({
    id: new FormControl(null),
    name: new FormControl(null, [Validators.required]),
    balance: new FormControl(null, [
      DialogComponent.balancePresent(),
      Validators.min(0),
    ]),
    asset_id: new FormControl(0, [Validators.required, Validators.min(1)]),
  });

  submitform(): void {
    if (!this.form.valid) {
      return;
    }
    const raw = this.form.getRawValue();
    const payload = {
      ...raw,
      name: typeof raw.name === 'string' ? raw.name : this.data.name,
      balance: Number(raw.balance),
      asset_id: Number(raw.asset_id),
    };

    if (this.data.id) {
      this.StockService.edit(this.data.id, payload).subscribe({
        next: (result: any) => {
          if (result?.success !== false && (result?.message || result?.data)) {
            this.dialogRef.close(payload);
          }
        },
        error: () => {},
      });
    } else {
      this.StockService.add(payload).subscribe({
        next: (result: any) => {
          if (result?.success !== false && (result?.message || result?.data)) {
            this.dialogRef.close(payload);
          }
        },
        error: () => {},
      });
    }
  }

}
