import { Component, Inject } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { BanksService } from '../services/banks.service';
import { AssetService } from '../services/asset.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';

@Component({
  selector: 'app-dialog',
  templateUrl: './dialog.component.html',
  styleUrls: ['./dialog.component.css']
})
export class DialogComponent {

  assetData:any[] = [];

  constructor(@Inject(MAT_DIALOG_DATA) public data: any,
      private dialogRef: MatDialogRef<DialogComponent>,private bankService:BanksService, private assetService:AssetService){}

  ngOnInit(): void {
    this.getAssets();
    if (this.data.id) {
      this.form.get('asset_id')?.clearValidators();
      this.form.get('asset_id')?.updateValueAndValidity();
      this.form.get('balance')?.clearValidators();
      this.form.get('balance')?.updateValueAndValidity();
      this.form.patchValue({
        name: this.data.name,
        type: this.data.type,
        balance: this.data.balance,
        usage: this.data.usage,
        asset_id: this.data.asset_id ?? 0,
      });
    }
  }

  getAssets(){
    this.assetService.getMainAssets().subscribe(res=>{
      this.assetData = res.data;
    })
  }

  form: FormGroup = new FormGroup({
    id: new FormControl(null),
    name: new FormControl(null, [Validators.required]),
    type: new FormControl(null, [Validators.required]),
    balance: new FormControl(null, [Validators.required]),
    usage: new FormControl(null, [Validators.required]),
    asset_id: new FormControl(0, [Validators.required, Validators.min(1)]),
    counter_account_id: new FormControl(null),
  });

  submitform() {
    if (!this.form.valid) {
      return;
    }
    if (this.data.id) {
      const payload = {
        name: this.form.value.name,
        usage: this.form.value.usage,
        type: this.form.value.type,
      };
      this.bankService.edit(this.data.id, payload).subscribe((result: any) => {
        if (result.message === 'success') {
          this.dialogRef.close(this.form.value);
        }
      });
      return;
    }

    const bal = Number(this.form.value.balance) || 0;
    if (bal > 0.000001 && !this.form.value.counter_account_id) {
      return;
    }

    const addPayload = {
      ...this.form.value,
      parent_account_id: this.form.value.asset_id,
    };
    this.bankService.add(addPayload).subscribe((result: any) => {
      if (result.message === 'success') {
        this.dialogRef.close(this.form.value);
      }
    });
  }

}
