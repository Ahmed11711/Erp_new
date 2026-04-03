import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { of } from 'rxjs';
import { AccountingReportService } from 'src/app/accounting/services/accounting-report.service';
import { SafeService } from 'src/app/accounting/services/safe.service';
import { BankService } from 'src/app/accounting/services/bank.service';
import { ServiceAccountsService } from 'src/app/financial/services/service-accounts.service';

import { FinancialStatementComponent } from './financial-statement.component';

describe('FinancialStatementComponent', () => {
  let component: FinancialStatementComponent;
  let fixture: ComponentFixture<FinancialStatementComponent>;

  beforeEach(() => {
    const accountingStub = {
      getAccountingTree: () => of([]),
      getAccountStatement: () => of({ entries: [], account: null, opening_balance: 0, closing_balance: 0, total_debit: 0, total_credit: 0 })
    };

    const routeStub = {
      queryParamMap: of({ get: () => null }),
      snapshot: { queryParamMap: { get: () => null } }
    };

    const routerStub = {
      navigate: jasmine.createSpy('navigate')
    };

    TestBed.configureTestingModule({
      imports: [ReactiveFormsModule, FormsModule],
      declarations: [FinancialStatementComponent],
      providers: [
        { provide: AccountingReportService, useValue: accountingStub },
        { provide: SafeService, useValue: { getAll: () => of({ data: [] }) } },
        { provide: BankService, useValue: { getAll: () => of({ data: [] }) } },
        { provide: ServiceAccountsService, useValue: { index: () => of({ data: [] }) } },
        { provide: ActivatedRoute, useValue: routeStub },
        { provide: Router, useValue: routerStub }
      ]
    });
    fixture = TestBed.createComponent(FinancialStatementComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
