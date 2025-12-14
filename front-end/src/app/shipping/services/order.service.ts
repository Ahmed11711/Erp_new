import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class OrderService {

  constructor( private http:HttpClient) { }


  getOrders(items:number,page:number){
    return this.http.get(`${environment.Url}/orders?itemsPerPage=${items}&page=${page}`);
  }

  getTrackings(params={}){
    return this.http.get(`${environment.Url}/tracking` , {params});
  }

  getActions(){
    return this.http.get(`${environment.Url}/getActions`);
  }

  undo(id){
    return this.http.post(`${environment.Url}/tracking/undo` , {id});
  }

  getOrderById(id:number){
    return this.http.get(`${environment.Url}/orders/${id}`);
  }

  getProducts(){
    return this.http.get(`${environment.Url}/categories/cat_orders`);
  }

  getNumbers(){
    return this.http.get(`${environment.Url}/phonenumbers`);
  }

  getOrdersNumber(){
    return this.http.get(`${environment.Url}/getOrdersNumbers`);
  }

  postOrder(formData:any){
    return this.http.post(`${environment.Url}/orders` , formData);
  }

  editOrder(id:number, formData:any){
    return this.http.post(`${environment.Url}/editorder/${id}` , formData);
  }

  chngeStatus(id:number , status:string,note:string , amount:any , bank:any ,receviedOrder:any , param = {}){
    console.log(id , status);
    return this.http.get(`${environment.Url}/changestatus/${id}?status=${status}&note=${note}&amount=${amount}&bank=${bank}&receviedOrder=${receviedOrder}` , {params:param});
  }

  refuseOrder(id:number ,note:string , amount:any , bank:any ,receviedorder:any , reasoncat:any){
    return this.http.get(`${environment.Url}/refuseorder/${id}?note=${note}&amount=${amount}&bank=${bank}&getorder=${receviedorder}&reasoncat=${reasoncat}`);
  }

  confirmOrder(id:number,date:string,line_id:number,note:string , maintenReason:string){
    return this.http.post(`${environment.Url}/confirm/${id}`,{date,line_id,note,maintenReason});
  }

  shipOrder(formData:any,id:number){
    return this.http.post(`${environment.Url}/shiporder/${id}`,formData);
  }
  vipOrder(id:number){
    return this.http.get(`${environment.Url}/vip/${id}`);
  }

  shortageOrder(id:number){
    return this.http.get(`${environment.Url}/shortage/${id}`);
  }

  addShippmentNumber(id:number,value:string){
    return this.http.get(`${environment.Url}/addshippmentnumber/${id}?value=${value}`);
  }

  addTempReview(id:number,value:string){
    return this.http.get(`${environment.Url}/tempreview/${id}?value=${value}`);
  }

  readTempReview(id:number){
    return this.http.get(`${environment.Url}/readtempreview/${id}`);
  }

  addNote(id:number,value:string){
    return this.http.get(`${environment.Url}/addnote/${id}?value=${value}`);
  }

  collectOrder(id:number,formData:any){
    return this.http.post(`${environment.Url}/collectorder/${id}`,formData);
  }

  reviewOrder(formData:any){
    return this.http.post(`${environment.Url}/revieworder`,formData);
  }

  userReviewOrder(formData:any){
    return this.http.post(`${environment.Url}/userrevieworder`,formData);
  }

  partcollectOrder(id:number,formData:any){
    return this.http.post(`${environment.Url}/partcollectorder/${id}`,formData);
  }

  receivedOrder(id:number,bank:any,reason:any,maintenReason:string){
    return this.http.get(`${environment.Url}/order/received/${id}?bank=${bank}&reason=${reason}&maintenReason=${maintenReason}`);
  }
  maintainOrder(id:number,data:any){
    return this.http.post(`${environment.Url}/order/maintained/${id}`,data);
  }

  postGoogleSheet(sheet:string, data:any){
    return this.http.post(`${environment.Url}/googlesheet/${sheet}` , data);
  }
}
