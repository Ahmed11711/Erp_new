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

  updatePayableAccount(id: number, payableTreeAccountId: number | null): Observable<any> {
    return this.http.patch<any>(`${environment.Url}/employees/${id}/payable-account`, {
      payable_tree_account_id: payableTreeAccountId,
    });
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

  deleteMerit(id: number): Observable<any>
  {
    return this.http.delete<any>(`${environment.Url}/employeemerit/${id}`)
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

  bulkSalaryPayment(formData: { month: number; year: number; source_type: string; source_id: number; payments: { employee_id: number; amount: number }[] }): Observable<any> {
    return this.http.post<any>(`${environment.Url}/employeemonthpaid/bulk`, formData);
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

  revertAbsenceDayPermission(data:any):Observable<any>{
    return this.http.post<any>(`${environment.Url}/empHoursPermission/revert`,data);
  }

  empHoursPermisionAll(data:any):Observable<any>{
    return this.http.post<any>(`${environment.Url}/empHoursPermissionall`,data);
  }

  updateFingerPrintSheet(data:any):Observable<any>{
    return this.http.post<any>(`${environment.Url}/updatefingerprintsheet`,data);
  }

  reviewMonth(month, id, dateFrom?: string, dateTo?: string):Observable<any>{
    let url = `${environment.Url}/reviewMonth?month=${month}&employee_id=${id}`;
    if (dateFrom) {
      url += `&date_from=${dateFrom}`;
    }
    if (dateTo) {
      url += `&date_to=${dateTo}`;
    }
    return this.http.get<any>(url);
  }

  addCheckOut(id:number , data:any):Observable<any>{
    return this.http.post<any>(`${environment.Url}/addCheckOut/${id}`,data);
  }

  editCheckInOrOut(id:number , data:any):Observable<any>{
    return this.http.post<any>(`${environment.Url}/editCheckInOrOut/${id}`,data);
  }

  registerAttendance(data:any):Observable<any>{
    return this.http.post<any>(`${environment.Url}/registerAttendance`,data);
  }

  changeCheckIn(id:number , data:any):Observable<any>{
    return this.http.post<any>(`${environment.Url}/changeCheckIn/${id}`,data);
  }

  getFingerPrintSheetLogs(id:number):Observable<any>{
    return this.http.get<any>(`${environment.Url}/fingerprint-sheet-logs/${id}`);
  }

  addFixedChangedSalary(formData:any):Observable<any>
  {
    return this.http.post<any>(`${environment.Url}/addFixedChangedSalary`,formData)
  }

  previewPayrollAccrualImport(file: File, month: number, year: number): Observable<any> {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('month', String(month));
    fd.append('year', String(year));
    return this.http.post<any>(`${environment.Url}/payroll/accrual-import/preview`, fd);
  }

  applyPayrollAccrualImport(month: number, year: number, lines: {
    employee_id: number;
    amount: number;
    extra_day_value?: number;
    overtime_value?: number;
    rewards?: number;
    allowances?: number;
    deductions?: number;
    advance?: number;
  }[]): Observable<any> {
    return this.http.post<any>(`${environment.Url}/payroll/accrual-import/apply`, {
      month,
      year,
      lines,
    });
  }
}
