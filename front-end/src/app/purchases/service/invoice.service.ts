import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class InvoiceService {

  constructor(private http:HttpClient) { }


  addInvoice(invoice:any){
    return this.http.post(`${environment.Url}/purchases`,invoice)
  }

  getInvoices(){
    return this.http.get(`${environment.Url}/purchases`);
  }

  getInvoiceById(id:number, forEdit = false){
    return this.http.get(`${environment.Url}/purchases/${id}?foredit=${forEdit}` );
  }

  deleteInvoice(id:number){
    return this.http.delete(`${environment.Url}/purchases/${id}`);
  }

  search(items:number,page:number,search:any){
    return this.http.get(`${environment.Url}/purchases/search?itemsPerPage=${items}&page=${page}`,{params:search});
  }
}
