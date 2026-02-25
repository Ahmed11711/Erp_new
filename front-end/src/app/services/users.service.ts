import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../env/env';

@Injectable({
  providedIn: 'root'
})
export class UsersService {
  constructor(private http: HttpClient) {}

  getUsers(): Observable<any> {
    return this.http.get<any>(`${environment.Url}/users`);
  }

  getUserById(id: number): Observable<any> {
    return this.http.get<any>(`${environment.Url}/users/${id}`);
  }

  createUser(userData: any): Observable<any> {
    return this.http.post<any>(`${environment.Url}/users`, userData);
  }

  updateUser(id: number, userData: any): Observable<any> {
    return this.http.put<any>(`${environment.Url}/users/${id}`, userData);
  }

  deleteUser(id: number): Observable<any> {
    return this.http.delete<any>(`${environment.Url}/users/${id}`);
  }
}
