import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class ServiceAccountsService {

  constructor(private http: HttpClient) { }

  index() {
    return this.http.get(environment.Url + '/accounting/service-accounts');
  }

  store(data: any) {
    return this.http.post(environment.Url + '/accounting/service-accounts', data);
  }

  update(id: any, data: any) {
    return this.http.put(environment.Url + '/accounting/service-accounts/' + id, data);
  }

  transfer(data: any) {
    return this.http.post(environment.Url + '/accounting/service-accounts/transfer', data);
  }
}
