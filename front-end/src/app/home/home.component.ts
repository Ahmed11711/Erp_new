import { Component } from '@angular/core';
import { NotificationService } from '../notification/service/notification.service';
import { AuthService } from '../auth/auth.service';
import { CategoryService } from '../categories/services/category.service';
import { ServiceAccountsService } from '../financial/services/service-accounts.service';
import { SafeService } from '../accounting/services/safe.service';
import { BankService } from '../accounting/services/bank.service';

@Component({
  selector: 'app-home',
  templateUrl: './home.component.html',
  styleUrls: ['./home.component.css']
})
export class HomeComponent {
  notifications: any[] = [];
  user!: string;

  serviceAccounts: any[] = [];

  totalSafeBalance: number = 0;
  totalBankBalance: number = 0;

  constructor(
    private authService: AuthService,
    private categoryService: CategoryService,
    private serviceAccountsService: ServiceAccountsService,
    private safeService: SafeService,
    private bankService: BankService
  ) { }

  ngOnInit(): void {
    this.user = this.authService.getUser();
    this.getServiceAccounts();
    this.getFinancialSummary();
  }

  getServiceAccounts() {
    this.serviceAccountsService.index().subscribe((res: any) => {
      this.serviceAccounts = res;
    });
  }

  getFinancialSummary() {
    this.safeService.getAll().subscribe((res: any) => {
      const safes = res.data || (Array.isArray(res) ? res : []);
      this.totalSafeBalance = safes.reduce((sum: number, item: any) => sum + (Number(item.balance) || 0), 0);
    });

    this.bankService.getAll().subscribe((res: any) => {
      const banks = res.data || (Array.isArray(res) ? res : []);
      this.totalBankBalance = banks.reduce((sum: number, item: any) => sum + (Number(item.balance) || 0), 0);
    });
  }


}
