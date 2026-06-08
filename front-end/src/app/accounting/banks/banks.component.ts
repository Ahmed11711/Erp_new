import { Component, OnInit } from '@angular/core';
import { FormControl } from '@angular/forms';
import { MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { BankService } from '../services/bank.service';
import { TreeAccountService } from '../services/tree-account.service';
import { MatDialog } from '@angular/material/dialog';
import { ToastService } from '../../shared/toast/toast.service';
import { ConfirmDialogComponent } from '../../shared/confirm-dialog/confirm-dialog.component';

interface AccountOption {
  id: number;
  label: string;
}

@Component({
  selector: 'app-banks',
  templateUrl: './banks.component.html',
  styleUrls: ['./banks.component.css']
})
export class BanksComponent implements OnInit {
  banks: any[] = [];
  filteredBanks: any[] = [];
  accountOptions: AccountOption[] = [];
  filteredParentAccounts: AccountOption[] = [];
  filteredCounterAccounts: AccountOption[] = [];
  parentAccountCtrl = new FormControl<string | AccountOption>('', { nonNullable: true });
  counterAccountCtrl = new FormControl<string | AccountOption>('', { nonNullable: true });
  readonly accountAutocompleteCap = 400;
  loading = false;
  saving = false;
  searchTerm = '';

  showAddDialog = false;
  showEditDialog = false;
  showTransferDialog = false;

  newBank: any = this.getEmptyBank();
  selectedBank: any = null;
  transferData: any = this.getEmptyTransfer();

  get totalBalance(): number {
    return this.banks.reduce((sum, b) => sum + (Number(b.balance) || 0), 0);
  }

  constructor(
    private bankService: BankService,
    private treeAccountService: TreeAccountService,
    private dialog: MatDialog,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.getAllBanks();
    this.getTreeAccounts();
    this.parentAccountCtrl.valueChanges.subscribe(v => {
      this.applyParentAccountFilter(typeof v === 'string' ? v : '');
    });
    this.counterAccountCtrl.valueChanges.subscribe(v => {
      this.applyCounterAccountFilter(typeof v === 'string' ? v : '');
    });
  }

  displayAccountOption = (value: string | AccountOption | null): string => {
    if (!value) return '';
    return typeof value === 'string' ? value : value.label;
  };

  private getEmptyBank() {
    return {
      name: '',
      type: 'main',
      balance: 0,
      usage: '',
      parent_account_id: null,
      counter_account_id: null
    };
  }

  private getEmptyTransfer() {
    return {
      type: 'transfer_bank_to_bank',
      from_id: null,
      to_id: null,
      amount: 0,
      date: new Date().toISOString().split('T')[0],
      notes: ''
    };
  }

  onSearch(): void {
    if (!this.searchTerm.trim()) {
      this.filteredBanks = [...this.banks];
      return;
    }
    const term = this.searchTerm.trim().toLowerCase();
    this.filteredBanks = this.banks.filter(b =>
      b.name?.toLowerCase().includes(term) ||
      b.usage?.toLowerCase().includes(term) ||
      b.asset?.name?.toLowerCase().includes(term)
    );
  }

  clearSearch(): void {
    this.searchTerm = '';
    this.filteredBanks = [...this.banks];
  }

  getAllBanks(): void {
    this.loading = true;
    this.bankService.getAll().subscribe({
      next: (res) => {
        const raw = res.data ?? res;
        this.banks = Array.isArray(raw) ? raw : (raw?.data ?? []);
        this.filteredBanks = [...this.banks];
        this.loading = false;
      },
      error: () => {
        this.toast.error('حدث خطأ أثناء تحميل البنوك');
        this.loading = false;
      }
    });
  }

  getTreeAccounts(): void {
    this.treeAccountService.getAll().subscribe({
      next: (res) => {
        const raw = (res as any)?.data;
        const arr = Array.isArray(raw) ? raw : raw ? [raw] : Array.isArray(res) ? res : [];
        const flat = this.flattenAccounts(arr);
        if (flat.length > 0) {
          this.setAccountOptions(flat);
          return;
        }
        this.treeAccountService.getTree().subscribe({
          next: (treeRes: any) => {
            const t = treeRes?.data ?? treeRes;
            const tArr = Array.isArray(t) ? t : t ? [t] : [];
            this.setAccountOptions(this.flattenAccounts(tArr));
          },
          error: () => this.toast.error('تعذر تحميل شجرة الحسابات')
        });
      },
      error: () => this.toast.error('تعذر تحميل شجرة الحسابات')
    });
  }

  private setAccountOptions(options: AccountOption[]): void {
    this.accountOptions = options;
    this.applyParentAccountFilter('');
    this.applyCounterAccountFilter('');
  }

  private flattenAccounts(nodes: any[]): AccountOption[] {
    const out: AccountOption[] = [];
    const walk = (list: any[]) => {
      for (const n of list || []) {
        if (n?.id != null && n?.name) {
          const code = n.code != null && n.code !== '' ? String(n.code) : '';
          out.push({
            id: Number(n.id),
            label: code ? `${n.name} — ${code}` : String(n.name)
          });
        }
        if (Array.isArray(n?.children) && n.children.length) {
          walk(n.children);
        }
      }
    };
    walk(nodes);
    return out.sort((a, b) => a.label.localeCompare(b.label, 'ar'));
  }

  private applyParentAccountFilter(term: string): void {
    this.filteredParentAccounts = this.filterAccountOptions(term);
  }

  private applyCounterAccountFilter(term: string): void {
    this.filteredCounterAccounts = this.filterAccountOptions(term);
  }

  private filterAccountOptions(term: string): AccountOption[] {
    const raw = String(term ?? '').trim();
    const q = raw.toLowerCase();
    let list = this.accountOptions;
    if (q) {
      list = list.filter(a => {
        if (String(a.id).includes(raw)) return true;
        return a.label.toLowerCase().includes(q) || a.label.includes(raw);
      });
    }
    return list.slice(0, this.accountAutocompleteCap);
  }

  onParentAccountFocus(): void {
    const v = this.parentAccountCtrl.value;
    this.applyParentAccountFilter(typeof v === 'string' ? v : '');
  }

  onCounterAccountFocus(): void {
    const v = this.counterAccountCtrl.value;
    this.applyCounterAccountFilter(typeof v === 'string' ? v : '');
  }

  onParentAccountSelected(event: MatAutocompleteSelectedEvent): void {
    const acc = event.option.value as AccountOption;
    if (!acc?.id) return;
    this.newBank.parent_account_id = acc.id;
    this.parentAccountCtrl.setValue(acc, { emitEvent: false });
    this.applyParentAccountFilter('');
  }

  onCounterAccountSelected(event: MatAutocompleteSelectedEvent): void {
    const acc = event.option.value as AccountOption;
    if (!acc?.id) return;
    this.newBank.counter_account_id = acc.id;
    this.counterAccountCtrl.setValue(acc, { emitEvent: false });
    this.applyCounterAccountFilter('');
  }

  onParentAccountBlur(): void {
    setTimeout(() => this.syncAccountCtrlOnBlur(this.parentAccountCtrl, 'parent_account_id'), 150);
  }

  onCounterAccountBlur(): void {
    setTimeout(() => this.syncAccountCtrlOnBlur(this.counterAccountCtrl, 'counter_account_id'), 150);
  }

  private syncAccountCtrlOnBlur(
    ctrl: FormControl<string | AccountOption | null>,
    field: 'parent_account_id' | 'counter_account_id'
  ): void {
    const v = ctrl.value;
    if (v && typeof v === 'object') return;

    const str = typeof v === 'string' ? v.trim() : '';
    if (!str) {
      this.newBank[field] = null;
      return;
    }

    const exact = this.accountOptions.find(a => a.label === str);
    if (exact) {
      this.newBank[field] = exact.id;
      ctrl.setValue(exact, { emitEvent: false });
      return;
    }

    this.newBank[field] = null;
    ctrl.setValue(str, { emitEvent: false });
  }

  private resetAccountAutocomplete(): void {
    this.parentAccountCtrl.setValue('', { emitEvent: false });
    this.counterAccountCtrl.setValue('', { emitEvent: false });
    this.applyParentAccountFilter('');
    this.applyCounterAccountFilter('');
  }

  openAddDialog(): void {
    this.newBank = this.getEmptyBank();
    this.resetAccountAutocomplete();
    this.showAddDialog = true;
  }

  openEditDialog(bank: any): void {
    this.selectedBank = { ...bank };
    this.showEditDialog = true;
  }

  openTransferDialog(fromBank?: any): void {
    this.transferData = this.getEmptyTransfer();
    if (fromBank) {
      this.transferData.from_id = fromBank.id;
    }
    this.showTransferDialog = true;
  }

  closeDialogs(): void {
    this.showAddDialog = false;
    this.showEditDialog = false;
    this.showTransferDialog = false;
    this.selectedBank = null;
  }

  canSaveNew(): boolean {
    if (!this.newBank.name?.trim()) return false;
    if (!this.newBank.parent_account_id) return false;
    const bal = Number(this.newBank.balance) || 0;
    if (bal > 0 && !this.newBank.counter_account_id) return false;
    return true;
  }

  saveBank(): void {
    if (!this.canSaveNew()) {
      this.toast.warning('الرجاء تعبئة الحقول المطلوبة');
      return;
    }

    this.saving = true;
    this.bankService.create(this.newBank).subscribe({
      next: () => {
        this.toast.success('تم إضافة البنك بنجاح');
        this.getAllBanks();
        this.closeDialogs();
        this.saving = false;
      },
      error: (err) => {
        this.toast.error(err.error?.message || 'حدث خطأ أثناء الحفظ');
        this.saving = false;
      }
    });
  }

  canSaveEdit(): boolean {
    return !!this.selectedBank?.name?.trim();
  }

  updateBank(): void {
    if (!this.canSaveEdit()) {
      this.toast.warning('الرجاء تعبئة الحقول المطلوبة');
      return;
    }

    this.saving = true;
    this.bankService.update(this.selectedBank.id, {
      name: this.selectedBank.name,
      usage: this.selectedBank.usage,
      type: this.selectedBank.type
    }).subscribe({
      next: () => {
        this.toast.success('تم تحديث بيانات البنك بنجاح');
        this.getAllBanks();
        this.closeDialogs();
        this.saving = false;
      },
      error: (err) => {
        this.toast.error(err.error?.message || 'حدث خطأ أثناء التحديث');
        this.saving = false;
      }
    });
  }

  deleteBank(bank: any): void {
    if (Number(bank.balance) !== 0) {
      this.toast.warning('لا يمكن حذف بنك يحتوي على رصيد');
      return;
    }

    this.dialog.open(ConfirmDialogComponent, {
      data: {
        title: 'حذف البنك',
        message: `هل أنت متأكد من حذف بنك "${bank.name}"؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        type: 'danger'
      },
      width: '420px',
      direction: 'rtl'
    }).afterClosed().subscribe(confirmed => {
      if (!confirmed) return;
      this.loading = true;
      this.bankService.delete(bank.id).subscribe({
        next: () => {
          this.toast.success('تم حذف البنك بنجاح');
          this.getAllBanks();
        },
        error: (err) => {
          this.toast.error(err.error?.message || 'حدث خطأ أثناء الحذف');
          this.loading = false;
        }
      });
    });
  }

  getFromBankBalance(): number {
    const bank = this.banks.find(b => b.id == this.transferData.from_id);
    return bank ? Number(bank.balance) || 0 : 0;
  }

  canSubmitTransfer(): boolean {
    return !!this.transferData.from_id &&
           !!this.transferData.to_id &&
           this.transferData.amount > 0 &&
           this.transferData.from_id !== this.transferData.to_id;
  }

  submitTransfer(): void {
    if (!this.canSubmitTransfer()) {
      this.toast.warning('الرجاء تعبئة بيانات التحويل بشكل صحيح');
      return;
    }

    if (this.transferData.amount > this.getFromBankBalance()) {
      this.toast.error('المبلغ المطلوب أكبر من الرصيد المتاح');
      return;
    }

    this.saving = true;
    this.bankService.transfer(this.transferData).subscribe({
      next: () => {
        this.toast.success('تم التحويل بنجاح');
        this.getAllBanks();
        this.closeDialogs();
        this.saving = false;
      },
      error: (err) => {
        this.toast.error(err.error?.message || 'حدث خطأ أثناء التحويل');
        this.saving = false;
      }
    });
  }

  trackByBank(index: number, bank: any): number {
    return bank.id;
  }
}
