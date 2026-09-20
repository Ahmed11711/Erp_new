import { Injectable } from '@angular/core';
import { PreloadingStrategy, Route } from '@angular/router';
import { Observable, of, timer } from 'rxjs';
import { switchMap } from 'rxjs/operators';

/**
 * يحمّل موديولات محددة بعد استقرار الصفحة الأولى حتى يكون التنقل التالي فورياً
 * دون إبطاء أول فتح للداشبورد.
 */
@Injectable({ providedIn: 'root' })
export class SelectivePreloadStrategy implements PreloadingStrategy {
  preload(route: Route, load: () => Observable<unknown>): Observable<unknown> {
    if (!route.data?.['preload']) {
      return of(null);
    }
    return timer(2500).pipe(switchMap(() => load()));
  }
}
