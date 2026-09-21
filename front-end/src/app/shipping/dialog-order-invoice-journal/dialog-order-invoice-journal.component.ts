import { Component, Inject, OnInit } from '@angular/core';
import { FormArray, FormControl, FormGroup, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatAutocompleteSelectedEvent } from '@angular/material/autocomplete';
import { firstValueFrom } from 'rxjs';
import Swal from 'sweetalert2';
import { TreeAccountService } from 'src/app/accounting/services/tree-account.service';
import { OrderService } from '../services/order.service';

type AccountOption = { id: number; label: string };

export interface InvoiceJournalLineDraft {
  account_id: number;
  account_code?: string | null;
  account_name?: string | null;
  debit: number;
  credit: number;
  description: string;
}

interface JournalDraft {
  key: string;
  title: string;
  can_post: boolean;
  posted?: boolean;
  applicable?: boolean;
  reason?: string | null;
  date?: string | null;
  description?: string;
  lines: InvoiceJournalLineDraft[];
  sync_operational_default?: boolean;
  cash_source?: string | null;
  cash_source_already_recorded?: boolean;
}

@Component({
  selector: 'app-dialog-order-invoice-journal',
  templateUrl: './dialog-order-invoice-journal.component.html',
  styleUrls: ['./dialog-order-invoice-journal.component.css'],
})
export class DialogOrderInvoiceJournalComponent implements OnInit {
  loading = true;
  submitting = false;
  loadError = '';
  canPost = false;
  reason = '';
  drafts: JournalDraft[] = [];
  accountOptions: AccountOption[] = [];
  itemAccountCtrls: Array<Array<FormControl<string | AccountOption>>> = [];
  rowFilteredAccounts: AccountOption[][][] = [];
  readonly accountCap = 30;

  form = new FormGroup({
    journals: new FormArray<FormGroup>([]),
  });

  constructor(
    public dialogRef: MatDialogRef<DialogOrderInvoiceJournalComponent>,
    @Inject(MAT_DIALOG_DATA) public data: { orderId: number; customerName?: string; netTotal?: number },
    private orderService: OrderService,
    private treeAccountService: TreeAccountService,
  ) {}

  get journals(): FormArray<FormGroup> {
    return this.form.controls.journals;
  }

  journalLines(index: number): FormArray<FormGroup> {
    return this.journals.at(index).get('lines') as FormArray<FormGroup>;
  }

  journalBadge(index: number): string {
    const draft = this.drafts[index];
    if (this.journals.at(index)?.get('can_post')?.value) {
      return 'مسودة للترحيل';
    }
    if (draft?.posted) {
      return 'مرحّل';
    }
    if (draft?.applicable === false) {
      return 'غير مطلوب';
    }
    return 'غير قابل للترحيل';
  }

  showJournalReason(index: number): boolean {
    const draft = this.drafts[index];
    return !!draft?.reason && !draft?.posted;
  }

  showsSyncOperational(index: number): boolean {
    return String(this.drafts[index]?.key || this.journals.at(index)?.get('key')?.value || '') === 'collection';
  }

  private defaultSyncOperational(draft: JournalDraft): boolean {
    if (draft.key !== 'collection') {
      return false;
    }
    if (typeof draft.sync_operational_default === 'boolean') {
      return draft.sync_operational_default;
    }
    return !draft.cash_source_already_recorded;
  }

  journalTotals(index: number): { debit: number; credit: number; balance: number } {
    let debit = 0;
    let credit = 0;
    for (const group of this.journalLines(index).controls) {
      debit += Number(group.get('debit')?.value || 0);
      credit += Number(group.get('credit')?.value || 0);
    }
    debit = Math.round(debit * 100) / 100;
    credit = Math.round(credit * 100) / 100;
    return { debit, credit, balance: Math.round((debit - credit) * 100) / 100 };
  }

  isJournalBalanced(index: number): boolean {
    const totals = this.journalTotals(index);
    return Math.abs(totals.balance) < 0.01 && totals.debit > 0;
  }

  get allEditableBalanced(): boolean {
    return this.journals.controls.every((group, index) => {
      if (!group.get('can_post')?.value) {
        return true;
      }
      return this.isJournalBalanced(index);
    });
  }

  displayAccountOption = (value: string | AccountOption | null): string => {
    if (!value) {
      return '';
    }
    return typeof value === 'string' ? value : value.label;
  };

  ngOnInit(): void {
    void this.bootstrap();
  }

  addLine(journalIndex: number, line?: Partial<InvoiceJournalLineDraft>): void {
    const group = new FormGroup({
      account_id: new FormControl<number | null>(line?.account_id ? Number(line.account_id) : null, Validators.required),
      debit: new FormControl<number>(Number(line?.debit || 0), { nonNullable: true }),
      credit: new FormControl<number>(Number(line?.credit || 0), { nonNullable: true }),
      description: new FormControl<string>(line?.description || '', { nonNullable: true }),
    });
    this.journalLines(journalIndex).push(group);
    this.attachAccountCtrl(journalIndex, this.journalLines(journalIndex).length - 1, line);
  }

  removeLine(journalIndex: number, lineIndex: number): void {
    if (this.journalLines(journalIndex).length <= 2) {
      return;
    }
    this.journalLines(journalIndex).removeAt(lineIndex);
    this.itemAccountCtrls[journalIndex].splice(lineIndex, 1);
    this.rowFilteredAccounts[journalIndex].splice(lineIndex, 1);
  }

  resetJournal(journalIndex: number): void {
    const draft = this.drafts[journalIndex];
    if (!draft) {
      return;
    }
    this.journalLines(journalIndex).clear();
    this.itemAccountCtrls[journalIndex] = [];
    this.rowFilteredAccounts[journalIndex] = [];
    this.journals.at(journalIndex).patchValue({
      date: draft.date || '',
      description: draft.description || draft.title,
      sync_operational: this.defaultSyncOperational(draft),
    });
    for (const line of draft.lines) {
      this.addLine(journalIndex, line);
    }
  }

  refreshRowFilter(journalIndex: number, lineIndex: number): void {
    const ctrl = this.itemAccountCtrls[journalIndex]?.[lineIndex];
    if (!ctrl) {
      return;
    }
    const value = ctrl.value;
    const term = typeof value === 'string' ? value : (value?.label ?? '');
    this.rowFilteredAccounts[journalIndex][lineIndex] = this.filterAccounts(term);
  }

  onAccountSelected(journalIndex: number, lineIndex: number, event: MatAutocompleteSelectedEvent): void {
    const acc = event.option.value as AccountOption;
    if (!acc?.id) {
      return;
    }
    this.journalLines(journalIndex).at(lineIndex).patchValue({ account_id: acc.id });
    this.itemAccountCtrls[journalIndex][lineIndex].setValue(acc, { emitEvent: false });
    this.refreshRowFilter(journalIndex, lineIndex);
  }

  onAccountBlur(journalIndex: number, lineIndex: number): void {
    setTimeout(() => {
      const ctrl = this.itemAccountCtrls[journalIndex]?.[lineIndex];
      if (!ctrl) {
        return;
      }
      const value = ctrl.value;
      if (value && typeof value !== 'string' && value.id) {
        this.journalLines(journalIndex).at(lineIndex).patchValue({ account_id: value.id });
        return;
      }
      const term = String(value || '').trim();
      const match = this.accountOptions.find((a) => a.label === term || String(a.id) === term);
      if (match) {
        this.journalLines(journalIndex).at(lineIndex).patchValue({ account_id: match.id });
        ctrl.setValue(match, { emitEvent: false });
        return;
      }
      this.journalLines(journalIndex).at(lineIndex).patchValue({ account_id: null });
    }, 150);
  }

  async submit(): Promise<void> {
    if (!this.canPost || this.submitting || !this.allEditableBalanced || this.form.invalid) {
      return;
    }
    const allowedKeys = ['invoice', 'cogs', 'prepaid', 'delivery', 'collection', 'shipping_expense'] as const;
    type JournalPayload = {
      key: typeof allowedKeys[number];
      date: string;
      description: string;
      sync_operational?: boolean;
      lines: Array<{ account_id: number; debit: number; credit: number; description: string }>;
    };
    const journals: JournalPayload[] = [];
    this.journals.controls.forEach((group, index) => {
      if (!group.get('can_post')?.value) {
        return;
      }
      const rawKey = String(group.get('key')?.value || '');
      const key = allowedKeys.find((item) => item === rawKey);
      if (!key) {
        return;
      }
      const payload: JournalPayload = {
        key,
        date: String(group.get('date')?.value || ''),
        description: String(group.get('description')?.value || '').trim(),
        lines: this.journalLines(index).controls.map((line) => ({
          account_id: Number(line.get('account_id')?.value),
          debit: Number(line.get('debit')?.value || 0),
          credit: Number(line.get('credit')?.value || 0),
          description: String(line.get('description')?.value || '').trim(),
        })),
      };
      if (key === 'collection') {
        payload.sync_operational = !!group.get('sync_operational')?.value;
      }
      if (payload.lines.length >= 2) {
        journals.push(payload);
      }
    });

    if (journals.some((journal) => journal.lines.some((line) => !line.account_id))) {
      await Swal.fire({ icon: 'warning', title: 'اختر حساباً لكل بند', timer: 1800, showConfirmButton: false });
      return;
    }
    if (journals.length === 0) {
      await Swal.fire({ icon: 'info', title: 'لا توجد قيود قابلة للترحيل', timer: 1800, showConfirmButton: false });
      return;
    }

    this.submitting = true;
    try {
      const result: any = await firstValueFrom(
        this.orderService.postOrderMissingInvoice(this.data.orderId, { journals })
      );
      await Swal.fire({
        icon: 'success',
        title: result?.message || 'تم ترحيل القيود',
        timer: 1800,
        showConfirmButton: false,
      });
      this.dialogRef.close(true);
    } catch (err: any) {
      await Swal.fire({
        icon: 'error',
        title: 'لم يكتمل الترحيل',
        text: err?.error?.message || err?.message || 'تعذر ترحيل القيود',
      });
    } finally {
      this.submitting = false;
    }
  }

  close(): void {
    this.dialogRef.close(false);
  }

  private async bootstrap(): Promise<void> {
    this.loading = true;
    this.loadError = '';
    try {
      const preview = await firstValueFrom(
        this.orderService.previewOrderMissingInvoice(this.data.orderId)
      );
      this.drafts = this.normalizeDrafts(preview);
      this.canPost = this.drafts.some((draft) => draft.can_post);
      this.reason = preview?.reason || '';
      this.accountOptions = this.drafts
        .flatMap((draft) => draft.lines)
        .filter((line) => line.account_id)
        .map((line) => ({
          id: Number(line.account_id),
          label: this.accountLabel(line.account_code, line.account_name),
        }));
      this.buildJournalForms();
      this.loading = false;
      void this.loadAccountPicker();
    } catch (err: any) {
      this.loadError = err?.error?.reason || err?.error?.message || 'تعذر تحميل مسودة القيود.';
      this.canPost = false;
      this.loading = false;
    }
  }

  private normalizeDrafts(preview: any): JournalDraft[] {
    const drafts: JournalDraft[] = Array.isArray(preview?.journals) && preview.journals.length
      ? preview.journals.map((journal: any) => ({
          key: String(journal.key || ''),
          title: String(journal.title || journal.key || 'قيد'),
          can_post: !!journal.can_post,
          posted: !!journal.posted,
          applicable: journal.applicable !== false,
          reason: journal.reason || null,
          date: journal.date || preview.date || '',
          description: journal.description || journal.title || '',
          lines: Array.isArray(journal.lines) ? journal.lines : [],
          sync_operational_default: !!journal.sync_operational_default,
          cash_source: journal.cash_source || null,
          cash_source_already_recorded: !!journal.cash_source_already_recorded,
        }))
      : [{
          key: 'invoice',
          title: 'إثبات المبيعات — تاريخ الشحن',
          can_post: !!preview?.can_post,
          posted: false,
          applicable: true,
          reason: preview?.reason || null,
          date: preview?.date || '',
          description: preview?.description || `فاتورة مبيعات — طلب رقم ${this.data.orderId}`,
          lines: Array.isArray(preview?.lines) ? preview.lines : [],
        }];

    return drafts.filter((draft) => draft.can_post || draft.posted || draft.applicable !== false);
  }

  private buildJournalForms(): void {
    this.journals.clear();
    this.itemAccountCtrls = [];
    this.rowFilteredAccounts = [];
    this.drafts.forEach((draft, journalIndex) => {
      this.itemAccountCtrls[journalIndex] = [];
      this.rowFilteredAccounts[journalIndex] = [];
      this.journals.push(new FormGroup({
        key: new FormControl(draft.key, { nonNullable: true }),
        title: new FormControl(draft.title, { nonNullable: true }),
        can_post: new FormControl(draft.can_post, { nonNullable: true }),
        date: new FormControl(draft.date || '', { nonNullable: true, validators: draft.can_post ? [Validators.required] : [] }),
        description: new FormControl(draft.description || draft.title, { nonNullable: true }),
        sync_operational: new FormControl(this.defaultSyncOperational(draft), { nonNullable: true }),
        lines: new FormArray<FormGroup>([]),
      }));
      for (const line of draft.lines) {
        this.addLine(journalIndex, line);
      }
      if (draft.can_post && draft.lines.length === 0) {
        this.addLine(journalIndex);
        this.addLine(journalIndex);
      }
    });
  }

  private async loadAccountPicker(): Promise<void> {
    try {
      const accountsRes = await firstValueFrom(this.treeAccountService.getPickerList());
      const next = this.flattenAccounts(accountsRes?.data);
      if (next.length) {
        this.accountOptions = next;
        this.itemAccountCtrls.forEach((rows, journalIndex) => {
          rows.forEach((_ctrl, lineIndex) => this.refreshRowFilter(journalIndex, lineIndex));
        });
      }
    } catch {
      // المسودة تظهر بالحسابات المقترحة حتى لو تأخرت قائمة الشجرة
    }
  }

  private attachAccountCtrl(journalIndex: number, lineIndex: number, line?: Partial<InvoiceJournalLineDraft>): void {
    const opt = line?.account_id
      ? this.accountOptions.find((a) => a.id === Number(line.account_id))
        ?? (line.account_name
          ? { id: Number(line.account_id), label: this.accountLabel(line.account_code, line.account_name) }
          : null)
      : null;
    const ctrl = new FormControl<string | AccountOption>(opt ?? '', { nonNullable: true });
    ctrl.valueChanges.subscribe(() => this.refreshRowFilter(journalIndex, lineIndex));
    this.itemAccountCtrls[journalIndex][lineIndex] = ctrl;
    this.refreshRowFilter(journalIndex, lineIndex);
  }

  private filterAccounts(term: string): AccountOption[] {
    const raw = String(term ?? '').trim().toLowerCase();
    let list = this.accountOptions;
    if (raw) {
      list = list.filter((a) => a.label.toLowerCase().includes(raw) || String(a.id).includes(raw));
    }
    return list.slice(0, this.accountCap);
  }

  private flattenAccounts(nodes: any, out: AccountOption[] = []): AccountOption[] {
    const list = Array.isArray(nodes) ? nodes : [];
    for (const node of list) {
      if (node?.id && node?.name) {
        out.push({ id: Number(node.id), label: this.accountLabel(node.code, node.name) });
      }
      if (Array.isArray(node?.children) && node.children.length) {
        this.flattenAccounts(node.children, out);
      }
    }
    return out.sort((a, b) => a.label.localeCompare(b.label, 'ar'));
  }

  private accountLabel(code: unknown, name: unknown): string {
    const codeText = code != null && String(code).trim() !== '' ? String(code) : '';
    const nameText = String(name || '');
    return codeText ? `${codeText} — ${nameText}` : nameText;
  }
}
