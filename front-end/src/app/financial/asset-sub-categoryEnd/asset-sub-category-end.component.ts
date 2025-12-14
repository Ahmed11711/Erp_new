import { Component } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { ActivatedRoute } from '@angular/router';
import Swal from 'sweetalert2';
import { DialogAssetComponent } from '../dialog-asset/dialog-asset.component';
import { AssetService } from '../services/asset.service';
 import { Router } from '@angular/router';

@Component({
  selector: 'app-asset-sub-category-end',
  templateUrl: './asset-sub-category-end.component.html',
  styleUrls: ['./asset-sub-category-end.component.css']
})
export class AssetSubSubCategoryComponent {
parent:any = {}
  data:any[]=[];
  id?:string;

  constructor(private matDialog:MatDialog ,private assetService:AssetService,private route: ActivatedRoute,   private router: Router ){
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

  goToDetails(code)
  {
 this.router.navigate(['/dashboard/financial/report-order-new-details'], {
    queryParams: { asset_id: code }
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
