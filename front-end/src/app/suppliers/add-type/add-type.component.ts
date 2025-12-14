import { Component } from '@angular/core';
import { MatDialogRef } from '@angular/material/dialog';
import { TypesService } from '../services/types.service';

@Component({
  selector: 'app-add-type',
  templateUrl: './add-type.component.html',
  styleUrls: ['./add-type.component.css']
})
export class AddTypeComponent {

  constructor(public dialogRef: MatDialogRef<AddTypeComponent>,private typeService:TypesService) {
    
  }

  onCancelClick(): void {
    this.dialogRef.close();
  }
  addType(form:any){
    if(form.invalid){
      return;
    }
    this.typeService.addType(form.value.type).subscribe((res:any)=>{
      location.reload();
    })
    this.dialogRef.close();
  }
}
