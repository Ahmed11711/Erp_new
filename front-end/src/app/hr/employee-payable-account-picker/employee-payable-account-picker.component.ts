import { Component, EventEmitter, HostListener, Input, OnInit, Output } from '@angular/core';
import { TreeAccountService } from 'src/app/accounting/services/tree-account.service';

export interface TreeAccountOption {
  id: number;
  label: string;
}

@Component({
  selector: 'app-employee-payable-account-picker',
  templateUrl: './employee-payable-account-picker.component.html',
  styleUrls: ['./employee-payable-account-picker.component.css'],
})
export class EmployeePayableAccountPickerComponent implements OnInit {
  @Input() accountId: number | null = null;
  /** داخل dialog: القائمة تتوسع للأسفل بدلاً من absolute لتجنب القص */
  @Input() expandInline = false;
  @Output() accountIdChange = new EventEmitter<number | null>();

  treeAccounts: TreeAccountOption[] = [];
  treeAccountSearch = '';
  treePickerOpen = false;
  loading = true;
  loadError = '';

  constructor(private treeAccountService: TreeAccountService) {}

  ngOnInit(): void {
    this.treeAccountService.getAll().subscribe({
      next: (res) => {
        this.loading = false;
        const list = res?.success && Array.isArray(res.data) ? res.data : [];
        this.treeAccounts = list
          .filter((a) => a.id != null && a.name)
          .map((a) => {
            const code = a.code != null ? String(a.code) : '';
            return {
              id: Number(a.id),
              label: code ? `${code} - ${a.name}` : String(a.name),
            };
          })
          .sort((a, b) => a.label.localeCompare(b.label, 'ar'));
      },
      error: () => {
        this.loading = false;
        this.loadError = 'تعذر تحميل شجرة الحسابات';
      },
    });
  }

  get filteredTreeAccounts(): TreeAccountOption[] {
    const raw = String(this.treeAccountSearch ?? '').trim();
    if (!raw) {
      return this.treeAccounts;
    }
    const q = raw.toLowerCase();
    return this.treeAccounts.filter((item) => item.label.toLowerCase().includes(q));
  }

  get selectedLabel(): string {
    if (!this.accountId) {
      return '';
    }
    return this.treeAccounts.find((a) => a.id === this.accountId)?.label ?? `#${this.accountId}`;
  }

  toggleTreePicker(): void {
    this.treePickerOpen = !this.treePickerOpen;
    if (!this.treePickerOpen) {
      this.treeAccountSearch = '';
      return;
    }
    setTimeout(() => {
      const input = document.querySelector('.tree-picker--open .tree-picker__search-input') as HTMLInputElement | null;
      input?.focus();
    }, 0);
  }

  selectTreeAccount(item: TreeAccountOption): void {
    this.accountId = item.id;
    this.accountIdChange.emit(item.id);
    this.treePickerOpen = false;
    this.treeAccountSearch = '';
  }

  clearSelection(): void {
    this.accountId = null;
    this.accountIdChange.emit(null);
    this.treePickerOpen = false;
    this.treeAccountSearch = '';
  }

  @HostListener('document:click')
  onDocumentClick(): void {
    if (this.treePickerOpen) {
      this.treePickerOpen = false;
      this.treeAccountSearch = '';
    }
  }
}
