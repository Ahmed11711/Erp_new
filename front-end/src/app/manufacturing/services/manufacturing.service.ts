import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class ManufacturingService {


  constructor(private http:HttpClient) { }

  addRecipe(data:any){
    return this.http.post(`${environment.Url}/manufacture`,data);
  }

  confirm(data:any){
    return this.http.post(`${environment.Url}/manufacture/confirm`,data);
  }

  getAllRecipes(){
    return this.http.get(`${environment.Url}/manufacture`)
  }

  manfuctureByWarhouse(data:any){
    return this.http.get(`${environment.Url}/manufacture/manfucture_by_warhouse?warehouse=${data}`)
  }

  confirmed(){
    return this.http.get(`${environment.Url}/manufacture/confirmed`)
  }

  done(id:any){
    return this.http.get(`${environment.Url}/manufacture/done/${id}`)
  }
}
