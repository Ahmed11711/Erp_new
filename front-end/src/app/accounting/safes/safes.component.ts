import { Component, OnInit } from '@angular/core';
import { SafeService } from '../services/safe.service';
import { BankService } from '../services/bank.service';
import { TreeAccountService } from '../services/tree-account.service';
import { MatDialog } from '@angular/material/dialog';
import { ToastService } from '../../shared/toast/toast.service';
import { ConfirmDialogComponent } from '../../shared/confirm-dialog/confirm-dialog.component';

@Component({
  selector: 'app-safes',
  templateUrl: './safes.component.html',
  styleUrls: ['./safes.component.css']
})
export class SafesComponent implements OnInit {
  safes: any[] = [];
  filteredSafes: any[] = [];
  banks: any[] = [];
  treeAccounts: any[] = [];
  loading = false;
  saving = false;
  searchTerm = '';

  showAddDialog = false;
  showEditDialog = false;
  showTransferDialog = false;

  newSafe: any = this.getEmptySafe();
  selectedSafe: any = null;
  transferData: any = this.getEmptyTransfer();

  get totalBalance(): number {
    return this.safes.reduce((sum, s) => sum + (Number(s.balance) || 0), 0);
  }

  get mainSafesCount(): number {
    return this.safes.filter(s => s.type === 'main').length;
  }

  get branchSafesCount(): number {
    return this.safes.filter(s => s.type !== 'main').length;
  }

  constructor(
    private safeService: SafeService,
    private bankService: BankService,
    private treeAccountService: TreeAccountService,
    private dialog: MatDialog,
    private toast: ToastService
  ) {}

  ngOnInit(): void {
    this.getAllSafes();
    this.getAllBanks();
    this.getTreeAccounts();
  }

  private getEmptySafe() {
    return {
      name: '',
      type: 'main',
      balance: 0,
      is_inside_branch: false,
      branch_name: '',
      account_id: null,
      counter_account_id: null
    };
  }

  private getEmptyTransfer() {
    return {
      type: 'safe_to_safe',
      from_id: null,
      to_id: null,
      amount: 0,
      date: new Date().toISOString().split('T')[0],
      notes: ''
    };
  }

  onSearch(): void {
    if (!this.searchTerm.trim()) {
      this.filteredSafes = [...this.safes];
      return;
    }
    const term = this.searchTerm.trim().toLowerCase();
    this.filteredSafes = this.safes.filter(s =>
      s.name?.toLowerCase().includes(term) ||
      s.branch_name?.toLowerCase().includes(term) ||
      s.account?.name?.toLowerCase().includes(term)
    );
  }

  clearSearch(): void {
    this.searchTerm = '';
    this.filteredSafes = [...this.safes];
  }

  getAllSafes(): void {
    this.loading = true;
    this.safeService.getAll().subscribe({
      next: (res) => {
        this.safes = res.data || (Array.isArray(res) ? res : []);
        this.filteredSafes = [...this.safes];
        this.loading = false;
      },
      error: (err) => {
        this.toast.error('حدث خطأ أثناء تحميل الخزن');
        this.loading = false;
      }
    });
  }

  getAllBanks(): void {
    this.bankService.getAll().subscribe({
      next: (res) => {
        this.banks = res.data || (Array.isArray(res) ? res : []);
      },
      error: () => this.toast.error('تعذر تحميل بيانات البنوك')
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
    this.newSafe = this.getEmptySafe();
    this.showAddDialog = true;
  }

  openEditDialog(safe: any): void {
    this.selectedSafe = { ...safe, account_id: safe.account_id || safe.account?.id };
    this.showEditDialog = true;
  }

  openTransferDialog(fromSafe?: any): void {
    this.transferData = this.getEmptyTransfer();
    if (fromSafe) {
      this.transferData.from_id = fromSafe.id;
    }
    this.showTransferDialog = true;
  }

  closeDialogs(): void {
    this.showAddDialog = false;
    this.showEditDialog = false;
    this.showTransferDialog = false;
    this.selectedSafe = null;
  }

  canSaveNewSafe(): boolean {
    if (!this.newSafe.name?.trim()) return false;
    if (!this.newSafe.account_id) return false;
    const bal = Number(this.newSafe.balance) || 0;
    if (bal > 0 && !this.newSafe.counter_account_id) return false;
    return true;
  }

  saveSafe(): void {
    if (!this.canSaveNewSafe()) {
      this.toast.warning('الرجاء تعبئة جميع الحقول المطلوبة');
      return;
    }

    this.saving = true;
    this.safeService.create(this.newSafe).subscribe({
      next: () => {
        this.toast.success('تم إضافة الخزنة بنجاح');
        this.getAllSafes();
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
    return !!this.selectedSafe?.name?.trim();
  }

  updateSafe(): void {
    if (!this.canSaveEdit()) {
      this.toast.warning('الرجاء تعبئة اسم الخزنة');
      return;
    }

    this.saving = true;
    this.safeService.update(this.selectedSafe.id, this.selectedSafe).subscribe({
      next: () => {
        this.toast.success('تم تحديث بيانات الخزنة بنجاح');
        this.getAllSafes();
        this.closeDialogs();
        this.saving = false;
      },
      error: (err) => {
        this.toast.error(err.error?.message || 'حدث خطأ أثناء التحديث');
        this.saving = false;
      }
    });
  }

  deleteSafe(safe: any): void {
    this.dialog.open(ConfirmDialogComponent, {
      data: {
        title: 'حذف الخزنة',
        message: `هل أنت متأكد من حذف خزنة "${safe.name}"؟ لا يمكن التراجع عن هذا الإجراء.`,
        confirmText: 'حذف',
        cancelText: 'إلغاء',
        type: 'danger'
      },
      width: '420px',
      direction: 'rtl'
    }).afterClosed().subscribe(confirmed => {
      if (!confirmed) return;
      this.loading = true;
      this.safeService.delete(safe.id).subscribe({
        next: () => {
          this.toast.success('تم حذف الخزنة بنجاح');
          this.getAllSafes();
        },
        error: (err) => {
          this.toast.error(err.error?.message || 'حدث خطأ أثناء الحذف');
          this.loading = false;
        }
      });
    });
  }

  canSubmitTransfer(): boolean {
    return !!this.transferData.from_id &&
           !!this.transferData.to_id &&
           this.transferData.amount > 0 &&
           this.transferData.from_id !== this.transferData.to_id;
  }

  getFromSafeBalance(): number {
    const safe = this.safes.find(s => s.id == this.transferData.from_id);
    return safe ? Number(safe.balance) || 0 : 0;
  }

  submitTransfer(): void {
    if (!this.canSubmitTransfer()) {
      this.toast.warning('الرجاء تعبئة بيانات التحويل بشكل صحيح');
      return;
    }

    if (this.transferData.amount > this.getFromSafeBalance()) {
      this.toast.error('المبلغ المطلوب أكبر من رصيد الخزنة المحولة منها');
      return;
    }

    this.saving = true;

    if (this.transferData.type === 'safe_to_safe') {
      const payload = {
        from_safe_id: this.transferData.from_id,
        to_safe_id: this.transferData.to_id,
        amount: this.transferData.amount,
        notes: this.transferData.notes
      };
      this.safeService.transfer(payload).subscribe({
        next: () => {
          this.toast.success('تم التحويل بنجاح');
          this.getAllSafes();
          this.closeDialogs();
          this.saving = false;
        },
        error: (err) => {
          this.toast.error(err.error?.message || 'حدث خطأ أثناء التحويل');
          this.saving = false;
        }
      });
    } else if (this.transferData.type === 'safe_to_bank') {
      const payload = {
        type: 'transfer_safe_to_bank',
        from_id: this.transferData.from_id,
        to_id: this.transferData.to_id,
        amount: this.transferData.amount,
        date: this.transferData.date,
        notes: this.transferData.notes
      };
      this.bankService.transfer(payload).subscribe({
        next: () => {
          this.toast.success('تم التحويل للبنك بنجاح');
          this.getAllSafes();
          this.closeDialogs();
          this.saving = false;
        },
        error: (err) => {
          this.toast.error(err.error?.message || 'حدث خطأ أثناء التحويل');
          this.saving = false;
        }
      });
    }
  }

  trackBySafe(index: number, safe: any): number {
    return safe.id;
  }
}
