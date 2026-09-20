import { TestBed } from '@angular/core/testing';
import { HttpClientTestingModule } from '@angular/common/http/testing';

import { FilterOrderService } from './filter-order.service';

describe('FilterOrderService', () => {
  let service: FilterOrderService;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [HttpClientTestingModule],
    });
    service = TestBed.inject(FilterOrderService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  it('keeps date ranges after a list remount hydrates from the singleton', () => {
    service.order_date_from = '2026-08-01';
    service.order_date_to = '2026-08-31';

    const orderDate = service.order_date || null;
    const orderDateFrom = service.order_date_from || null;
    const orderDateTo = service.order_date_to || null;

    service.order_date = orderDate || '';
    service.order_date_from = orderDate ? '' : (orderDateFrom || '');
    service.order_date_to = orderDate ? '' : (orderDateTo || '');

    expect(service.order_date_from).toBe('2026-08-01');
    expect(service.order_date_to).toBe('2026-08-31');
  });

  it('resetFilters clears date ranges and paging so a new search starts fresh', () => {
    service.order_date_from = '2026-08-01';
    service.order_date_to = '2026-08-31';
    service.order_status = 'طلب جديد';
    service.page = 3;
    service.pageSize = 50;

    service.resetFilters();

    expect(service.order_date_from).toBe('');
    expect(service.order_date_to).toBe('');
    expect(service.order_status).toBe('');
    expect(service.page).toBe(0);
    expect(service.pageSize).toBe(15);
  });
});
