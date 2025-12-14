import { HttpClient } from '@angular/common/http';
import { Component, Inject } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { DialogPayMoneyForSupplierComponent } from 'src/app/suppliers/dialog-pay-money-for-supplier/dialog-pay-money-for-supplier.component';
import { CompaniesService } from '../services/companies.service';


@Component({
  selector: 'app-dialog-add-company',
  templateUrl: './dialog-add-company.component.html',
  styleUrls: ['./dialog-add-company.component.css']
})
export class DialogAddCompanyComponent {

  //govern and city
  location:any[]=[];
  cities:any[]=[];
  governName:boolean=false;

  govern(event){
    if (event.target.value == "القاهرة") {
      this.governName = true;
    } else{
      this.governName = false;
    }
  }
  //end

  constructor(public dialogRef: MatDialogRef<DialogPayMoneyForSupplierComponent>,
    @Inject(MAT_DIALOG_DATA) public data: any,
    private companyService:CompaniesService,
    private http:HttpClient
  ) {}

  ngOnInit(){
    console.log(this.data);
    this.http.get('assets/egypt/governorates.json').subscribe((data:any)=>this.location=data);
    this.http.get('assets/egypt/cities.json').subscribe((data:any)=>{
      this.cities = data.filter((elem:any)=>elem.governorate_id == 1);
    });

    this.form.patchValue({
      governorate:'المحافظة',
      city:'المدينة'

    });
  }

  form:FormGroup = new FormGroup({
    'name' :new FormControl(null  , [Validators.required ]),
    'phone1' :new FormControl(null , [Validators.required , Validators.pattern('^01\\d{9}$')]),
    'phone2' :new FormControl(null , [Validators.pattern('^01\\d{9}$')]),
    'phone3' :new FormControl(null , [Validators.pattern('^01\\d{9}$')]),
    'phone4' :new FormControl(null , [Validators.pattern('^01\\d{9}$')]),
    'tel' :new FormControl(null),
    'governorate' :new FormControl(null , [Validators.required ]),
    'city' :new FormControl(null ),
    'address' :new FormControl(null , [Validators.required ])
  })

  onCloseClick(): void {
    this.dialogRef.close();
  }

  submitform(){
    if (this.form.valid) {
      let data = this.form.value
      console.log(data);

      const formData = new FormData();
      formData.append('name', data.name);
      formData.append('phone1', data.phone1);
      formData.append('phone2', data.phone2);
      formData.append('phone3', data.phone3);
      formData.append('phone4', data.phone4);
      formData.append('tel', data.tel);
      formData.append('governorate', data.governorate);
      if (data.city != "المدينة") {
        formData.append('city', data.city);
      }
      formData.append('address', data.address);

      console.log(this.form.value);

      this.companyService.addCompany(formData).subscribe(result=>{
        console.log(result);

        if (result.message == 'success') {
          this.onCloseClick();
          this.data.refreshData();
        }
      },
      (error)=>{
        this.errormessage = true;
        this.existName = '';
        this.existPhone = '';
        if (error.error.errors.name) {
          this.existName = '.الشركة موجودة بالفعل '
        }
        if (error.error.errors.phone1) {
          this.existPhone = '.الرقم مستخدم من قبل شركة '
        }
        console.log(error.error.errors);
      }
      )
    }
  }

  existName!:string
  existPhone!:string

  errormessage:boolean=false;

}
