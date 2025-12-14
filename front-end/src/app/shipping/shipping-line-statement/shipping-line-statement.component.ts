import { ChangeDetectorRef, Component } from '@angular/core';
import { FormGroup, FormControl, Validators } from '@angular/forms';
import { AuthService } from 'src/app/auth/auth.service';
import { ShippingCompanyService } from '../services/shipping-company.service';
import { OrderService } from '../services/order.service';
import { ShippingLineStatementService } from '../services/shipping-line-statement.service';
import Swal from 'sweetalert2';
import { ExcelService } from 'src/app/excel.service';
import { PdfService } from 'src/app/pdf.service';

@Component({
  selector: 'app-shipping-line-statement',
  templateUrl: './shipping-line-statement.component.html',
  styleUrls: ['./shipping-line-statement.component.css']
})
export class ShippingLineStatementComponent {

  companies :any = []
  data:any[]=[];
  order!:any;

  total;

  date!:any;
  company!:any;

  openbtn:boolean=true;
  formdiv:boolean=false;
  errorform:boolean= false;
  addForm:boolean =false;
  addbtn:boolean =false;

  errorMessage!:string;

  user!:string;

  constructor(private shippingCompany:ShippingCompanyService, private authService:AuthService, private cdr: ChangeDetectorRef,
    private OrderService:OrderService, private ShippingLineStatementService:ShippingLineStatementService, private pdfService:PdfService, private excelService:ExcelService) { }

  ngOnInit(){
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    this.date = `${yyyy}-${mm}-${dd}`;

    this.user = this.authService.getUser();
    this.shippingCompany.shippingCompanySelect().subscribe((res:any)=>{
      this.companies = res
    });

    this.OrderService.getOrdersNumber().subscribe((res:any)=>{
      this.orders = res;
      this.orders = res.map(order => ({
        ...order,
        id: String(order.id)
      }));

    });
  }

  selectedDate(e){
    this.date = e.target.value;
    if (this.company > 0) {
      this.getData();
    }
  }

  selectCompany(e){
    this.company = e.target.value;
    this.filteredOrders = [];
    this.getData();
  }



  openForm(){
    this.openbtn = false;
    this.formdiv = true;
    this.addForm = true;
    this.addbtn = true;
  }

  getData(){
    if (this.company) {
      let params = {date:this.date, company:this.company}
      this.ShippingLineStatementService.get(params).subscribe(result=>{
        this.data=result;
        console.log(this.data);
        this.total = this.data.reduce((acc, elm) => {
          return !elm.canceled ? acc + elm.order.net_total : acc;
        }, 0);

      })
    }
  }

  submitform(){
    if (this.order && this.company) {
      let params = {date:this.date, company:this.company, order_id:this.order}
      this.ShippingLineStatementService.add(params).subscribe(result=>{
        if (result) {
          this.getData();
          Swal.fire({
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
          })
        }
      })
    }
  }

  cancelOrder(id:number){
    this.ShippingLineStatementService.cancel(id).subscribe(result=>{
      if (result) {
        this.getData();
        Swal.fire({
          icon: 'success',
          timer: 1500,
          showConfirmButton: false
        })
      }
    })
  }

  orders:any[]=[];

  catword = 'id';
  filteredOrders:any = [];
  editOrder(order){
    console.log(order);

    if (order.length > 2) {
      this.filteredOrders = this.orders.filter(item =>
        item.id.toString().includes(order)
      );
      console.log(this.filteredOrders);

    }
  }


  orderChange(event) {
    console.log(event);

    this.order = event.id
  }

  resetOrder(){
    this.filteredOrders = [];
    this.order = null
  }

  startPrint:boolean = false;
  export(status) {
    if (this.company) {
      this.startPrint = true;
      setTimeout(()=>{
        let fileName = this.companies.find(elm=> elm.id == this.company).name;
        var element = document.getElementById('capture');
        this.pdfService.generatePdf(element, status, fileName);
        this.startPrint = false;
        this.cdr.detectChanges();
      }, 1000)
    }
  }

  exportTableToExcel() {
    if (this.company) {
      let fileName = this.companies.find(elm=> elm.id == this.company).name;
      const tableElement:any = document.getElementById('capture');
      this.excelService.generateExcel(fileName, tableElement, 1);
    }
  }


}
