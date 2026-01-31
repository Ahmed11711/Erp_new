import { Component, OnInit } from '@angular/core';
import { SafeService } from '../services/safe.service';
import { BankService } from '../services/bank.service';
import { TreeAccountService } from '../services/tree-account.service';

@Component({
  selector: 'app-safes',
  templateUrl: './safes.component.html',
  styleUrls: ['./safes.component.css']
})
export class SafesComponent implements OnInit {
  safes: any[] = [];
  banks: any[] = [];
  treeAccounts: any[] = [];
  loading = false;
  showAddDialog = false;
  showEditDialog = false;
  showTransferDialog = false;

  newSafe: any = {
    name: '',
    type: 'main',
    balance: 0,
    is_inside_branch: false,
    branch_name: '',
    account_id: null
  };

  selectedSafe: any = null;

  transferData: any = {
    type: 'safe_to_safe', // 'safe_to_safe' | 'safe_to_bank'
    from_id: null,
    to_id: null,
    amount: 0,
    date: new Date().toISOString().split('T')[0],
    notes: ''
  };

  constructor(
    private safeService: SafeService,
    private bankService: BankService,
    private treeAccountService: TreeAccountService
  ) { }

  ngOnInit(): void {
    this.getAllSafes();
    this.getAllBanks();
    this.getTreeAccounts();
  }

  getAllSafes() {
    this.loading = true;
    this.safeService.getAll().subscribe({
      next: (res) => {
        this.safes = res.data || (Array.isArray(res) ? res : []);
        this.loading = false;
      },
      error: (err) => {
        console.error(err);
        this.loading = false;
      }
    });
  }

  getAllBanks() {
    this.bankService.getAll().subscribe({
      next: (res) => {
        this.banks = res.data || (Array.isArray(res) ? res : []);
      }
    });
  }

  getTreeAccounts() {
    this.treeAccountService.getAll().subscribe({
      next: (res) => {
        if (res.data) {
          this.treeAccounts = Array.isArray(res.data) ? res.data : [res.data];
        } else if (Array.isArray(res)) {
          this.treeAccounts = res;
        }
      }
    });
  }

  openAddDialog() {
    this.newSafe = {
      name: '',
      type: 'main',
      balance: 0,
      is_inside_branch: false,
      branch_name: '',
      account_id: null
    };
    this.showAddDialog = true;
  }

  openEditDialog(safe: any) {
    this.selectedSafe = { ...safe };
    this.showEditDialog = true;
  }

  openTransferDialog(fromSafe?: any) {
    this.transferData = {
      type: 'safe_to_safe',
      from_id: fromSafe ? fromSafe.id : null,
      to_id: null,
      amount: 0,
      date: new Date().toISOString().split('T')[0],
      notes: ''
    };
    this.showTransferDialog = true;
  }

  closeDialogs() {
    this.showAddDialog = false;
    this.showEditDialog = false;
    this.showTransferDialog = false;
    this.selectedSafe = null;
  }

  saveSafe() {
    if (!this.newSafe.name) {
      alert('الرجاء تعبئة الاسم');
      return;
    }

    this.loading = true;
    this.safeService.create(this.newSafe).subscribe({
      next: (res) => {
        this.getAllSafes();
        this.closeDialogs();
        this.loading = false;
      },
      error: (err) => {
        alert(err.error?.message || 'Error');
        this.loading = false;
      }
    });
  }

  updateSafe() {
    if (!this.selectedSafe.name) {
      alert('الرجاء تعبئة الاسم');
      return;
    }

    this.loading = true;
    this.safeService.update(this.selectedSafe.id, this.selectedSafe).subscribe({
      next: (res) => {
        this.getAllSafes();
        this.closeDialogs();
        this.loading = false;
      },
      error: (err) => {
        alert(err.error?.message || 'Error');
        this.loading = false;
      }
    });
  }

  deleteSafe(id: number) {
    if (confirm('هل انت متأكد من الحذف؟')) {
      this.loading = true;
      this.safeService.delete(id).subscribe({
        next: () => {
          this.getAllSafes();
          this.loading = false;
        },
        error: (err) => {
          alert(err.error?.message || 'Error');
          this.loading = false;
        }
      });
    }
  }

  submitTransfer() {
    if (!this.transferData.from_id || !this.transferData.to_id || this.transferData.amount <= 0) {
      alert('الرجاء تعبئة بيانات التحويل بشكل صحيح');
      return;
    }

    this.loading = true;

    if (this.transferData.type === 'safe_to_safe') {
      const payload = {
        from_safe_id: this.transferData.from_id,
        to_safe_id: this.transferData.to_id,
        amount: this.transferData.amount,
        notes: this.transferData.notes
      };
      this.safeService.transfer(payload).subscribe({
        next: (res) => {
          alert('تم التحويل بنجاح');
          this.getAllSafes();
          this.closeDialogs();
          this.loading = false;
        },
        error: (err) => {
          alert(err.error?.message || 'حدث خطأ');
          this.loading = false;
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
      // Use BankService for cross transfers as implemented in BankController
      this.bankService.transfer(payload).subscribe({
        next: (res) => {
          alert('تم التحويل للبنك بنجاح');
          this.getAllSafes();
          this.closeDialogs();
          this.loading = false;
        },
        error: (err) => {
          alert(err.error?.message || 'حدث خطأ');
          this.loading = false;
        }
      });
    }
  }
}
