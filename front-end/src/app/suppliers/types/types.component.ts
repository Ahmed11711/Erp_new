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
    this.loadTypes();
  }

  loadTypes(){
    this.typeService.getTypes().subscribe((res:any)=>{
      this.tabelData = res;
    });
  }

  addType(){
    this.matDialog.open(AddTypeComponent);
  }

  deleteType(id: number){
    this.typeService.deleteType(id).subscribe({
      next: () => this.loadTypes(),
      error: (err) => alert(err?.error?.message || 'تعذر حذف الفئة'),
    });
  }

}
