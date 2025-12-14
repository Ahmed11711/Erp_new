import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class ProductionService {

  constructor(private http:HttpClient) { }


  addProduction(warehouse:string,production_line:string){
    return this.http.post(`${environment.Url}/productions`,{warehouse,production_line});
  }

  getProductions(){
      return this.http.get(`${environment.Url}/productions`);
  }

  deleteProduction(id:number){
    return this.http.delete(`${environment.Url}/productions/${id}`);
  }

}
