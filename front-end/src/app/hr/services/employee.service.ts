import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from 'src/env/env';

@Injectable({
  providedIn: 'root'
})
export class EmployeeService {

  constructor(private http:HttpClient) { }


  add(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/employees`,formData)
  }


  edit(id:any , formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/employees/edit/${id}`,formData)
  }


  getById(id:any){
    return this.http.get<any>(`${environment.Url}/employees/${id}`);
  }

  data(){
    return this.http.get<any>(`${environment.Url}/employees`)
  }

  absences(items:number,page:number,search:any):Observable<any>{
    return this.http.get<any>(`${environment.Url}/employees/absences?itemsPerPage=${items}&page=${page}`,{params:search});
  }

  dataPerMonth(id:number , month:any , year:any):Observable<any>{
    return this.http.get<any>(`${environment.Url}/employeepermonth/${id}?month=${month}&year=${year}`);
  }

  accountStatment(date:any):Observable<any>{
    return this.http.get<any>(`${environment.Url}/employees/accountstatment?date=${date}`);
  }

  reviewd(id:number,type:any ,value:any):Observable<any>{
    return this.http.get<any>(`${environment.Url}/employees/accountstatment/reviewed/${id}?type=${type}&value=${value}`);
  }

  EmployeesPerMonth(items:number,page:number ,month:any , year:any,search:any):Observable<any>{
    return this.http.get<any>(`${environment.Url}/employeespermonth?itemsPerPage=${items}&page=${page}&month=${month}&year=${year}`,{params:search});
  }

  searchEmployee(items:number,page:number,search:any){
  return this.http.get(`${environment.Url}/employees/search?itemsPerPage=${items}&page=${page}`,{params:search});
  }

  deleteEmp(id:number){
    return this.http.delete<any>(`${environment.Url}/employees/${id}`)
  }

  addMerit(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/employeemerit`,formData)
  }

  employeeAbsenseStatus(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/employee/absencestatus`,formData)
  }

  addSubtraction(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/employeesubtraction`,formData)
  }

  addAdvancePayment(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/employeeadvancepayment`,formData)
  }

  absenceDeduction(data:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/absencededuction`,data)
  }

  addSalaryPayment(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/employeemonthpaid`,formData)
  }

  addExtraHours(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/employeeextrahours`,formData)
  }

  permission(id:number , permission:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/give_permission/${id}`,permission)
  }


  saveExcelData(formData:any , status:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/employees/excelfingerprintdata?status=${status}`,formData)
  }

  getEmpsDataPerMonth(search:any):Observable<any>{
    return this.http.get<any>(`${environment.Url}/getEmpDataPerMonth`,{params:search});
  }

  getEmpDataPerMonth(id:number,search:any):Observable<any>{
    return this.http.get<any>(`${environment.Url}/getEmpDataPerMonth/${id}`,{params:search});
  }

  empHoursPermision(data:any):Observable<any>{
    return this.http.post<any>(`${environment.Url}/empHoursPermission`,data);
  }

  empHoursPermisionAll(data:any):Observable<any>{
    return this.http.post<any>(`${environment.Url}/empHoursPermissionall`,data);
  }

  updateFingerPrintSheet(data:any):Observable<any>{
    return this.http.post<any>(`${environment.Url}/updatefingerprintsheet`,data);
  }

  reviewMonth(month , id):Observable<any>{
    return this.http.get<any>(`${environment.Url}/reviewMonth?month=${month}&employee_id=${id}`);
  }

  addCheckOut(id:number , data:any):Observable<any>{
    return this.http.post<any>(`${environment.Url}/addCheckOut/${id}`,data);
  }

  editCheckInOrOut(id:number , data:any):Observable<any>{
    return this.http.post<any>(`${environment.Url}/editCheckInOrOut/${id}`,data);
  }

  changeCheckIn(id:number , data:any):Observable<any>{
    return this.http.post<any>(`${environment.Url}/changeCheckIn/${id}`,data);
  }

  addFixedChangedSalary(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/addFixedChangedSalary`,formData)
  }
}
