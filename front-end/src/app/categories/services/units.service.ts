import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class UnitsService {

  constructor(private http:HttpClient) { }


  addUnit(warehouse:string,unit:string){
    return this.http.post(`${environment.Url}/measurements`,{warehouse,unit});
  }

  getUnits(){
     return this.http.get(`${environment.Url}/measurements`);
  }

  deleteUnit(id:number){
    return this.http.delete(`${environment.Url}/measurements/${id}`);
  }
}
