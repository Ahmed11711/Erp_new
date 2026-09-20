import { fakeAsync, TestBed, tick } from '@angular/core/testing';

import { LoadingService } from './loading.service';

describe('LoadingService', () => {
  let service: LoadingService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(LoadingService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  it('does not show the overlay until the delay elapses', fakeAsync(() => {
    let shown = false;
    service.loading$.subscribe((value) => {
      shown = value;
    });

    service.showLoading();
    expect(shown).toBeFalse();
    tick(279);
    expect(shown).toBeFalse();
    tick(1);
    expect(shown).toBeTrue();

    service.hideLoading();
    expect(shown).toBeFalse();
  }));

  it('cancels a pending overlay when the request finishes quickly', fakeAsync(() => {
    let shown = false;
    service.loading$.subscribe((value) => {
      shown = value;
    });

    service.showLoading();
    tick(100);
    service.hideLoading();
    tick(500);
    expect(shown).toBeFalse();
  }));
});
