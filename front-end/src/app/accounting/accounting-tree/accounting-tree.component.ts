import { Component, OnInit } from '@angular/core';
import { TreeAccountService } from '../services/tree-account.service';
import { TreeAccount } from '../interfaces/tree-account.interface';

@Component({
  selector: 'app-accounting-tree',
  templateUrl: './accounting-tree.component.html',
  styleUrls: ['./accounting-tree.component.css']
})
export class AccountingTreeComponent implements OnInit {
  accounts: TreeAccount[] = [];
  treeData: TreeAccount[] = [];
  loading = false;
  showAddDialog = false;
  showEditDialog = false;
  selectedAccount: TreeAccount | null = null;
  expandedNodes: Set<number> = new Set();

  accountTypes = [
    { value: 'asset', label: 'أصول' },
    { value: 'liability', label: 'خصوم' },
    { value: 'equity', label: 'حقوق ملكية' },
    { value: 'revenue', label: 'إيرادات' },
    { value: 'expense', label: 'مصروفات' }
  ];

  accountTypeOptions = [
    { value: 'رئيسي', label: 'رئيسي' },
    { value: 'فرعي', label: 'فرعي' },
    { value: 'مستوى أول', label: 'مستوى أول' }
  ];

  newAccount: TreeAccount = {
    name: '',
    type: 'asset',
    balance: 0,
    debit_balance: 0,
    credit_balance: 0,
    is_trading_account: false
  };

  constructor(private treeAccountService: TreeAccountService) { }

  ngOnInit(): void {
    this.loadAccounts();
  }

  loadAccounts(): void {
    this.loading = true;
    this.treeAccountService.getAll().subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.accounts = Array.isArray(response.data) ? response.data : [];
        } else {
          this.accounts = [];
        }
        this.buildTree();
        this.loading = false;
      },
      error: (error) => {
        console.error('Error loading accounts:', error);
        this.accounts = [];
        this.treeData = [];
        this.loading = false;
      }
    });
  }

  buildTree(): void {
    // Check if accounts already have nested children structure
    const hasNestedChildren = this.accounts.some(acc => acc.children && acc.children.length > 0);
    
    if (hasNestedChildren) {
      // Use the nested structure directly
      this.treeData = this.accounts.filter(acc => !acc.parent_id);
    } else {
      // Build tree from flat list
      const accountMap = new Map<number, TreeAccount>();
      const rootAccounts: TreeAccount[] = [];

      // Create a map of all accounts
      this.accounts.forEach(account => {
        accountMap.set(account.id!, { ...account, children: [] });
      });

      // Build the tree structure
      this.accounts.forEach(account => {
        const accountNode = accountMap.get(account.id!);
        if (accountNode) {
          if (account.parent_id) {
            const parent = accountMap.get(account.parent_id);
            if (parent && parent.children) {
              parent.children!.push(accountNode);
            }
          } else {
            rootAccounts.push(accountNode);
          }
        }
      });

      this.treeData = rootAccounts;
    }
  }

  toggleNode(nodeId: number): void {
    if (this.expandedNodes.has(nodeId)) {
      this.expandedNodes.delete(nodeId);
    } else {
      this.expandedNodes.add(nodeId);
    }
  }

  isExpanded(nodeId: number): boolean {
    return this.expandedNodes.has(nodeId);
  }

  openAddDialog(parentId?: number): void {
    this.newAccount = {
      name: '',
      type: 'asset',
      balance: 0,
      debit_balance: 0,
      credit_balance: 0,
      is_trading_account: false,
      parent_id: parentId
    };
    if (parentId) {
      const parent = this.findAccountById(parentId);
      if (parent) {
        this.newAccount.type = parent.type;
      }
    }
    this.showAddDialog = true;
  }

  openEditDialog(account: TreeAccount): void {
    this.selectedAccount = { ...account };
    this.showEditDialog = true;
  }

  closeDialogs(): void {
    this.showAddDialog = false;
    this.showEditDialog = false;
    this.selectedAccount = null;
  }

  saveAccount(): void {
    if (!this.newAccount.name || !this.newAccount.type) {
      alert('الرجاء إدخال اسم الحساب ونوعه');
      return;
    }

    this.loading = true;
    this.treeAccountService.create(this.newAccount).subscribe({
      next: (response) => {
        this.loadAccounts();
        this.closeDialogs();
        this.loading = false;
      },
      error: (error) => {
        console.error('Error creating account:', error);
        alert('حدث خطأ أثناء إضافة الحساب');
        this.loading = false;
      }
    });
  }

  updateAccount(): void {
    if (!this.selectedAccount) return;

    if (!this.selectedAccount.name || !this.selectedAccount.type) {
      alert('الرجاء إدخال اسم الحساب ونوعه');
      return;
    }

    this.loading = true;
    this.treeAccountService.update(this.selectedAccount.id!, this.selectedAccount).subscribe({
      next: (response) => {
        this.loadAccounts();
        this.closeDialogs();
        this.loading = false;
      },
      error: (error) => {
        console.error('Error updating account:', error);
        alert('حدث خطأ أثناء تحديث الحساب');
        this.loading = false;
      }
    });
  }

  deleteAccount(account: TreeAccount): void {
    if (!confirm(`هل أنت متأكد من حذف الحساب "${account.name}" وجميع الحسابات الفرعية؟`)) {
      return;
    }

    this.loading = true;
    this.treeAccountService.delete(account.id!).subscribe({
      next: (response) => {
        this.loadAccounts();
        this.loading = false;
      },
      error: (error) => {
        console.error('Error deleting account:', error);
        alert('حدث خطأ أثناء حذف الحساب');
        this.loading = false;
      }
    });
  }

  findAccountById(id: number): TreeAccount | null {
    return this.accounts.find(acc => acc.id === id) || null;
  }

  getAccountTypeLabel(type: string): string {
    const accountType = this.accountTypes.find(t => t.value === type);
    return accountType ? accountType.label : type;
  }

  getIndentLevel(level: number): string {
    return `${level * 20}px`;
  }
}
