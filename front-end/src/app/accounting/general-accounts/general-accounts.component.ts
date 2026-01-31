import { Component, OnInit } from '@angular/core';
import { TreeAccount } from '../interfaces/tree-account.interface';
import { TreeAccountService } from '../services/tree-account.service';

@Component({
  selector: 'app-general-accounts',
  templateUrl: './general-accounts.component.html',
  styleUrls: ['./general-accounts.component.css']
})
export class GeneralAccountsComponent implements OnInit {
  accounts: TreeAccount[] = [];
  filteredAccounts: TreeAccount[] = [];
  loading: boolean = false;
  searchQuery: string = '';
  selectedType: string = '';

  accountTypes = [
    { value: 'asset', label: 'أصول' },
    { value: 'liability', label: 'التزامات' },
    { value: 'equity', label: 'حقوق ملكية' },
    { value: 'revenue', label: 'إيرادات' },
    { value: 'expense', label: 'مصروفات' }
  ];

  constructor(private treeAccountService: TreeAccountService) { }

  ngOnInit(): void {
    this.loadAccounts();
  }

  loadAccounts() {
    this.loading = true;
    this.treeAccountService.getAll().subscribe({
      next: (response) => {
        if (response.success) {
          this.accounts = response.data;
          this.filterAccounts();
        }
        this.loading = false;
      },
      error: (err) => {
        console.error('Error loading accounts', err);
        this.loading = false;
      }
    });
  }

  filterAccounts() {
    this.filteredAccounts = this.accounts.filter(account => {
      const matchQuery = !this.searchQuery ||
        account.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
        account.code?.toString().includes(this.searchQuery);

      const matchType = !this.selectedType || account.type === this.selectedType;

      return matchQuery && matchType;
    });
  }

  getAccountTypeLabel(type: string): string {
    const found = this.accountTypes.find(t => t.value === type);
    return found ? found.label : type;
  }

  refresh() {
    this.loadAccounts();
  }
}

