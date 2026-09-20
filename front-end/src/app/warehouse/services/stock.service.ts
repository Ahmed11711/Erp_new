import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { CachedLookup } from 'src/app/shared/utils/cached-lookup';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class StockService {

  private readonly allStocks = new CachedLookup<any>(() =>
    this.http.get<any>(`${environment.Url}/stocks`)
  );

  constructor(private http:HttpClient) { }


  list(params:any = {}):Observable<any>
  {
    // القائمة الكاملة تُطلب عند فتح عدة صفحات، أما الاستدعاء المفلتر فيمرّ للخادم دائماً.
    if (!params || Object.keys(params).length === 0) {
      return this.allStocks.get();
    }
    return this.http.get<any>(`${environment.Url}/stocks`,{params})
  }

  add(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/stocks`,formData).pipe(
      tap(() => this.allStocks.invalidate())
    )
  }


  edit(id:any , formData:any):Observable<any>
  {
    return this.http.put<any>(`${environment.Url}/stocks/${id}`,formData).pipe(
      tap(() => this.allStocks.invalidate())
    )
  }


  delete(id:any):Observable<any>
  {
    return this.http.delete<any>(`${environment.Url}/stocks/${id}`).pipe(
      tap(() => this.allStocks.invalidate())
    )
  }

  /**
   * Laravel JsonResource::collection يضع الصفوف في res.data.data وليس res.data مباشرة.
   */
  parseListResponse(res: any): any[] {
    const payload = res?.data;
    if (Array.isArray(payload)) {
      return payload;
    }
    if (payload && Array.isArray(payload.data)) {
      return payload.data;
    }
    return [];
  }

}