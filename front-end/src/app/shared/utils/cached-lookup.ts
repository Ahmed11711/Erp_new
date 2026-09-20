import { Observable, finalize, of, shareReplay, tap } from 'rxjs';

/**
 * ذاكرة مؤقتة لقوائم المراجع الصغيرة (خطوط الإنتاج، التصنيفات، المخازن، فئات الموردين…).
 *
 * سيرفر التطوير `php artisan serve` يخدم طلباً واحداً في كل مرة، فكل نداء إضافي عند فتح
 * الصفحة يُضاف إلى الطابور. هذه القوائم صغيرة ونادرة التغيير وتُطلب من عدة صفحات، فتخزينها
 * يحذف النداء بالكامل بعد أول مرة.
 *
 * كما يوحّد الطلبات المتزامنة: إذا طلب أكثر من مكوّن نفس القائمة في نفس اللحظة يُنفّذ
 * نداء HTTP واحد فقط.
 */
export class CachedLookup<T> {
  private value: T | null = null;
  private inFlight$: Observable<T> | null = null;

  constructor(private readonly fetcher: () => Observable<T>) {}

  get(): Observable<T> {
    if (this.value !== null) {
      return of(this.value);
    }

    if (!this.inFlight$) {
      this.inFlight$ = this.fetcher().pipe(
        tap((value) => {
          this.value = value;
        }),
        finalize(() => {
          this.inFlight$ = null;
        }),
        shareReplay({ bufferSize: 1, refCount: true })
      );
    }

    return this.inFlight$;
  }

  /** يُستدعى بعد أي إضافة أو تعديل أو حذف حتى لا تبقى القائمة قديمة. */
  invalidate(): void {
    this.value = null;
    this.inFlight$ = null;
  }
}
