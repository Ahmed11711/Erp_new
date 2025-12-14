import { Component } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import Swal from 'sweetalert2';
import { ProductionService } from '../services/production.service';

@Component({
  selector: 'app-production',
  templateUrl: './production.component.html',
  styleUrls: ['./production.component.css']
})
export class ProductionComponent {
productionData:any=[];
  constructor(public matDialog:MatDialog, private production:ProductionService) { }

ngOnInit(){
this.getProduction();
}
  getProduction(){
    this.production.getProductions().subscribe((res:any)=>{
      this.productionData = res;
    });
  }

  add(){
    Swal.fire({
      title: 'Enter Details',
      html:
        `<select class="form-control" name="warehouse" id="field1" style="direction: rtl;">
          <option selected disabled value="">المخزن</option>
          <option value="مخزن مواد خام">مخزن مواد خام</option>
          <option value="مخزن منتج تحت التشغيل">مخزن منتج تحت التشغيل</option>
          <option value="مخزن منتج تام">مخزن منتج تام</option>
        </select>` +
        '<div class="form-group"><input style="direction: rtl;" id="field2" class="form-control mt-2" placeholder="خط الانتاج"></div>',
      showCancelButton: true,
      confirmButtonText: 'Submit',
      preConfirm: () => {

        const field1Element = document.getElementById('field1') as HTMLSelectElement;
        const selectedValue = field1Element.value;
        const field2Value = (<HTMLInputElement>document.getElementById('field2')).value;

        if (!selectedValue || !field2Value) {
          Swal.showValidationMessage('يجب ادخال البيانات');
          return;
        }
        this.production.addProduction(selectedValue,field2Value).subscribe((res:any)=>{
          this.getProduction();
        }
        );
      }
    });
  }
  deleteLine(id:number){
    this.production.deleteProduction(id).subscribe((res:any)=>{
      this.getProduction();
    }
    );
  }
}
