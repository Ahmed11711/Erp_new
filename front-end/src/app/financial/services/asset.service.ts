import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class AssetService {


  constructor(private http:HttpClient) { }


  add(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/assets`,formData)
  }

  data(){
    return this.http.get<any>(`${environment.Url}/assets`)
  }

  getMainAssets(params:any = {}){
    let target = '/tree_accounts';

    if (params?.id) {
      target = `${target}/${params.id}`;
    }
    return this.http.get<any>(`${environment.Url}${target}`, {params});
  }

  addAsset(formdata){
    return this.http.post<any>(`${environment.Url}/tree_accounts`, formdata);
  }

  editAsset(formdata){
    return this.http.put<any>(`${environment.Url}/tree_accounts/${formdata.id}`, formdata);
  }

  deleteAsset(id){
    return this.http.delete<any>(`${environment.Url}/tree_accounts/${id}`);
  }
}
