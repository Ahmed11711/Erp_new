import { Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { BanksService } from '../services/banks.service';
import { ExpenseKindService } from '../services/expense-kind.service';
import { ExpenseService } from '../services/expense.service';

@Component({
  selector: 'app-editexpense',
  templateUrl: './editexpense.component.html',
  styleUrls: ['./editexpense.component.css']
})
export class EditexpenseComponent {

  shippingWays:any[]=[];
  orderSources:any[]=[];
  errormessage:boolean=false;
  products:any[]=[];
  banksData:any[]=[];
  expenseKind:any[]=[];
  id!:any;

  constructor(private expenseKindService:ExpenseKindService,
    private bankService:BanksService ,
    private expenseService:ExpenseService ,
    private route:Router, private router:ActivatedRoute
  ){}

  ngOnInit(): void {
    this.form.patchValue({
      bank_id:"الخزينة",
      expense_type:"نوع المصروف",
      kind_id:"فئة المصروف",
    });
    this.id = this.router.snapshot.paramMap.get("id");
    this.expenseKindService.data().subscribe(result=>this.expenseKind=result);
    this.bankService.bankSelect().subscribe((result:any)=>this.banksData=result);

    this.expenseService.getByID(this.id).subscribe(res=>{
      this.form.patchValue({
        bank_id:res.bank_id,
        expense_type:res.expense_type,
        kind_id:res.kind_id,
        expens_statement:res.expens_statement,
        amount:res.amount,
        note:res.note,
        address:res.address
      })
    })

  }

  form:FormGroup = new FormGroup({
    'bank_id' :new FormControl(null),
    'expense_type' :new FormControl(null, [Validators.required ]),
    'kind_id' :new FormControl(null, [Validators.required ]),
    'expens_statement' :new FormControl(null, [Validators.required ] ),
    'amount' :new FormControl(null, [Validators.required ] ),
    'note' :new FormControl(null, [Validators.required ] ),
    'address' :new FormControl(null, [Validators.required ] ),
    'expense_image' :new FormControl(null),
  })

  imgtext:string="صورة "
  fileopend:boolean=false;

  openFileInput() {
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
      fileInput.click();
      this.fileopend=true;
    }
  }

  selectedFile: any;
  onFileChanged(event: any) {
    this.selectedFile = event.target.files[0];
    this.imgtext = this.selectedFile?.name || 'No image selected';
  }


  submitform(){
    if(this.form.valid){
      let data = this.form.value
      const formData = new FormData();
      formData.append('bank_id', data.bank_id);
      formData.append('expense_type', data.expense_type);
      formData.append('kind_id', data.kind_id);
      formData.append('expens_statement', data.expens_statement);
      formData.append('amount', data.amount);
      formData.append('note', data.note);
      formData.append('address', data.address);

      if (this.selectedFile) {
        formData.append('expense_image', this.selectedFile, this.selectedFile.name);
      }

      this.expenseService.edit(this.id,formData).subscribe(result=>{
        if (result) {
          this.route.navigate(['/dashboard/financial/expenses']);
        }
      })
    }
  }
}
