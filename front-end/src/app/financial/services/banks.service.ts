import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';
import { Bank } from '../interfaces/bank';
import { Observable } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class BanksService {

  constructor(private http:HttpClient) { }


  add(formData:Bank):Observable<Bank>
  {
    return this.http.post<Bank>(`${environment.Url}/banks`,formData)
  }

  edit(id:number,formData:Bank):Observable<Bank>
  {
    return this.http.put<Bank>(`${environment.Url}/banks/${id}`,formData)
  }

  data(){
    return this.http.get<Bank>(`${environment.Url}/banks`)
  }

  bankSelect(){
    return this.http.get<Bank>(`${environment.Url}/bankSelect`)
  }

  bankDetails(id:any , items:number,page:number){
    return this.http.get(`${environment.Url}/banks/${id}?itemsPerPage=${items}&page=${page}`);
  }

  pendingBanks(items:number,page:number, param ={}){
    return this.http.get(`${environment.Url}/pendingBanks?itemsPerPage=${items}&page=${page}` ,{params:param});
  }

  pendingBanksStatus(data:any){
    return this.http.post(`${environment.Url}/pendingBanks` , data);
  }

  depositBank(id:any , amount:number , reason:string){
    return this.http.get(`${environment.Url}/banks/depositbank/${id}?amount=${amount}&reason=${reason}`);
  }

  editBankBalance(id:any , amount:number , reason:string){
    return this.http.get(`${environment.Url}/banks/editBankBalance/${id}?amount=${amount}&reason=${reason}`);
  }

  withDrawBank(id:any , amount:number , reason:string){
    return this.http.get(`${environment.Url}/banks/withDrawBank/${id}?amount=${amount}&reason=${reason}`);
  }

  transferMoney(bankFrom:number , bankTo:number , amount:number ,reason:string){
    return this.http.get(`${environment.Url}/bank/transfermoney?bankFrom=${bankFrom}&bankTo=${bankTo}&amount=${amount}&reason=${reason}`);
  }
}
