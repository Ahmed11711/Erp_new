import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { CachedLookup } from 'src/app/shared/utils/cached-lookup';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class ProductionService {

  private readonly productions = new CachedLookup<unknown>(() =>
    this.http.get(`${environment.Url}/productions`)
  );

  constructor(private http:HttpClient) { }


  addProduction(warehouse:string,production_line:string){
    return this.http.post(`${environment.Url}/productions`,{warehouse,production_line}).pipe(
      tap(() => this.productions.invalidate())
    );
  }

  getProductions(): Observable<unknown> {
    return this.productions.get();
  }

  deleteProduction(id:number){
    return this.http.delete(`${environment.Url}/productions/${id}`).pipe(
      tap(() => this.productions.invalidate())
    );
  }

}
