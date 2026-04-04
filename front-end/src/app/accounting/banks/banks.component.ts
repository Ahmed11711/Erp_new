import { Component, OnInit } from '@angular/core';
import { BankService } from '../services/bank.service';
import { TreeAccountService } from '../services/tree-account.service';
import { MatDialog } from '@angular/material/dialog';
import { ToastService } from '../../shared/toast/toast.service';
import { ConfirmDialogComponent } from '../../shared/confirm-dialog/confirm-dialog.component';

@Component({
  selector: 'app-banks',
  templateUrl: './banks.component.html',
  styleUrls: ['./banks.component.css']
})
export class BanksComponent implements OnInit {
  banks: any[] = [];
  filteredBanks: any[] = [];
  treeAccounts: any[] = [];
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
  }

  private getEmptyBank() {
    return { name: '', type: 'main', balance: 0, usage: '', asset_id: null };
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
        this.banks = res.data || (Array.isArray(res) ? res : []);
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
        if (res.data) {
          this.treeAccounts = Array.isArray(res.data) ? res.data : [res.data];
        } else if (Array.isArray(res)) {
          this.treeAccounts = res;
        }
      },
      error: () => this.toast.error('تعذر تحميل شجرة الحسابات')
    });
  }

  openAddDialog(): void {
    this.newBank = this.getEmptyBank();
    this.showAddDialog = true;
  }

  openEditDialog(bank: any): void {
    this.selectedBank = { ...bank, asset_id: bank.asset_id || bank.asset?.id };
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
    return !!this.newBank.name?.trim() && !!this.newBank.asset_id;
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
    return !!this.selectedBank?.name?.trim() && !!this.selectedBank?.asset_id;
  }

  updateBank(): void {
    if (!this.canSaveEdit()) {
      this.toast.warning('الرجاء تعبئة الحقول المطلوبة');
      return;
    }

    this.saving = true;
    this.bankService.update(this.selectedBank.id, this.selectedBank).subscribe({
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
