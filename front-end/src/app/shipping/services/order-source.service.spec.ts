import { TestBed } from '@angular/core/testing';

import { OrderSourceService } from './order-source.service';

describe('OrderSourceService', () => {
  let service: OrderSourceService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(OrderSourceService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
