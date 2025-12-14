import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ShippingCompanyService } from 'src/app/shipping/services/shipping-company.service';
import { ExpenseKindService } from '../services/expense-kind.service';

@Component({
  selector: 'app-expenses-kind',
  templateUrl: './expenses-kind.component.html',
  styleUrls: ['./expenses-kind.component.css']
})
export class ExpensesKindComponent {

  openbtn:boolean=true;
  formdiv:boolean=false;
  errorform:boolean= false;
  addForm:boolean =false;
  addbtn:boolean =false;
  expenseKind:any[]=[];

  errorMessage!:string;
  data:any[]=[];


  length = 50;
  pageSize = 15;
  page = 0;
  pageSizeOptions = [15,50];

  constructor(private expenseService:ExpenseKindService , private expenseKindService:ExpenseKindService,){

  }

  ngOnInit(){
    this.expenseKindService.data().subscribe(result=>this.expenseKind=result);
    this.getData();
  }

  onPageChange(event:any){
    this.pageSize = event.pageSize;
      this.page = event.pageIndex;
      this.getData();
  }

  search(e:any){

    if(e.target.id == 'type'){
      this.param['type']=e.target.value;
    }
    if(e.target.id == 'state'){
      this.param['state']=e.target.value;
    }
    this.getData();

  }

  param = {};
  getData(){

    this.expenseService.search(this.pageSize,this.page+1,this.param).subscribe((res:any)=>{
      this.data = res.data;
      this.length=res.total;
      this.pageSize=res.per_page;
    })
  }

  // getData(){
  //   this.expenseService.data().subscribe(result=>this.data=result)
  // }

  form:FormGroup = new FormGroup({
    'expense_type' :new FormControl(null , [Validators.required ]),
    'expense_kind' :new FormControl(null , [Validators.required ])
  })

  openForm(){
    this.openbtn = false;
    this.formdiv = true;
    this.addForm = true;
    this.addbtn = true;
    this.form.patchValue({
      expense_type:'نوع المصروف'
    })
  }

  submitform(){
    if (this.addForm) {
      if (this.form.valid) {
        this.expenseService.add(this.form.value).subscribe(result=>{
          if (result) {
            this.openbtn = true;
            this.formdiv = false;
            this.getData();
            this.form.reset();
          }
        },
        (error)=>{

        }
        )
      }
    }
  }

  deleteData(id:number){
    this.expenseService.deleteUser(id).subscribe(result=>{
      if (result == "deleted sucuessfully") {
        this.getData();
      }
    },
    (error)=>{
      console.log(error);

      if (error.status == 404 && error.statusText == 'Not Found') {
        alert(error.statusText);
      }
    }
    )
  }

}

