import { Component, OnInit } from '@angular/core';
import { FormBuilder, FormGroup, FormArray, FormControl, Validators } from '@angular/forms';
import { MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { DailyEntryService } from '../services/daily-entry.service';
import { TreeAccountService } from '../services/tree-account.service';
import { DailyEntry } from '../interfaces/daily-entry.interface';
import { TreeAccount } from '../interfaces/tree-account.interface';
import Swal from 'sweetalert2';

interface AccountOption {
  id: number;
  label: string;
}

@Component({
  selector: 'app-daily-entries',
  templateUrl: './daily-entries.component.html',
  styleUrls: ['./daily-entries.component.css']
})
export class DailyEntriesComponent implements OnInit {
  entries: DailyEntry[] = [];
  accounts: TreeAccount[] = [];
  accountOptions: AccountOption[] = [];
  itemAccountCtrls: FormControl<string | AccountOption>[] = [];
  rowFilteredAccounts: AccountOption[][] = [];
  readonly accountAutocompleteCap = 400;

  loading = false;
  showForm = false;
  isEditMode = false;
  currentEntryId: number | null = null;
  currentPage = 1;
  perPage = 25;
  totalPages = 1;
  totalItems = 0;

  dateFrom: string = '';
  dateTo: string = '';
  searchTerm: string = '';

  entryForm: FormGroup;
  Math = Math;

  constructor(
    private dailyEntryService: DailyEntryService,
    private treeAccountService: TreeAccountService,
    private fb: FormBuilder
  ) {
    this.entryForm = this.fb.group({
      date: [new Date().toISOString().split('T')[0], Validators.required],
      description: [''],
      items: this.fb.array([], Validators.required)
    });
  }

  ngOnInit(): void {
    this.loadAccounts();
    this.loadEntries();
  }

  get itemsFormArray(): FormArray {
    return this.entryForm.get('items') as FormArray;
  }

  displayAccountOption = (value: string | AccountOption | null): string => {
    if (!value) return '';
    return typeof value === 'string' ? value : value.label;
  };

  loadAccounts(): void {
    this.treeAccountService.getAll().subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.accounts = Array.isArray(response.data) ? response.data : [];
          this.buildAccountOptions();
          if (this.showForm) {
            this.rebuildAccountCtrlsFromForm();
          }
        }
      },
      error: (error) => {
        console.error('Error loading accounts:', error);
      }
    });
  }

  private buildAccountOptions(): void {
    this.accountOptions = this.accounts
      .filter((a) => a.id != null && a.name)
      .map((a) => {
        const code = a.code != null ? String(a.code) : '';
        return {
          id: Number(a.id),
          label: code ? `${code} - ${a.name}` : String(a.name)
        };
      })
      .sort((a, b) => a.label.localeCompare(b.label, 'ar'));
  }

  private filterAccountOptions(term: string): AccountOption[] {
    const raw = String(term ?? '').trim();
    const q = raw.toLowerCase();
    let list = this.accountOptions;
    if (q) {
      list = list.filter((a) => {
        if (String(a.id).includes(raw)) return true;
        return a.label.toLowerCase().includes(q) || a.label.includes(raw);
      });
    }
    return list.slice(0, this.accountAutocompleteCap);
  }

  getRowFilteredAccounts(index: number): AccountOption[] {
    return this.rowFilteredAccounts[index] ?? this.filterAccountOptions('');
  }

  refreshRowFilter(index: number): void {
    const ctrl = this.itemAccountCtrls[index];
    if (!ctrl) return;
    const v = ctrl.value;
    const term = typeof v === 'string' ? v : (v?.label ?? '');
    this.rowFilteredAccounts[index] = this.filterAccountOptions(term);
  }

  private rebuildAccountCtrlsFromForm(): void {
    this.itemAccountCtrls = [];
    this.rowFilteredAccounts = [];
    this.itemsFormArray.controls.forEach((group, idx) => {
      const rawId = group.get('account_id')?.value;
      const accountId = rawId ? Number(rawId) : null;
      const opt = accountId ? this.accountOptions.find((a) => a.id === accountId) ?? null : null;
      const ctrl = new FormControl<string | AccountOption>(opt ?? '', { nonNullable: true });
      const rowIndex = idx;
      ctrl.valueChanges.subscribe(() => this.refreshRowFilter(rowIndex));
      this.itemAccountCtrls.push(ctrl);
      this.refreshRowFilter(idx);
    });
  }

  onAccountSelected(index: number, event: MatAutocompleteSelectedEvent): void {
    const acc = event.option.value as AccountOption;
    if (!acc?.id) return;
    this.itemsFormArray.at(index).patchValue({ account_id: acc.id });
    this.itemAccountCtrls[index].setValue(acc, { emitEvent: false });
    this.refreshRowFilter(index);
  }

  onAccountBlur(index: number): void {
    setTimeout(() => this.syncAccountOnBlur(index), 150);
  }

  private syncAccountOnBlur(index: number): void {
    const ctrl = this.itemAccountCtrls[index];
    if (!ctrl) return;
    const v = ctrl.value;
    if (v && typeof v === 'object') {
      this.itemsFormArray.at(index).patchValue({ account_id: v.id });
      return;
    }

    const str = typeof v === 'string' ? v.trim() : '';
    if (!str) {
      this.itemsFormArray.at(index).patchValue({ account_id: '' });
      return;
    }

    const exact = this.accountOptions.find((a) => a.label === str);
    if (exact) {
      this.itemsFormArray.at(index).patchValue({ account_id: exact.id });
      ctrl.setValue(exact, { emitEvent: false });
      return;
    }

    const partial = this.filterAccountOptions(str);
    if (partial.length === 1) {
      this.itemsFormArray.at(index).patchValue({ account_id: partial[0].id });
      ctrl.setValue(partial[0], { emitEvent: false });
    }
  }

  loadEntries(): void {
    this.loading = true;
    const params: any = {
      per_page: this.perPage,
      page: this.currentPage
    };

    if (this.dateFrom) params.date_from = this.dateFrom;
    if (this.dateTo) params.date_to = this.dateTo;
    if (this.searchTerm) params.search = this.searchTerm;

    this.dailyEntryService.getAll(params).subscribe({
      next: (response) => {
        this.entries = response.data || [];
        this.currentPage = response.current_page || 1;
        this.totalPages = response.last_page || 1;
        this.totalItems = response.total || 0;
        this.loading = false;
      },
      error: (error) => {
        console.error('Error loading entries:', error);
        this.loading = false;
        Swal.fire({
          title: 'خطأ',
          text: 'حدث خطأ أثناء تحميل القيود',
          icon: 'error',
          confirmButtonText: 'موافق'
        });
      }
    });
  }

  addItem(): void {
    const itemForm = this.fb.group({
      account_id: ['', Validators.required],
      debit: [0, [Validators.required, Validators.min(0)]],
      credit: [0, [Validators.required, Validators.min(0)]],
      notes: ['']
    });

    itemForm.addValidators((control) => {
      const formGroup = control as FormGroup;
      const debit = formGroup.get('debit')?.value || 0;
      const credit = formGroup.get('credit')?.value || 0;
      if (debit === 0 && credit === 0) {
        return { required: true };
      }
      return null;
    });

    this.itemsFormArray.push(itemForm);
    this.rebuildAccountCtrlsFromForm();
  }

  removeItem(index: number): void {
    this.itemsFormArray.removeAt(index);
    this.rebuildAccountCtrlsFromForm();
    this.calculateTotals();
  }

  calculateTotals(): { totalDebit: number, totalCredit: number, balance: number } {
    const items = this.itemsFormArray.value;
    const totalDebit = items.reduce((sum: number, item: any) => sum + (parseFloat(item.debit) || 0), 0);
    const totalCredit = items.reduce((sum: number, item: any) => sum + (parseFloat(item.credit) || 0), 0);
    const balance = totalDebit - totalCredit;
    return { totalDebit, totalCredit, balance };
  }

  isFormValid(): boolean {
    if (!this.entryForm.valid) return false;
    const totals = this.calculateTotals();
    return Math.abs(totals.balance) < 0.01 && this.itemsFormArray.length >= 2;
  }

  openAddForm(): void {
    this.isEditMode = false;
    this.currentEntryId = null;
    this.entryForm.reset({
      date: new Date().toISOString().split('T')[0],
      description: ''
    });
    this.itemsFormArray.clear();
    this.addItem();
    this.addItem();
    this.showForm = true;
  }

  openEditForm(entry: DailyEntry): void {
    this.isEditMode = true;
    this.currentEntryId = entry.id || null;
    this.entryForm.patchValue({
      date: entry.date,
      description: entry.description || ''
    });

    this.itemsFormArray.clear();
    if (entry.items && entry.items.length > 0) {
      entry.items.forEach(item => {
        const itemForm = this.fb.group({
          account_id: [item.account_id, Validators.required],
          debit: [item.debit || 0, [Validators.required, Validators.min(0)]],
          credit: [item.credit || 0, [Validators.required, Validators.min(0)]],
          notes: [item.notes || '']
        });
        this.itemsFormArray.push(itemForm);
      });
    } else {
      this.addItem();
      this.addItem();
    }
    this.rebuildAccountCtrlsFromForm();
    this.showForm = true;
  }

  closeForm(): void {
    this.showForm = false;
    this.isEditMode = false;
    this.currentEntryId = null;
    this.entryForm.reset();
    this.itemsFormArray.clear();
    this.itemAccountCtrls = [];
    this.rowFilteredAccounts = [];
  }

  onSubmit(): void {
    if (!this.isFormValid()) {
      Swal.fire({
        title: 'خطأ في التحقق',
        text: 'يرجى التأكد من أن مجموع المدين يساوي مجموع الدائن وأن هناك على الأقل بندين',
        icon: 'error',
        confirmButtonText: 'موافق'
      });
      return;
    }

    const formData = {
      date: this.entryForm.value.date,
      description: this.entryForm.value.description || '',
      items: this.itemsFormArray.value.map((item: any) => ({
        account_id: item.account_id,
        debit: parseFloat(item.debit) || 0,
        credit: parseFloat(item.credit) || 0,
        notes: item.notes || ''
      }))
    };

    this.loading = true;

    if (this.isEditMode && this.currentEntryId) {
      this.dailyEntryService.update(this.currentEntryId, formData).subscribe({
        next: (response) => {
          this.loading = false;
          Swal.fire({
            title: 'نجح',
            text: response.message || 'تم تحديث القيد بنجاح',
            icon: 'success',
            confirmButtonText: 'موافق'
          });
          this.closeForm();
          this.loadEntries();
        },
        error: (error) => {
          this.loading = false;
          const errorMessage = error.error?.message || 'حدث خطأ أثناء تحديث القيد';
          Swal.fire({
            title: 'خطأ',
            text: errorMessage,
            icon: 'error',
            confirmButtonText: 'موافق'
          });
        }
      });
    } else {
      this.dailyEntryService.create(formData).subscribe({
        next: (response) => {
          this.loading = false;
          Swal.fire({
            title: 'نجح',
            text: response.message || 'تم إنشاء القيد بنجاح',
            icon: 'success',
            confirmButtonText: 'موافق'
          });
          this.closeForm();
          this.loadEntries();
        },
        error: (error) => {
          this.loading = false;
          const errorMessage = error.error?.message || 'حدث خطأ أثناء إنشاء القيد';
          Swal.fire({
            title: 'خطأ',
            text: errorMessage,
            icon: 'error',
            confirmButtonText: 'موافق'
          });
        }
      });
    }
  }

  deleteEntry(entry: DailyEntry): void {
    Swal.fire({
      title: 'هل أنت متأكد؟',
      text: `هل تريد حذف القيد رقم ${entry.entry_number}؟`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'نعم، احذف',
      cancelButtonText: 'إلغاء',
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6'
    }).then((result) => {
      if (result.isConfirmed && entry.id) {
        this.loading = true;
        this.dailyEntryService.delete(entry.id).subscribe({
          next: (response) => {
            this.loading = false;
            Swal.fire({
              title: 'تم الحذف',
              text: response.message || 'تم حذف القيد بنجاح',
              icon: 'success',
              confirmButtonText: 'موافق'
            });
            this.loadEntries();
          },
          error: (error) => {
            this.loading = false;
            const errorMessage = error.error?.message || 'حدث خطأ أثناء حذف القيد';
            Swal.fire({
              title: 'خطأ',
              text: errorMessage,
              icon: 'error',
              confirmButtonText: 'موافق'
            });
          }
        });
      }
    });
  }

  viewEntry(entry: DailyEntry): void {
    let itemsHtml = '<table class="table table-bordered"><thead><tr><th>الحساب</th><th>مدين</th><th>دائن</th><th>ملاحظات</th></tr></thead><tbody>';
    if (entry.items) {
      entry.items.forEach(item => {
        itemsHtml += `<tr>
          <td>${item.account?.name || ''}</td>
          <td>${item.debit || 0}</td>
          <td>${item.credit || 0}</td>
          <td>${item.notes || ''}</td>
        </tr>`;
      });
    }
    itemsHtml += '</tbody></table>';

    Swal.fire({
      title: `القيد رقم ${entry.entry_number}`,
      html: `
        <div class="text-right">
          <p><strong>التاريخ:</strong> ${entry.date}</p>
          <p><strong>الوصف:</strong> ${entry.description || 'لا يوجد'}</p>
          <p><strong>المستخدم:</strong> ${entry.user?.name || 'غير محدد'}</p>
          <hr>
          <h5>بنود القيد:</h5>
          ${itemsHtml}
        </div>
      `,
      width: '800px',
      confirmButtonText: 'موافق'
    });
  }

  applyFilters(): void {
    this.currentPage = 1;
    this.loadEntries();
  }

  clearFilters(): void {
    this.dateFrom = '';
    this.dateTo = '';
    this.searchTerm = '';
    this.currentPage = 1;
    this.loadEntries();
  }

  goToPage(page: number): void {
    if (page >= 1 && page <= this.totalPages) {
      this.currentPage = page;
      this.loadEntries();
    }
  }
}
