import { TestBed } from '@angular/core/testing';

import { ShippingLinesService } from './shipping-lines.service';

describe('ShippingLinesService', () => {
  let service: ShippingLinesService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(ShippingLinesService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
