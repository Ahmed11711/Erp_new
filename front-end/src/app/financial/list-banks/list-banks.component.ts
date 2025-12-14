import { Component, OnInit } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { DialogComponent } from '../dialog/dialog.component';
import { BanksService } from '../services/banks.service';
import { Bank } from '../interfaces/bank';
import { AuthService } from 'src/app/auth/auth.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-list-banks',
  templateUrl: './list-banks.component.html',
  styleUrls: ['./list-banks.component.css']
})
export class ListBanksComponent implements OnInit{

  banks:Bank[]=[];
  user!:string;
  constructor(private matDialog:MatDialog , private bankService:BanksService , private authService:AuthService){}

  ngOnInit(): void {
    this.getData();
    this.user = this.authService.getUser();
  }


  openDialog(data = {}) {
    const dialogRef = this.matDialog.open(DialogComponent, {
      data
    });

    dialogRef.afterClosed().subscribe(result => {
      console.log(result);
      if (result) {
        this.getData();
      }
    });
  }

  getData(){
    this.bankService.data().subscribe((result:any)=>{
      this.banks = result;
    });
  }

  depositBank(id){
    let amount;
    let reason='';
    Swal.fire({
      title: 'مبلغ الايداع',
      input: 'number',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال قيمة'
        }
        if (value !== '') {
          amount = value;
        }
        return undefined
      }
    }).then((result) => {
      console.log(result);
      if (result.isConfirmed) {
        Swal.fire({
          title: 'سبب الايداع؟',
          input: 'text',
          showCancelButton: true,
          inputValidator: (value) => {
            if (!value) {
              return 'يجب ادخال قيمة'
            }
            if (value !== '') {
              reason = value;
              this.bankService.depositBank(id , amount , reason).subscribe(res=>{
                if (res) {
                  console.log(res);

                  this.getData();
                }
              })
            }
            return undefined
          }
      })}
    });


  }

  editBankBalance(id){
    let amount;
    let reason='';
    Swal.fire({
      title: 'تعديل رصيد الخزنة',
      input: 'number',
      inputPlaceholder:'الرصيد',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال قيمة'
        }
        if (value !== '') {
          amount = value;
        }
        return undefined
      }
    }).then((result) => {
      console.log(result);
      if (result.isConfirmed) {
        Swal.fire({
          title: 'سبب تعديل الرصيد؟',
          input: 'text',
          showCancelButton: true,
          inputValidator: (value) => {
            if (!value) {
              return 'يجب ادخال قيمة'
            }
            if (value !== '') {
              reason = value;
              this.bankService.editBankBalance(id , amount , reason).subscribe(res=>{
                if (res) {
                  this.getData();
                }
              })
            }
            return undefined
          }
      })}
    });


  }

  withDrawBank(id){
    let amount;
    let reason='';
    Swal.fire({
      title: 'مبلغ السحب',
      input: 'number',
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'يجب ادخال قيمة'
        }
        if (value !== '') {
          amount = value;
        }
        return undefined
      }
    }).then((result) => {
      console.log(result);
      if (result.isConfirmed) {
        Swal.fire({
          title: 'سبب السحب؟',
          input: 'text',
          showCancelButton: true,
          inputValidator: (value) => {
            if (!value) {
              return 'يجب ادخال قيمة'
            }
            if (value !== '') {
              reason = value;
              this.bankService.withDrawBank(id , amount , reason).subscribe(res=>{
                if (res) {
                  this.getData();
                }
              })
            }
            return undefined
          }
      })}
    });


  }

  transferMoney(id:number , name:string){
    let bankTo;
    let bankFrom = id;
    let amount;
    let reason;
    const banks = this.banks;
    const bankSelectOptions = banks.reduce((options, bank) => {
      if (bank.id !== id) {
        options[bank.id] = bank.name;
      }
      return options;
    }, {});

    Swal.fire({
      text: `اختر الخزينة المراد التحويل اليها؟`,
      title:name,
      input: 'select',
      inputOptions: bankSelectOptions,
      inputPlaceholder: 'اختر الخزينة',
      showCancelButton: true,
      confirmButtonText: 'تأكيد',
      cancelButtonText: 'إلغاء',
    }).then((bankResult) => {
      if (bankResult.isConfirmed) {
        const selectedBankId = bankResult.value;
        if (selectedBankId) {
          bankTo = selectedBankId;
          Swal.fire({
            title: 'مبلغ المراد تحويله؟',
            input: 'number',
            showCancelButton: true,
            inputValidator: (value) => {
              if (!value) {
                return 'يجب ادخال قيمة'
              }
              if (value !== '') {
                amount = value;
              }
              return undefined
            }
          }).then((result) => {
            console.log(result);
            if (result.isConfirmed) {
              Swal.fire({
                title: 'سبب التحويل؟',
                input: 'text',
                showCancelButton: true,
                inputValidator: (value) => {
                  if (!value) {
                    return 'يجب ادخال قيمة'
                  }
                  if (value !== '') {
                    reason = value;
                    this.bankService.transferMoney(bankFrom,bankTo,amount,reason).subscribe(result=>{
                      console.log(result);
                      this.getData();
                    });
                  }
                  return undefined
                }
            })}
          });
        } else{
          Swal.fire({
            icon:'error',
            title: 'اختر الخزينة',
          })
        }
      }
    });


  }


}
