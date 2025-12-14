import { Component, ViewChild } from '@angular/core';
import { SuppliersService } from '../services/suppliers.service';
import { TypesService } from '../services/types.service';
import { MatDialog } from '@angular/material/dialog';
import { DialogPayMoneyForSupplierComponent } from '../dialog-pay-money-for-supplier/dialog-pay-money-for-supplier.component';

@Component({
  selector: 'app-list-suppliers',
  templateUrl: './list-suppliers.component.html',
  styleUrls: ['./list-suppliers.component.css']
})
export class ListSuppliersComponent {
types:any = [];
suppliersData:any = [];
phone:string='';
supplier_name:string='';
supplier_type:string='';

length = 50;
pageSize = 5;
page = 0;

totalBalance:number=0;
selectedStatus!: any;

pageSizeOptions = [5,10,15,50];

@ViewChild('listSupp', {static: false}) listSupp!: any;
  constructor(private tpes:TypesService, private suppliers:SuppliersService , private dialog:MatDialog) { }

  ngOnInit(){
    this.tpes.getTypes().subscribe((res:any)=>{
      this.types = res;
    })
    this.getSuppliers();
  }


getSuppliers(items= this.pageSize,page = this.page+1){
  this.suppliers.getSuppliers(items,page).subscribe((res:any)=>{
    this.suppliersData = res.data;
    // this.suppliersData.forEach(item => {
    //   item.balance = item.balance.toFixed(2);
    // });
    this.length = res.total;
    this.pageSize = res.per_page;
console.log(this.pageSize)
  })
}

onPageChange(event:any){
  this.pageSize = event.pageSize;
     this.page = event.pageIndex;
    this.search();
}
onTypeChange($event:any){
  console.log($event.target.value);
this.supplier_type = $event.target.value;
this.search();
}
onPhonechanges($event:any){
  this.phone = $event.target.value;
  this.search();

}
onSuppliernamechanges($event:any){
  this.supplier_name = $event.target.value;
  this.search();
}


param:any = {};
search(){
  console.log(this.param);
  this.param = {};
  if(this.phone!=''){
    this.param['supplier_phone'] = this.phone;
  }
  if(this.supplier_name!=''){
    this.param['supplier_name'] = this.supplier_name;
  }
  if(this.supplier_type!=''){
    this.param['supplier_type'] = this.supplier_type;
  }

  if (this.selectedStatus) {
    this.param['status'] = this.selectedStatus
  }

  this.suppliers.searchSuppliers(this.pageSize,this.page+1,this.param).subscribe((data:any)=>{
    console.log(data);
    this.suppliersData=data.suppliers.data;
    // this.suppliersData.forEach(item => {
    //   item.balance = item.balance.toFixed(2);
    // });
    this.length=data.suppliers.total;
    this.pageSize=data.suppliers.per_page;
    this.totalBalance=data.sum_of_balance;
  } )
}

clrSearch(){
  this.selectedStatus = null;
  this.listSupp.resetForm();
  this.getSuppliers();
}

openDialog(supplier): void {
  const dialogRef = this.dialog.open(DialogPayMoneyForSupplierComponent, {
    width: '30%',data: {supplier,refreshData: ()=>this.getSuppliers()},
  });

  dialogRef.afterClosed().subscribe(result => {
    console.log('The dialog was closed');
  });
}

}
