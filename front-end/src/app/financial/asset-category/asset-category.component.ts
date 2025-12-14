import { Component } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import Swal from 'sweetalert2';
import { DialogAssetComponent } from '../dialog-asset/dialog-asset.component';
import { AssetService } from '../services/asset.service';
import { ActivatedRoute } from '@angular/router';

@Component({
  selector: 'app-asset-category',
  templateUrl: './asset-category.component.html',
  styleUrls: ['./asset-category.component.css']
})
export class AssetCategoryComponent {
  parent:any = {}
  data:any[]=[];
  id?:string;

  constructor(private matDialog:MatDialog ,private assetService:AssetService,private route: ActivatedRoute){
    this.id = this.route.snapshot.paramMap.get('id')!;
  }

  ngOnInit(){
    this.getMainAssets();
  }


  getMainAssets(){
    this.assetService.getMainAssets({id:this.id}).subscribe(res=>{
      this.parent = res.data;
      this.data = res.data.children;
    })
  }


  openDialog(data = {}) {
    const dialogRef = this.matDialog.open(DialogAssetComponent, {
      data
    });

    dialogRef.afterClosed().subscribe(result => {
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
          if (this.id) {
            result.parent_id = this.id
          }
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
