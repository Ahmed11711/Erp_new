import { Component,Inject } from '@angular/core';
import {MatDialog} from '@angular/material/dialog';
import Swal from 'sweetalert2';
import { UnitsService } from '../services/units.service';
@Component({
  selector: 'app-units',
  templateUrl: './units.component.html',
  styleUrls: ['./units.component.css']
})
export class UnitsComponent {
tabelData:any=[];
constructor(public matDialog:MatDialog, private unitservice:UnitsService) { }

  ngOnInit(){
  this.getUnits();
  }

  getUnits(){
    this.unitservice.getUnits().subscribe((res:any)=>{
      this.tabelData = res;
    });
  }
  addUnit(){
    Swal.fire({
      title: 'Enter Details',
      html:
        `<select class="form-control" name="warehouse" id="field1" style="direction: rtl;">
          <option selected disabled value="">المخزن</option>
          <option value="مخزن مواد خام">مخزن مواد خام</option>
          <option value="مخزن منتج تحت التشغيل">مخزن منتج تحت التشغيل</option>
          <option value="مخزن منتج تام">مخزن منتج تام</option>
        </select>` +
        '<div class="form-group"><input style="direction: rtl;" id="field2" class="form-control mt-2" placeholder="وحدة القياس"></div>',
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
        this.unitservice.addUnit(selectedValue,field2Value).subscribe((res:any)=>{
          this.getUnits();
        }
        );
      }
    });
  }
  deleteUnit(id:number){
    this.unitservice.deleteUnit(id).subscribe((res:any)=>{
      this.getUnits();
    });
  }
}
