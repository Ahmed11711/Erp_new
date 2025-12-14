import { Component, Inject } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
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
    if (this.data.id) {
      this.form.patchValue({
        name:this.data.name,
        balance:this.data.balance,
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
    'balance' :new FormControl(null , [Validators.required]),
    'asset_id' :new FormControl(0 , [Validators.required, Validators.min(1)]),
  })

  submitform(){
    if(this.form.valid){
      if (this.data.id) {
        this.StockService.edit(this.data.id,this.form.value).subscribe((result:any)=>{
          if (result.message) {
            this.dialogRef.close(this.form.value);
          }
        })
      }else{
        this.StockService.add(this.form.value).subscribe((result:any)=>{
          if (result.message) {
            this.dialogRef.close(this.form.value);
          }
        })
      }
    }
  }

}
