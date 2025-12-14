import { Component } from '@angular/core';
import { AssetService } from '../services/asset.service';
import { MatDialog } from '@angular/material/dialog';
import { DialogAssetComponent } from '../dialog-asset/dialog-asset.component';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-estates',
  templateUrl: './estates.component.html',
  styleUrls: ['./estates.component.css']
})
export class EstatesComponent {

  data:any[]=[];

  constructor(private matDialog:MatDialog ,private assetService:AssetService){}

  ngOnInit(){
    this.getMainAssets();
  }


  getMainAssets(){
    let param = {
      parent:true
    }
    this.assetService.getMainAssets(param).subscribe(res=>{
      this.data = res.data;
    })
  }


  openDialog(data = {}) {
    const dialogRef = this.matDialog.open(DialogAssetComponent, {
      data
    });

    dialogRef.afterClosed().subscribe(result => {
      console.log(result);
      if (result) {
        if (result.id) {
          this.assetService.editAsset(result).subscribe(res=>{
            if (res) {
              Swal.fire({
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getMainAssets();
            }
          })
        } else {
          this.assetService.addAsset(result).subscribe(res=>{
            if (res) {
              Swal.fire({
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
              });
              this.getMainAssets();
            }
          })
        }
      }
    });
  }

  deleteAsset(id){
    this.assetService.deleteAsset(id).subscribe(res=>{
      if (res) {
        Swal.fire({
          icon: 'success',
          timer: 2000,
          showConfirmButton: false
        });
        this.getMainAssets();
      }
    })
  }

}
