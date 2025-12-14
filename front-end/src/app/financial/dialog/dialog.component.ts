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
      this.form.patchValue({
        name:this.data.name,
        type:this.data.type,
        balance:this.data.balance,
        usage:this.data.usage,
        asset_id:this.data.asset_id ?? 0,
      })
    }
  }

  getAssets(){
    this.assetService.getMainAssets().subscribe(res=>{
      this.assetData = res.data;
    })
  }

  form:FormGroup = new FormGroup({
    'id' :new FormControl(null),
    'name' :new FormControl(null , [Validators.required ]),
    'type' :new FormControl(null , [Validators.required ]),
    'balance' :new FormControl(null , [Validators.required]),
    'usage' :new FormControl(null , [Validators.required]),
    'asset_id' :new FormControl(0 , [Validators.required, Validators.min(1)]),
  })

  submitform(){
    if(this.form.valid){
      if (this.data.id) {
        this.bankService.edit(this.data.id,this.form.value).subscribe((result:any)=>{
          if (result.message === "success") {
            this.dialogRef.close(this.form.value);
          }
        })
      }else{
        this.bankService.add(this.form.value).subscribe((result:any)=>{
          if (result.message === "success") {
            this.dialogRef.close(this.form.value);
          }
        })
      }
    }
  }

}
