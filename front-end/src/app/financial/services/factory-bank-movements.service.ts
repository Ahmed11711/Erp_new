import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class FactoryBankMovementsService {

  constructor(private http:HttpClient) { }


  get(params):Observable<any>
  {
    return this.http.get<any>(`${environment.Url}/FactoryBankMovements`, {params})
  }

  add(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/FactoryBankMovements`,formData)
  }

  delete(id:number){
    return this.http.delete<any>(`${environment.Url}/FactoryBankMovements/${id}`)
  }

  addFactoryBankMovementsDetails(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/FactoryBankMovementsDetails`,formData)
  }


  getFactoryBankMovementsDetails(){
    return this.http.get<any>(`${environment.Url}/FactoryBankMovementsDetails`)
  }

  deleteFactoryBankMovementsDetails(id:number){
    return this.http.delete<any>(`${environment.Url}/FactoryBankMovementsDetails/${id}`)
  }

  addFactoryBankMovementsCustody(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/FactoryBankMovementsCustody`,formData)
  }


  getFactoryBankMovementsCustody(){
    return this.http.get<any>(`${environment.Url}/FactoryBankMovementsCustody`)
  }

  deleteFactoryBankMovementsCustody(id:number){
    return this.http.delete<any>(`${environment.Url}/FactoryBankMovementsCustody/${id}`)
  }

}
