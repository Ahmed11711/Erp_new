import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { BehaviorSubject, Observable, Subscription, catchError, interval, map, of, tap } from 'rxjs';
import { AuthService } from 'src/app/auth/auth.service';
import { environment } from 'src/env/env';

export type SystemLockActor = {
  id: number;
  name: string;
};

export type SystemLockStatus = {
  locked: boolean;
  restricted: boolean;
  can_lock: boolean;
  can_unlock: boolean;
  can_control: boolean;
  message: string;
  show_message: boolean;
  exempt_user_ids: number[];
  locked_by: SystemLockActor | null;
  locked_at: string | null;
};

export type SystemLockUser = {
  id: number;
  name: string;
  email?: string;
  department?: string;
};

export const SYSTEM_LOCK_DEFAULT_MESSAGE = 'خدمة الانترنت لا تعمل بشكل كامل\nسيحدث خطاً في تحميل البيانات';

/** صفحة العرض أثناء القفل: قائمة الطلبات الحقيقية بآخر 10 طلبات. */
export const SYSTEM_UNAVAILABLE_PATH = '/dashboard/shipping/listorders';
export const SYSTEM_UNAVAILABLE_ALIAS = '/dashboard/unavailable';

@Injectable({
  providedIn: 'root'
})
export class SystemLockService implements OnDestroy {
  private readonly statusSubject = new BehaviorSubject<SystemLockStatus>(this.emptyStatus());
  readonly status$ = this.statusSubject.asObservable();

  private pollSub?: Subscription;
  private watching = false;
  private lastFetchedAt = 0;
  private readonly staleMs = 12_000;

  constructor(
    private http: HttpClient,
    private auth: AuthService,
    private router: Router,
  ) {
    this.applyStatus(this.readFromAuthPayload(), false);
  }

  get snapshot(): SystemLockStatus {
    return this.statusSubject.value;
  }

  get locked(): boolean {
    return this.snapshot.locked;
  }

  get isRestricted(): boolean {
    return this.snapshot.restricted;
  }

  get canControl(): boolean {
    return this.snapshot.can_control;
  }

  get canLock(): boolean {
    return this.snapshot.can_lock;
  }

  get canUnlock(): boolean {
    return this.snapshot.can_unlock;
  }

  get message(): string {
    return this.snapshot.message || SYSTEM_LOCK_DEFAULT_MESSAGE;
  }

  get showMessage(): boolean {
    return this.snapshot.show_message !== false;
  }

  private readonly messageCardSubject = new BehaviorSubject<boolean>(false);
  readonly messageCard$ = this.messageCardSubject.asObservable();

  get messageCardOpen(): boolean {
    return this.messageCardSubject.value;
  }

  openMessageCard(): void {
    if (this.showMessage) {
      this.messageCardSubject.next(true);
    }
  }

  closeMessageCard(): void {
    this.messageCardSubject.next(false);
  }

  startWatching(): void {
    if (this.watching) {
      return;
    }
    this.watching = true;
    this.refresh().subscribe();
    this.pollSub = interval(20_000).subscribe(() => this.refresh().subscribe());
  }

  stopWatching(): void {
    this.watching = false;
    this.pollSub?.unsubscribe();
    this.pollSub = undefined;
  }

  refresh(): Observable<SystemLockStatus> {
    if (!this.auth.peekAccessToken()) {
      return of(this.snapshot);
    }
    const headers = new HttpHeaders({ 'X-Skip-Global-Loading': '1' });
    return this.http.get<SystemLockStatus>(`${environment.Url}/system-lock`, { headers }).pipe(
      tap((res) => {
        this.lastFetchedAt = Date.now();
        this.applyStatus(res, true);
      }),
      catchError(() => of(this.snapshot))
    );
  }

  refreshIfStale(): Observable<SystemLockStatus> {
    if (this.lastFetchedAt > 0 && (Date.now() - this.lastFetchedAt) < this.staleMs) {
      return of(this.snapshot);
    }
    return this.refresh();
  }

  updateLock(body: {
    locked: boolean;
    message?: string;
    show_message?: boolean;
    exempt_user_ids?: number[];
  }): Observable<SystemLockStatus> {
    return this.http.put<SystemLockStatus>(`${environment.Url}/system-lock`, body).pipe(
      tap((res) => {
        this.lastFetchedAt = Date.now();
        this.applyStatus(res, true);
      })
    );
  }

  fetchUsers(): Observable<SystemLockUser[]> {
    const headers = new HttpHeaders({ 'X-Skip-Global-Loading': '1' });
    return this.http.get<{ data?: SystemLockUser[] }>(`${environment.Url}/system-lock/users`, { headers }).pipe(
      map((res) => Array.isArray(res?.data) ? res.data : []),
      catchError(() => of([] as SystemLockUser[]))
    );
  }

  applyFromHttpError(error: { status?: number; error?: { code?: string; message?: string } }): void {
    if (error?.status !== 503 || error?.error?.code !== 'SYSTEM_LOCKED') {
      return;
    }
    this.applyStatus({
      ...this.snapshot,
      locked: true,
      restricted: !(this.snapshot.can_lock || this.snapshot.can_unlock || this.snapshot.can_control),
      message: error.error?.message || this.snapshot.message || SYSTEM_LOCK_DEFAULT_MESSAGE,
    }, true);
  }

  applyStatus(raw: Partial<SystemLockStatus> | null | undefined, navigate: boolean): void {
    const next = this.normalize(raw);
    const prevRestricted = this.statusSubject.value.restricted;
    this.statusSubject.next(next);
    if (!navigate) {
      return;
    }
    if (next.restricted && !this.isLockPreviewUrl()) {
      void this.router.navigateByUrl(SYSTEM_UNAVAILABLE_PATH);
      return;
    }
    if (!next.restricted && prevRestricted && this.isUnavailableAlias()) {
      this.closeMessageCard();
      void this.router.navigateByUrl('/dashboard');
    }
    if (!next.restricted) {
      this.closeMessageCard();
    }
  }

  isUnavailableUrl(url?: string): boolean {
    return this.isLockPreviewUrl(url);
  }

  isUnavailableAlias(url?: string): boolean {
    const path = this.pathOf(url);
    return path === SYSTEM_UNAVAILABLE_ALIAS || path.startsWith(`${SYSTEM_UNAVAILABLE_ALIAS}/`);
  }

  isLockPreviewUrl(url?: string): boolean {
    const path = this.pathOf(url);
    return path === SYSTEM_UNAVAILABLE_PATH
      || path.startsWith(`${SYSTEM_UNAVAILABLE_PATH}/`)
      || this.isUnavailableAlias(url);
  }

  ordersPreview(): Observable<any> {
    const headers = new HttpHeaders({ 'X-Skip-Global-Loading': '1' });
    return this.http.get(`${environment.Url}/system-lock/orders-preview`, { headers });
  }

  private pathOf(url?: string): string {
    return (url || this.router.url || '').split('?')[0];
  }

  ngOnDestroy(): void {
    this.stopWatching();
  }

  private readFromAuthPayload(): Partial<SystemLockStatus> | null {
    const payload = this.auth.getStoredAuthPayload();
    const raw = payload?.['system_lock'];
    if (raw && typeof raw === 'object') {
      return raw as Partial<SystemLockStatus>;
    }
    return null;
  }

  private normalize(raw: Partial<SystemLockStatus> | null | undefined): SystemLockStatus {
    const exempt = Array.isArray(raw?.exempt_user_ids)
      ? raw!.exempt_user_ids.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0)
      : [];
    return {
      locked: !!raw?.locked,
      restricted: !!raw?.restricted,
      can_lock: !!raw?.can_lock,
      can_unlock: !!raw?.can_unlock,
      can_control: !!(raw?.can_control || raw?.can_lock || raw?.can_unlock),
      message: String(raw?.message || SYSTEM_LOCK_DEFAULT_MESSAGE),
      show_message: raw?.show_message !== false,
      exempt_user_ids: exempt,
      locked_by: raw?.locked_by && typeof raw.locked_by === 'object'
        ? { id: Number(raw.locked_by.id), name: String(raw.locked_by.name || '') }
        : null,
      locked_at: raw?.locked_at ? String(raw.locked_at) : null,
    };
  }

  private emptyStatus(): SystemLockStatus {
    return {
      locked: false,
      restricted: false,
      can_lock: false,
      can_unlock: false,
      can_control: false,
      message: SYSTEM_LOCK_DEFAULT_MESSAGE,
      show_message: true,
      exempt_user_ids: [],
      locked_by: null,
      locked_at: null,
    };
  }
}
