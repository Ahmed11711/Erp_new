import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { CachedLookup } from 'src/app/shared/utils/cached-lookup';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root',
})
export class ItemClassificationService {
  private readonly classifications = new CachedLookup<unknown>(() =>
    this.http.get(`${environment.Url}/item-classifications`)
  );

  constructor(private http: HttpClient) {}

  addClassification(warehouse: string, classification_name: string) {
    return this.http.post(`${environment.Url}/item-classifications`, { warehouse, classification_name }).pipe(
      tap(() => this.classifications.invalidate())
    );
  }

  getClassifications(): Observable<unknown> {
    return this.classifications.get();
  }

  deleteClassification(id: number) {
    return this.http.delete(`${environment.Url}/item-classifications/${id}`).pipe(
      tap(() => this.classifications.invalidate())
    );
  }
}
