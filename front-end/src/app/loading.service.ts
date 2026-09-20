import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';


@Injectable({
  providedIn: 'root'
})
export class LoadingService {

  private loadingSubject = new BehaviorSubject<boolean>(false);
  loading$ = this.loadingSubject.asObservable();
  /** عدد الطلبات الجارية — يمنع اختفاء الغطاء بين طلبات متوازية. */
  private activeRequests = 0;
  private showTimer: ReturnType<typeof setTimeout> | null = null;
  /** لا تُظهر الغطاء للطلبات السريعة حتى لا تومض الصفحة عند كل حفظ. */
  private readonly showDelayMs = 280;

  showLoading() {
    this.activeRequests++;
    if (this.activeRequests === 1 && !this.showTimer) {
      this.showTimer = setTimeout(() => {
        this.showTimer = null;
        if (this.activeRequests > 0) {
          this.loadingSubject.next(true);
        }
      }, this.showDelayMs);
    }
  }

  hideLoading() {
    if (this.activeRequests > 0) {
      this.activeRequests--;
    }
    if (this.activeRequests === 0) {
      if (this.showTimer) {
        clearTimeout(this.showTimer);
        this.showTimer = null;
      }
      this.loadingSubject.next(false);
    }
  }

}
