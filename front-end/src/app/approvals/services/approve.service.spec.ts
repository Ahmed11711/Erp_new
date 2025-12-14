import { TestBed } from '@angular/core/testing';

import { ApproveService } from './approve.service';

describe('ApproveService', () => {
  let service: ApproveService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(ApproveService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
