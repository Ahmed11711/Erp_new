import { TestBed } from '@angular/core/testing';

import { CimmitmentService } from './cimmitment.service';

describe('CimmitmentService', () => {
  let service: CimmitmentService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(CimmitmentService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
