import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { environment } from 'src/env/env';
@Injectable({
  providedIn: 'root',
})
export class NotificationService {

  private counter = new BehaviorSubject(0);
  counterVal = this.counter.asObservable();

  setCounter(newCounterVal:number){
    this.counter.next(newCounterVal);
  }

  recievedParam:any = {};

  constructor(private http: HttpClient) {


    //   const echo = new Echo({
    //   broadcaster: 'pusher',
    //   key: environment.pusher.key,
    //   cluster: environment.pusher.cluster,
    //   // encrypted: true,
    //   wsHost: window.location.hostname,
    //   wsPort: 6001, // Use the same port as your Laravel WebSocket server
    // });

    // // Example: Listen for a channel
    // echo.channel('notifications')
    //   .listen('NotificationSent', (data) => {
    //     console.log('Real-time notification received:', data);
    //     // Handle the real-time notification
    //   });
  }

  sendNotification(data: any): Observable<any> {
    return this.http.post<any>(`${environment.Url}/notification`, data);
  }

  getById(): Observable<any> {
    return this.http.get<any>(`${environment.Url}/notification`);
  }

  readNotify(id:number): Observable<any> {
    return this.http.get<any>(`${environment.Url}/notification/${id}`);
  }

  readOrderNotify(id:number , data:any): Observable<any> {
    return this.http.post<any>(`${environment.Url}/notification/${id}` , {orders:data});
  }

  recievedNotifiy(items:number,page:number){
    return this.http.get(`${environment.Url}/recievednotification?itemsPerPage=${items}&page=${page}`,{params:this.recievedParam});
  }

  sentNotifiy(items:number,page:number,search:any){
    return this.http.get(`${environment.Url}/sentnotification?itemsPerPage=${items}&page=${page}`,{params:search});
  }

  allNotifiy(items:number,page:number,search:any){
    return this.http.get(`${environment.Url}/allnotification?itemsPerPage=${items}&page=${page}`,{params:search});
  }

  delete(id:number){
    return this.http.delete<any>(`${environment.Url}/notification/delete/${id}`)
  }

}
