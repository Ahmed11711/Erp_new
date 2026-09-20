import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { CachedLookup } from 'src/app/shared/utils/cached-lookup';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class TypesService {

  private readonly types = new CachedLookup<unknown[]>(() =>
    this.http.get<unknown[]>(`${environment.Url}/suppliers/getAllSupplierTypes`)
  );

  constructor(private http:HttpClient) { }


  addType(supplier_type:string){
    return this.http.post(`${environment.Url}/suppliers/StoreSupplierType` ,{supplier_type}).pipe(
      tap(() => this.types.invalidate())
    );
  }

  getTypes(): Observable<unknown[]> {
    return this.types.get();
  }

  deleteType(id: number){
    return this.http.delete(`${environment.Url}/suppliers/deleteType/${id}`).pipe(
      tap(() => this.types.invalidate())
    );
  }

  invalidateCache(): void {
    this.types.invalidate();
  }

}
