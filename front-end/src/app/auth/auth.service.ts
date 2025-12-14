import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { CookieService } from 'ngx-cookie-service';
import { catchError, Observable, throwError } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class AuthService {

  constructor(private http:HttpClient , private cookie:CookieService , private route:Router) { }

  login(data: object): any {
    return this.http.post(`${environment.Url}/auth/login`, data)
      .pipe(
        catchError(error => {
          console.error('Login error:', error);
          throw error; // Rethrow the error to propagate it further
        })
      );
  }

  saveTolocalStorage(data:any){

    if (this.cookie.get("magalis")) {
      this.cookie.delete( 'magalis' , '/' );
    }

    const expirationDate = new Date();

    expirationDate.setTime(expirationDate.getTime() + data.expires_in * 60000);
    this.cookie.set('magalis',JSON.stringify(data) , expirationDate , '/' , undefined , true, 'Strict');

    // localStorage.setItem('magalis', JSON.stringify(data));
  }

  getToken(){
    const data = this.cookie.get("magalis") || '';
    const currentUrl = this.route.url;
    if (currentUrl !== '/' && data == '') {
      location.reload();
    }
    if (data !== '' ) {
      const parsedData = JSON.parse(data);
      return parsedData.access_token;
    }
    return false;
    // const datas = localStorage.getItem('magalis')
    // if (datas !== null) {
    //   const parsedData = JSON.parse(datas);
    //   return parsedData.access_token;
    // }
  }

  getPermission(){
    const data = this.cookie.get("magalis") || '';
    if (data !== '' ) {
      const parsedData = JSON.parse(data);
      const permission = parsedData.permissions.map(elm=>elm.toLowerCase());
      return permission;
    }
    return false;
  }

  getUser(){
    const data = this.cookie.get("magalis") || '';
    if (data !== '' ) {
      const parsedData = JSON.parse(data);
      return parsedData.user;
    }
    return false;
  }

  userName(){
    const data = this.cookie.get("magalis") || '';
    if (data !== '' ) {
      const parsedData = JSON.parse(data);
      return parsedData.name;
    }
    return false;
  }

  logOut() {
      this.cookie.delete( 'magalis' , '/' );
  }

}
