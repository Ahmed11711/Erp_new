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
  tabelData:any[] = [] ;
  listLoading = false;
  listError = false;

  constructor(public matDialog:MatDialog , private typeService:TypesService) { }

  ngOnInit(){
    this.loadTypes();
  }

  loadTypes(){
    this.listLoading = true;
    this.listError = false;
    this.typeService.getTypes().subscribe({
      next: (res:any)=>{
        this.tabelData = Array.isArray(res) ? res : (res?.data ?? []);
        this.listLoading = false;
      },
      error: ()=>{
        this.tabelData = [];
        this.listLoading = false;
        this.listError = true;
      },
    });
  }

  addType(){
    this.matDialog.open(AddTypeComponent).afterClosed().subscribe((added) => {
      if (added) {
        this.loadTypes();
      }
    });
  }

  deleteType(id: number){
    this.typeService.deleteType(id).subscribe({
      next: () => this.loadTypes(),
      error: (err) => alert(err?.error?.message || 'تعذر حذف الفئة'),
    });
  }

}
