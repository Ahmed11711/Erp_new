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

  assetData: any[] = [];
  assetKeyword = 'name';
  assetInitialValue = '';
  assetSearchText = '';
  assetFieldReady = true;

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

  getAssets() {
    this.assetService.getMainAssets().subscribe(res => {
      this.assetData = this.parseAssetList(res);
      this.syncAssetInitialValue();
    });
  }

  /** Laravel JsonResource::collection قد يضع الصفوف في data.data */
  private parseAssetList(res: any): any[] {
    const payload = res?.data;
    if (Array.isArray(payload)) {
      return payload;
    }
    if (payload && Array.isArray(payload.data)) {
      return payload.data;
    }
    return [];
  }

  private syncAssetInitialValue(): void {
    const id = Number(this.form.get('asset_id')?.value);
    if (id > 0) {
      const found = this.findAssetById(id);
      this.assetInitialValue = found?.name ?? '';
      this.refreshAssetField();
    }
  }

  private refreshAssetField(): void {
    this.assetFieldReady = false;
    setTimeout(() => {
      this.assetFieldReady = true;
    });
  }

  onAssetSelected(item: { id?: number | string; name?: string } | string | null | undefined): void {
    if (!item) {
      return;
    }

    if (typeof item === 'string') {
      const found = this.findAssetByName(item);
      if (found) {
        this.setAssetSelection(Number(found.id), found.name);
      }
      return;
    }

    const id = Number(item.id);
    if (id > 0) {
      this.setAssetSelection(id, item.name ?? this.findAssetById(id)?.name ?? '');
    } else {
      const found = this.findAssetByName(item.name ?? '');
      if (found) {
        this.setAssetSelection(Number(found.id), found.name);
      }
    }
  }

  onAssetInputChanged(value: string): void {
    this.assetSearchText = (value ?? '').toString();
    const typed = this.assetSearchText.trim();
    if (!typed) {
      return;
    }
    const found = this.findAssetByName(typed);
    if (found) {
      this.setAssetSelection(Number(found.id), found.name, false);
    }
  }

  syncAssetFromInput(): void {
    const typed = (this.assetSearchText || this.assetInitialValue || '').toString().trim();
    if (!typed) {
      return;
    }
    const found = this.findAssetByName(typed);
    if (found) {
      this.setAssetSelection(Number(found.id), found.name, false);
    }
  }

  onAssetCleared(): void {
    this.form.patchValue({ asset_id: 0 });
    this.form.get('asset_id')?.markAsTouched();
    this.assetInitialValue = '';
    this.assetSearchText = '';
  }

  private findAssetById(id: number): { id: number | string; name: string } | undefined {
    return this.assetData.find((a: { id: number | string }) => Number(a.id) === id);
  }

  private findAssetByName(name: string): { id: number | string; name: string } | undefined {
    const normalized = name.trim();
    if (!normalized) {
      return undefined;
    }
    return this.assetData.find((a: { name: string }) => (a.name ?? '').trim() === normalized);
  }

  private setAssetSelection(id: number, name: string, markTouched = true): void {
    this.form.patchValue({ asset_id: id });
    if (markTouched) {
      this.form.get('asset_id')?.markAsTouched();
    }
    this.assetInitialValue = name;
    this.assetSearchText = name;
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
    this.syncAssetFromInput();

    if (!this.form.valid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();
    const payload = {
      name: (typeof raw.name === 'string' ? raw.name.trim() : this.data.name) || '',
      balance: Number(raw.balance),
      asset_id: Number(raw.asset_id),
    };

    if (this.data.id) {
      this.StockService.edit(this.data.id, payload).subscribe({
        next: (result: any) => {
          if (result?.success !== false) {
            this.dialogRef.close(payload);
          }
        },
      });
    } else {
      this.StockService.add(payload).subscribe({
        next: (result: any) => {
          if (result?.success !== false) {
            this.dialogRef.close(payload);
          }
        },
      });
    }
  }

}
