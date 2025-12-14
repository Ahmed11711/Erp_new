import { Component, inject, ViewChild } from '@angular/core';
import { SuppliersService } from '../services/suppliers.service';
import { TypesService } from '../services/types.service';
import {MatSnackBar, MatSnackBarRef} from '@angular/material/snack-bar';
import { NgForm } from '@angular/forms';

@Component({
  selector: 'app-add-supplier',
  templateUrl: './add-supplier.component.html',
  styleUrls: ['./add-supplier.component.css']
})
export class AddSupplierComponent {
  errorMessage = null;
  types : any = [];
  durationInSeconds = 2;
  @ViewChild('addSupp', {static: false}) addSupp!: NgForm;
  constructor(private supplier:SuppliersService, private supplierTypes:TypesService,private _snackBar: MatSnackBar) { }

  ngOnInit(){
    this.supplierTypes.getTypes().subscribe((res:any)=>{
      this.types = res;
    });
  }
  openSnackBar() {
    this._snackBar.openFromComponent(PizzaPartyAnnotatedComponent, {
      duration: this.durationInSeconds * 1000,
    });
  }

  addSupplier(form:any){
  
    if(form.invalid){
      return;
    }
    const supplierData = { 
      supplier_name:form.value.name,
      supplier_type:form.value.type,
      supplier_phone:form.value.phone,
      supplier_rate:form.value.supplier_rate,
      price_rate:form.value.price_rate,
      supplier_address:form.value.address,
  }

    this.supplier.addSupplier(supplierData).subscribe((res:any)=>{
      if(res.success){
        this.clr();
        this.openSnackBar();
      }
    }, err=>{
      this.errorMessage = err.error.message;
      setTimeout(() => {
        this.errorMessage = null;
    }, 3500)
    }
    )
}

clr(){
  this.addSupp.resetForm();
}

}



@Component({
  selector: 'snack-bar-annotated-component-example-snack',
  templateUrl: 'snack-bar-annotated-component-example-snack.html',
  styles: [
    `
    :host {
      display: flex;
    }

    .example-pizza-party {
      color: hotpink;
    }
  `,
  ],
})
export class PizzaPartyAnnotatedComponent {
  snackBarRef = inject(MatSnackBarRef);
}
