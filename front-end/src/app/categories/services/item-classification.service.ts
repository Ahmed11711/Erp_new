import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root',
})
export class ItemClassificationService {
  constructor(private http: HttpClient) {}

  addClassification(warehouse: string, classification_name: string) {
    return this.http.post(`${environment.Url}/item-classifications`, { warehouse, classification_name });
  }

  getClassifications() {
    return this.http.get(`${environment.Url}/item-classifications`);
  }

  deleteClassification(id: number) {
    return this.http.delete(`${environment.Url}/item-classifications/${id}`);
  }
}
