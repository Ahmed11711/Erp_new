import { Component } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { AddTypeComponent } from '../add-type/add-type.component';
import { TypesService } from '../services/types.service';

@Component({
  selector: 'app-types',
  templateUrl: './types.component.html',
  styleUrls: ['./types.component.css']
})
export class TypesComponent {
  tabelData:any = [] ;

  constructor(public matDialog:MatDialog , private typeService:TypesService) { }

  ngOnInit(){
    this.typeService.getTypes().subscribe((res:any)=>{
      this.tabelData = res;
    });
  
  }

  addType(){
    this.matDialog.open(AddTypeComponent);
  }


}
