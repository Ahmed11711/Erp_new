import { Component, OnInit } from '@angular/core';
import { BankService } from '../services/bank.service';
import { TreeAccountService } from '../services/tree-account.service';

@Component({
  selector: 'app-banks',
  templateUrl: './banks.component.html',
  styleUrls: ['./banks.component.css']
})
export class BanksComponent implements OnInit {
  banks: any[] = [];
  treeAccounts: any[] = [];
  loading = false;
  showAddDialog = false;
  showEditDialog = false;
  showTransferDialog = false;

  newBank: any = {
    name: '',
    type: 'main',
    balance: 0,
    usage: '',
    asset_id: null
  };

  selectedBank: any = null;

  transferData: any = {
    type: 'transfer_bank_to_bank',
    from_id: null,
    to_id: null,
    amount: 0,
    date: new Date().toISOString().split('T')[0],
    notes: ''
  };

  constructor(
    private bankService: BankService,
    private treeAccountService: TreeAccountService
  ) { }

  ngOnInit(): void {
    this.getAllBanks();
    this.getTreeAccounts();
  }

  getAllBanks() {
    this.loading = true;
    this.bankService.getAll().subscribe({
      next: (res) => {
        this.banks = res.data || res; // Handle pagination or raw array
        if (res.data) this.banks = res.data;
        this.loading = false;
      },
      error: (err) => {
        console.error(err);
        this.loading = false;
      }
    });
  }

  getTreeAccounts() {
    this.treeAccountService.getAll().subscribe({
      next: (res) => {
        // We probably want only leaf accounts or specific type. 
        // For now get all and let user choose.
        // Assuming response has data property which is array
        if (res.data) {
          this.treeAccounts = Array.isArray(res.data) ? res.data : [res.data];
        } else if (Array.isArray(res)) {
          this.treeAccounts = res;
        }
      }
    });
  }

  openAddDialog() {
    this.newBank = { name: '', type: 'main', balance: 0, usage: '', asset_id: null };
    this.showAddDialog = true;
  }

  openEditDialog(bank: any) {
    this.selectedBank = { ...bank };
    this.showEditDialog = true;
  }

  openTransferDialog(fromBank?: any) {
    this.transferData = {
      type: 'transfer_bank_to_bank',
      from_id: fromBank ? fromBank.id : null,
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
    this.selectedBank = null;
  }

  saveBank() {
    if (!this.newBank.name || !this.newBank.asset_id) {
      alert('الرجاء تعبئة الحقول المطلوبة (الاسم، الحساب المرتبط)');
      return;
    }

    this.loading = true;
    this.bankService.create(this.newBank).subscribe({
      next: (res) => {
        this.getAllBanks();
        this.closeDialogs();
        this.loading = false;
      },
      error: (err) => {
        alert(err.error?.message || 'Error');
        this.loading = false;
      }
    });
  }

  updateBank() {
    if (!this.selectedBank.name || !this.selectedBank.asset_id) {
      alert('الرجاء تعبئة الحقول المطلوبة');
      return;
    }

    this.loading = true;
    this.bankService.update(this.selectedBank.id, this.selectedBank).subscribe({
      next: (res) => {
        this.getAllBanks();
        this.closeDialogs();
        this.loading = false;
      },
      error: (err) => {
        alert(err.error?.message || 'Error');
        this.loading = false;
      }
    });
  }

  deleteBank(id: number) {
    if (confirm('هل انت متأكد من الحذف؟')) {
      this.loading = true;
      this.bankService.delete(id).subscribe({
        next: () => {
          this.getAllBanks();
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
    if (this.transferData.from_id == this.transferData.to_id && this.transferData.type == 'transfer_bank_to_bank') {
      alert('لا يمكن التحويل لنفس البنك');
      return;
    }

    this.loading = true;
    this.bankService.transfer(this.transferData).subscribe({
      next: (res) => {
        alert('تم التحويل بنجاح');
        this.getAllBanks();
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
