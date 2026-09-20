import { Observable, Subject } from 'rxjs';
import { CachedLookup } from './cached-lookup';

describe('CachedLookup', () => {
  it('يستدعي الخادم مرة واحدة ثم يخدم من الذاكرة', () => {
    let calls = 0;
    const lookup = new CachedLookup<number[]>(() => {
      calls++;
      return new Observable<number[]>((sub) => {
        sub.next([1, 2]);
        sub.complete();
      });
    });

    let first: number[] | undefined;
    let second: number[] | undefined;
    lookup.get().subscribe((v) => (first = v));
    lookup.get().subscribe((v) => (second = v));

    expect(calls).toBe(1);
    expect(first).toEqual([1, 2]);
    expect(second).toEqual([1, 2]);
  });

  it('يوحّد الطلبات المتزامنة في نداء واحد', () => {
    let calls = 0;
    const source = new Subject<string[]>();
    const lookup = new CachedLookup<string[]>(() => {
      calls++;
      return source.asObservable();
    });

    const received: string[][] = [];
    lookup.get().subscribe((v) => received.push(v));
    lookup.get().subscribe((v) => received.push(v));

    expect(calls).toBe(1);

    source.next(['a']);
    expect(received).toEqual([['a'], ['a']]);
  });

  it('يعيد الجلب بعد invalidate', () => {
    let calls = 0;
    const lookup = new CachedLookup<number>(() => {
      calls++;
      const current = calls;
      return new Observable<number>((sub) => {
        sub.next(current);
        sub.complete();
      });
    });

    let value: number | undefined;
    lookup.get().subscribe((v) => (value = v));
    expect(value).toBe(1);

    lookup.invalidate();
    lookup.get().subscribe((v) => (value = v));

    expect(calls).toBe(2);
    expect(value).toBe(2);
  });

  it('لا يخزّن نتيجة فاشلة ويعيد المحاولة', () => {
    let calls = 0;
    const lookup = new CachedLookup<number>(() => {
      calls++;
      return new Observable<number>((sub) => {
        if (calls === 1) {
          sub.error(new Error('boom'));
          return;
        }
        sub.next(7);
        sub.complete();
      });
    });

    let failed = false;
    lookup.get().subscribe({ error: () => (failed = true) });
    expect(failed).toBeTrue();

    let value: number | undefined;
    lookup.get().subscribe((v) => (value = v));

    expect(calls).toBe(2);
    expect(value).toBe(7);
  });
});
