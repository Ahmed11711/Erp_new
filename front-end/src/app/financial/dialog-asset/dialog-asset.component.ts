import { Component, Inject } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { BanksService } from '../services/banks.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { AssetCategoryComponent } from '../asset-category/asset-category.component';

@Component({
  selector: 'app-dialog',
  templateUrl: './dialog-asset.component.html',
  styleUrls: ['./dialog-asset.component.css']
})
export class DialogAssetComponent {

  constructor(@Inject(MAT_DIALOG_DATA) public data: any,
    private dialogRef: MatDialogRef<AssetCategoryComponent>){
      console.log(data);

    }

    ngOnInit(): void {
      if (this.data.id) {
        this.form.patchValue({
          name : this.data.name,
          type : this.data.type,
          code : this.data.code,
          balance: this.data.balance,
          id: this.data.id ?? 0,
        })
      }
    }

  form:FormGroup = new FormGroup({
    'id' :new FormControl(null , [Validators.required ]),
    'name' :new FormControl(null , [Validators.required ]),
    'type' :new FormControl(0 , [Validators.required, Validators.min(1) ]),
    'code' :new FormControl(null , [Validators.required ]),
    'balance' :new FormControl(null , [Validators.required]),
  })

  closeDialog() {
    this.dialogRef.close(this.form.value);
  }


}
