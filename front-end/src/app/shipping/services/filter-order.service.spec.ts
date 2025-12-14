import { TestBed } from '@angular/core/testing';

import { FilterOrderService } from './filter-order.service';

describe('FilterOrderService', () => {
  let service: FilterOrderService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(FilterOrderService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
