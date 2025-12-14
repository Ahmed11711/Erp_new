import { TestBed } from '@angular/core/testing';

import { ShippingWayService } from './shipping-way.service';

describe('ShippingWayService', () => {
  let service: ShippingWayService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(ShippingWayService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
