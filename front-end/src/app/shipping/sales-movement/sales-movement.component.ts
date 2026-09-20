import { Component, OnDestroy, OnInit } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { PageEvent } from '@angular/material/paginator';
import { Subject, Subscription, firstValueFrom } from 'rxjs';
import { debounceTime } from 'rxjs/operators';
import Swal from 'sweetalert2';
import { DialogOrderInvoiceJournalComponent } from '../dialog-order-invoice-journal/dialog-order-invoice-journal.component';
import { OrderService } from '../services/order.service';

@Component({
  selector: 'app-sales-movement',
  templateUrl: './sales-movement.component.html',
  styleUrls: ['../../shared/styles/report-page-shell.css', './sales-movement.component.css'],
})
export class SalesMovementComponent implements OnInit, OnDestroy {
  dateFrom = '';
  dateTo = '';
  mode: 'order_date' | 'journal_date' | 'shipping_date' = 'order_date';
  missingOnly = false;
  searchTerm = '';
  showFilters = true;
  loading = false;
  postingMissing = false;
  loadError = false;
  loadErrorMessage = 'تعذر تحميل حركة المبيعات.';

  rows: any[] = [];
  summary: any = null;
  length = 0;
  page = 0;
  pageSize = 15;
  pageSizeOptions = [15, 50, 100];
  expanded = new Set<number>();
  journalLoading = new Set<number>();

  readonly modes: Array<{ id: 'order_date' | 'journal_date' | 'shipping_date'; label: string }> = [
    { id: 'order_date', label: 'بحث بتاريخ الطلب' },
    { id: 'shipping_date', label: 'بحث بتاريخ الشحن' },
    { id: 'journal_date', label: 'تاريخ القيد' },
  ];

  private loadSub?: Subscription;
  private readonly filterChanges = new Subject<void>();
  private readonly filterSub: Subscription;

  constructor(
    private orderService: OrderService,
    private dialog: MatDialog,
  ) {
    this.filterSub = this.filterChanges.pipe(debounceTime(280)).subscribe(() => this.load());
  }

  ngOnInit(): void {
    const today = this.todayIso();
    this.dateFrom = today;
    this.dateTo = today;
    this.load();
  }

  ngOnDestroy(): void {
    this.loadSub?.unsubscribe();
    this.filterSub.unsubscribe();
  }

  get missingInvoiceCount(): number {
    return Number(this.summary?.missing_invoice_count || 0);
  }

  get missingCogsCount(): number {
    return Number(this.summary?.missing_cogs_count || 0);
  }

  get missingJournalCount(): number {
    if (this.summary && this.summary.missing_journal_count != null) {
      return Number(this.summary.missing_journal_count);
    }
    return this.missingInvoiceCount + this.missingCogsCount;
  }

  get canPostMissingInvoices(): boolean {
    return this.isPostableDateMode && this.missingJournalCount > 0;
  }

  get isPostableDateMode(): boolean {
    return this.mode === 'order_date' || this.mode === 'shipping_date';
  }

  get activeDateLabel(): string {
    if (this.mode === 'shipping_date') {
      return 'تاريخ الشحن';
    }
    if (this.mode === 'journal_date') {
      return 'تاريخ القيد';
    }
    return 'تاريخ الطلب';
  }

  get isRefreshing(): boolean {
    return this.loading && this.rows.length > 0;
  }

  load(): void {
    if (!this.dateFrom || !this.dateTo) {
      return;
    }
    this.loadSub?.unsubscribe();
    this.loading = true;
    this.loadError = false;
    this.loadErrorMessage = 'تعذر تحميل حركة المبيعات.';
    this.loadSub = this.orderService.getSalesMovement({
      date_from: this.dateFrom,
      date_to: this.dateTo,
      mode: this.mode,
      search: this.searchTerm.trim() || undefined,
      missing_only: this.missingOnly || undefined,
      page: this.page + 1,
      itemsPerPage: this.pageSize,
    }).subscribe({
      next: (res: any) => {
        this.rows = Array.isArray(res?.data) ? res.data : [];
        this.summary = res?.summary || null;
        this.length = Number(res?.total || 0);
        this.loading = false;
      },
      error: (err) => {
        this.rows = [];
        this.summary = null;
        this.length = 0;
        this.loadError = true;
        this.loadErrorMessage = err?.error?.message
          || (err?.status === 403
            ? 'غير مصرح بعرض حركة المبيعات. تأكد من صلاحية عرض الطلبات.'
            : 'تعذر تحميل حركة المبيعات. حاول مرة أخرى.');
        this.loading = false;
      },
    });
  }

  toggleMissingOnly(): void {
    this.missingOnly = !this.missingOnly;
    this.page = 0;
    this.expanded.clear();
    this.load();
  }

  setMode(mode: 'order_date' | 'journal_date' | 'shipping_date'): void {
    if (this.mode === mode) {
      return;
    }
    this.mode = mode;
    this.page = 0;
    this.expanded.clear();
    this.load();
  }

  onSearch(): void {
    this.page = 0;
    this.expanded.clear();
    this.filterChanges.next();
  }

  applyFilters(): void {
    this.page = 0;
    this.expanded.clear();
    this.load();
  }

  onPageChange(event: PageEvent): void {
    this.page = event.pageIndex;
    this.pageSize = event.pageSize;
    this.expanded.clear();
    this.load();
  }

  openInvoiceJournal(row: any, event?: Event): void {
    event?.stopPropagation();
    if (!row?.order_id) {
      return;
    }
    this.dialog.open(DialogOrderInvoiceJournalComponent, {
      width: '980px',
      maxWidth: '96vw',
      data: {
        orderId: row.order_id,
        customerName: row.customer_name,
        netTotal: row.net_total,
      },
    }).afterClosed().subscribe((posted) => {
      if (posted) {
        this.load();
      }
    });
  }

  toggleExpand(orderId: number): void {
    if (this.expanded.has(orderId)) {
      this.expanded.delete(orderId);
      return;
    }
    this.expanded.add(orderId);
    const row = this.rows.find((item) => item.order_id === orderId);
    if (!row || (Array.isArray(row.journals) && row.journals.length) || this.journalLoading.has(orderId)) {
      return;
    }
    if (!row.journals_count && !row.missing_invoice && !row.missing_cogs && !row.missing_lifecycle) {
      return;
    }
    this.journalLoading.add(orderId);
    this.orderService.getAccountingCycle(orderId).subscribe({
      next: (cycle) => {
        row.journals = Array.isArray(cycle?.journals) ? cycle.journals : [];
        row.stages = Array.isArray(cycle?.stages) ? cycle.stages : row.stages;
        row.journals_count = Number(cycle?.journals_count ?? row.journals_count);
        row.lines_count = Number(cycle?.lines_count ?? row.lines_count);
        row.total_debit = Number(cycle?.total_debit ?? row.total_debit);
        row.total_credit = Number(cycle?.total_credit ?? row.total_credit);
        this.journalLoading.delete(orderId);
      },
      error: () => {
        this.journalLoading.delete(orderId);
      },
    });
  }

  trackByOrderId(_index: number, row: any): number {
    return row?.order_id;
  }

  visibleStages(row: any): any[] {
    return (Array.isArray(row?.stages) ? row.stages : []).filter((stage: any) =>
      stage?.applicable || stage?.posted || (Array.isArray(stage?.journals) && stage.journals.length)
    );
  }

  otherJournals(row: any): any[] {
    return (Array.isArray(row?.journals) ? row.journals : []).filter((journal: any) =>
      !journal?.stage || journal.stage === 'other'
    );
  }

  missingChipLabel(row: any): string {
    if (row?.missing_invoice && row?.missing_cogs) {
      return 'بدون قيد فاتورة وتكلفة';
    }
    if (row?.missing_invoice) {
      return 'بدون قيد فاتورة';
    }
    if (row?.missing_cogs) {
      return 'بدون قيد تكلفة المخزن';
    }
    const titles = Array.isArray(row?.missing_stage_titles)
      ? row.missing_stage_titles.filter((title: unknown) => typeof title === 'string' && title.trim() !== '')
      : [];
    if (titles.length === 1) {
      return 'بدون قيد ' + titles[0];
    }
    if (titles.length > 1) {
      return 'قيود ناقصة: ' + titles.join('، ');
    }
    return 'قيود ناقصة حسب الحالة';
  }

  canPostRow(row: any): boolean {
    return !!(row?.missing_invoice || row?.missing_cogs || row?.missing_lifecycle);
  }

  async postRowJournals(row: any, event?: Event): Promise<void> {
    event?.stopPropagation();
    if (!row?.order_id) {
      return;
    }
    this.openInvoiceJournal(row, event);
  }

  private async postMissingCogsForOrder(row: any): Promise<void> {
    try {
      this.postingMissing = true;
      Swal.fire({
        title: 'جاري ترحيل القيود الناقصة…',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });
      const result: any = await firstValueFrom(
        this.orderService.postOrderMissingInvoice(row.order_id, {})
      );
      Swal.close();
      await Swal.fire({
        icon: result?.success ? 'success' : 'info',
        title: result?.message || 'انتهى الترحيل',
        timer: 1800,
        showConfirmButton: false,
      });
      if (result?.success) {
        this.load();
      }
    } catch (err: any) {
      Swal.close();
      await Swal.fire({
        icon: 'error',
        title: 'لم يكتمل الترحيل',
        text: err?.error?.message || err?.message || 'تعذر ترحيل قيد التكلفة',
      });
    } finally {
      this.postingMissing = false;
    }
  }

  async postMissingInvoices(): Promise<void> {
    if (!this.canPostMissingInvoices || this.postingMissing || !this.dateFrom || !this.dateTo) {
      return;
    }

    try {
      this.postingMissing = true;
      Swal.fire({
        title: 'جاري فحص القيود الناقصة…',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
      });

      const preview: any = await firstValueFrom(
        this.orderService.previewMissingSalesInvoices({
          date_from: this.dateFrom,
          date_to: this.dateTo,
          mode: this.mode === 'shipping_date' ? 'shipping_date' : 'order_date',
        })
      );

      Swal.close();

      if (preview?.would_exceed_limit) {
        await Swal.fire({
          icon: 'warning',
          title: 'تجاوز الحد',
          text: `عدد الطلبات الناقصة (${preview.missing_count}) يتجاوز الحد المسموح لكل تشغيل (${preview.max_orders_per_run}). قلّل نطاق التاريخ.`,
        });
        return;
      }

      const missing = Number(preview?.missing_count || 0);
      if (missing === 0) {
        await Swal.fire({
          icon: 'info',
          title: 'لا توجد قيود ناقصة',
          text: 'كل الطلبات الظاهرة في هذا التاريخ لديها قيد إثبات فاتورة وقيد تكلفة المخزن، أو أنها ملغاة/غير مشحونة.',
        });
        this.load();
        return;
      }

      const sample = Array.isArray(preview?.sample_order_ids) ? preview.sample_order_ids.slice(0, 10) : [];
      const sampleHtml = sample.length
        ? `<p class="text-right small text-muted mb-0">أمثلة: ${sample.map((id: number) => '#' + id).join('، ')}</p>`
        : '';
      const postedNote = (preview?.already_posted_count ?? 0) > 0
        ? `<p class="text-right small mb-1">طلبات لديها قيد الفاتورة مسبقاً ولن تُمس: <b>${preview.already_posted_count}</b></p>`
        : '';
      const ineligibleNote = (preview?.skipped_ineligible_count ?? 0) > 0
        ? `<p class="text-right small mb-1">ملغى / أرشيف / محوّل من عرض (يُتجاوز للفاتورة): <b>${preview.skipped_ineligible_count}</b></p>`
        : '';
      const cogsNote = (preview?.missing_cogs_count ?? 0) > 0
        ? `<p class="text-right small mb-1">بدون قيد تكلفة خروج المخزن التام: <b>${preview.missing_cogs_count}</b></p>`
        : '';
      const invoiceNote = (preview?.missing_invoice_count ?? 0) > 0
        ? `<p class="text-right small mb-1">بدون قيد إثبات مبيعات: <b>${preview.missing_invoice_count}</b></p>`
        : '';

      const confirm = await Swal.fire({
        icon: 'question',
        title: 'ترحيل القيود الناقصة',
        html: `
          <p class="text-right">سيُنشأ القيد الناقص حسب حالة الطلب لـ <b>${missing}</b> طلب
          حسب <b>${this.activeDateLabel}</b> من <b>${this.dateFrom}</b> إلى <b>${this.dateTo}</b>.</p>
          <p class="text-right small">يُرحَّل إثبات المبيعات وتكلفة خروج المخزن التام والتسليم وتسوية الرفض إن كانت الحالة تتطلب ذلك، دون حذف أي قيد موجود ودون إنقاص المخزن مرة أخرى.</p>
          ${invoiceNote}${cogsNote}${postedNote}${ineligibleNote}${sampleHtml}
        `,
        showCancelButton: true,
        confirmButtonText: 'ترحيل الناقص فقط',
        cancelButtonText: 'إلغاء',
        confirmButtonColor: '#82225e',
      });

      if (!confirm.isConfirmed) {
        return;
      }

      Swal.fire({
        title: 'جاري ترحيل القيود الناقصة…',
        html: '<p class="small text-muted">قد يستغرق ذلك بعض الوقت. لا تغلق الصفحة.</p>',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading(),
      });

      const result: any = await firstValueFrom(
        this.orderService.runMissingSalesInvoices({
          date_from: this.dateFrom,
          date_to: this.dateTo,
          mode: this.mode === 'shipping_date' ? 'shipping_date' : 'order_date',
        })
      );

      Swal.close();

      let failHtml = '';
      const fails = Array.isArray(result?.failures) ? result.failures.slice(0, 15) : [];
      if (fails.length) {
        failHtml =
          '<p class="text-right small mt-2">طلبات تعذر ترحيلها:</p><ul class="text-right small" style="max-height:140px;overflow:auto;">' +
          fails.map((f: any) => `<li>#${f.order_id}: ${(f.error || '').toString().slice(0, 120)}</li>`).join('') +
          '</ul>';
      }

      await Swal.fire({
        icon: (result?.orders_failed ?? 0) > 0 && (result?.orders_posted ?? 0) === 0 ? 'error' : 'success',
        title: result?.message || 'انتهى',
        html:
          `<p class="text-right">تم ترحيل: <b>${result?.orders_posted ?? 0}</b> — فشل: <b>${result?.orders_failed ?? 0}</b></p>` +
          failHtml,
      });

      this.page = 0;
      this.load();
    } catch (err: any) {
      Swal.close();
      const msg = err?.error?.message || err?.message || 'حدث خطأ';
      await Swal.fire({ icon: 'error', title: 'لم يكتمل الطلب', text: msg });
    } finally {
      this.postingMissing = false;
    }
  }

  private todayIso(): string {
    const today = new Date();
    const y = today.getFullYear();
    const m = String(today.getMonth() + 1).padStart(2, '0');
    const d = String(today.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }
}
