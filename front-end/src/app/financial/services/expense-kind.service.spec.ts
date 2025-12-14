import { TestBed } from '@angular/core/testing';

import { ExpenseKindService } from './expense-kind.service';

describe('ExpenseKindService', () => {
  let service: ExpenseKindService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(ExpenseKindService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
