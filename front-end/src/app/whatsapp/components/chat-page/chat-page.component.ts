import {
  ChangeDetectorRef,
  Component,
  ElementRef,
  OnDestroy,
  OnInit,
  ViewChild,
} from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { Subscription, combineLatest, timer } from 'rxjs';
import { debounceTime, distinctUntilChanged, finalize } from 'rxjs/operators';
import Swal from 'sweetalert2';

import { WhatsAppService } from '../../services/whatsapp.service';
import {
  ChatMessage,
  ConversationOrder,
  MessageService,
  MessagesFilters,
} from '../../services/message.service';

type PreviewKind = 'image' | 'video' | 'audio' | 'other';

interface MessageView extends ChatMessage {
  /** Blob URL for media once resolved through the proxy. Null until loaded. */
  _mediaObjectUrl?: string | null;
  _mediaLoading?: boolean;
}

interface ThreadScrollPersist {
  filtersKey: string;
  oldestId: number;
  newestId: number;
  scrollTop: number;
  scrollHeight: number;
  clientHeight: number;
  nearBottom: boolean;
}

interface ListScrollPersist {
  scrollTop: number;
  page: number;
  filtersKey: string;
}

/**
 * WhatsApp-Web style chat page:
 *  - Cursor-based pagination (newest batch first, older on demand).
 *  - Search + date-range filters (debounced, reload conversation).
 *  - Inline media rendering through the /api/media/{id} proxy.
 *  - Maintains scroll position when prepending older messages.
 */
@Component({
  selector: 'app-chat-page',
  templateUrl: './chat-page.component.html',
  styleUrls: ['./chat-page.component.css'],
})
export class ChatPageComponent implements OnInit, OnDestroy {
  @ViewChild('scrollArea') scrollArea?: ElementRef<HTMLDivElement>;
  @ViewChild('listScrollArea') listScrollArea?: ElementRef<HTMLDivElement>;

  private readonly PAGE_SIZE = 20;
  private readonly storageListKey = 'erp_wa_chat:list_scroll_v1';
  private readonly storageThreadPrefix = 'erp_wa_chat:thread_v1:';

  /** Last loaded page of the customer list (Laravel paginator). */
  listPage = 0;
  listLastPage = 1;
  /** إجمالي العملاء من الـ API (للعرض في زر تحميل المزيد). */
  listTotalCustomers = 0;
  /** تحميل صفحة إضافية فقط (لا يعطل القائمة الحالية). */
  listLoadingMore = false;
  /** فشل تحميل قائمة العملاء */
  listLoadError = '';
  /** موبايل: إظهار فلاتر الرسائل في رأس المحادثة */
  threadFiltersOpen = false;
  /** موبايل: إظهار اقتراحات الرد السريع */
  suggestionsOpen = false;
  private skipRouteListReload = false;
  /** When restoring session, load list pages 1..N before applying scroll. */
  private listRestoreTargetPage = 1;
  private pendingListScrollTop: number | null = null;
  private listScrollSaveTimer: ReturnType<typeof setTimeout> | null = null;
  private threadScrollSaveTimer: ReturnType<typeof setTimeout> | null = null;
  /** Customer whose thread view state we persist when leaving the route. */
  private threadPersistCustomerId: number | null = null;
  /** Used to persist the previous thread when switching to another customer. */
  private lastBootstrappedCustomerId: number | null = null;

  customerId: number | null = null;
  customer: any = null;
  customers: any[] = [];
  /** تصفية قائمة المحادثات (اسم / رقم) — يظهر في عمود «المحادثات» */
  listSearch = new FormControl<string>('');
  /** من / إلى: يعيد تحميل القائمة من الـ API (عميل له رسالة ضمن النطاق) */
  listFilterForm = new FormGroup({
    from_date: new FormControl<string>(''),
    to_date: new FormControl<string>(''),
  });
  /** الصندوق يخفي المؤرشفين؛ الأرشيف يظهرهم فقط. رسالة واردة جديدة تُعيد العميل للصندوق. */
  listArchiveFilter: 'inbox' | 'archived' = 'inbox';
  listInboxCount = 0;
  listArchivedCount = 0;
  archiveBusyId: number | null = null;
  archiveAllBusy = false;

  messages: MessageView[] = [];
  hasMore = false;
  cursor: string | null = null;

  loading = false;
  /** أول تحميل للقائمة فقط (قبل ظهور أي عميل) */
  listInitialLoading = false;
  /** تحميل عمود «المحادثات» فقط (لا يعطل منطقة الرسائل) */
  listLoading = false;
  /** يمنع طلبات customers المتزامنة (سبب التعليق على الموبايل). */
  private listFetchBusy = false;
  private pendingListReload = false;
  private customersRequestSeq = 0;
  /** يُزاد عند كل طلب قائمة — لتحرير القفل حتى لو رُفض رد قديم. */
  private listFetchGeneration = 0;
  loadingMore = false;
  sending = false;

  templates: any[] = [];
  chatMetaTemplates: any[] = [];
  sendingMetaTemplate = false;
  quickSnippets: { label: string; text: string }[] = [
    { label: 'ترحيب', text: 'مرحباً، شكراً لتواصلك معنا. كيف يمكننا مساعدتك اليوم؟' },
    { label: 'متابعة الطلب', text: 'تمت متابعة طلبك، وسنُعلمك بأي تحديث في أقرب وقت.' },
    { label: 'تأكيد استلام', text: 'تم استلام رسالتك، وسيقوم فريقنا بالرد قريباً.' },
    { label: 'بيانات الشحن', text: 'نحتاج تأكيد عنوان الشحن ورقم هاتفك لإتمام التوصيل.' },
  ];

  filtersForm = new FormGroup({
    search: new FormControl<string>(''),
    from_date: new FormControl<string>(''),
    to_date: new FormControl<string>(''),
  });

  messageForm = new FormGroup({
    message: new FormControl<string>('', [Validators.required]),
  });

  /** Media lightbox state. */
  preview: { open: boolean; url: string; kind: PreviewKind; msg: MessageView | null } = {
    open: false,
    url: '',
    kind: 'image',
    msg: null,
  };

  /** Customer orders sheet (products + link to full order). */
  ordersPanelOpen = false;
  ordersPanelLoading = false;
  ordersPanelError = '';
  conversationOrders: ConversationOrder[] = [];
  focusedOrderId: number | null = null;
  expandedOrderIds = new Set<number>();
  private ordersCacheCustomerId: number | null = null;

  private readonly expanded = new Set<string>();
  private subs: Subscription[] = [];
  /** حدّ استعادة الصفحات من sessionStorage عند فتح الصفحة. */
  private readonly LIST_RESTORE_MAX_PAGES_DESKTOP = 3;
  private readonly LIST_RESTORE_MAX_PAGES_MOBILE = 1;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private whatsappService: WhatsAppService,
    private messageService: MessageService,
    private cdr: ChangeDetectorRef
  ) {}

  // ────────────────────────────────────────────────────────── lifecycle

  ngOnInit(): void {
    this.loadTemplates();
    this.loadChatMetaTemplates();

    this.subs.push(
      combineLatest([this.route.paramMap, this.route.queryParamMap]).subscribe(
        ([params, query]) => {
          const cid = params.get('customerId');
          if (!cid && this.threadPersistCustomerId != null) {
            this.persistThreadState(this.threadPersistCustomerId);
            this.threadPersistCustomerId = null;
          }
          if (cid) {
            const id = +cid;
            this.customerId = id;
            this.threadPersistCustomerId = id;
            if (!this.skipRouteListReload) {
              this.loadCustomers(true);
            }
            this.skipRouteListReload = false;
            if (!this.customer || Number(this.customer.id) !== id) {
              const row = this.customers.find((c) => c.id === id);
              this.customer = row ?? { id, name: '', phone: '' };
            }
            this.threadFiltersOpen = false;
            this.suggestionsOpen = false;
            this.bootstrapConversation();
            return;
          }
          this.customerId = null;
          this.customer = null;
          const phone = query.get('phone');
          if (phone) {
            this.findCustomerByPhone(phone);
          } else {
            this.loadCustomers();
          }
        }
      )
    );

    // استطلاع خفيف للقائمة حتى ترجع رسالة العميل المؤرشف للصندوق بسرعة.
    this.subs.push(
      timer(8000, 12000).subscribe(() => {
        if (typeof document !== 'undefined' && document.hidden) {
          return;
        }
        this.refreshCustomerListFromServer();
      })
    );

    // Debounced filter changes → reload the conversation.
    this.subs.push(
      this.filtersForm.valueChanges
        .pipe(
          debounceTime(350),
          distinctUntilChanged(
            (a, b) => JSON.stringify(a) === JSON.stringify(b)
          )
        )
        .subscribe(() => {
          if (this.customerId) {
            this.bootstrapConversation();
          }
        })
    );

    this.subs.push(
      this.listFilterForm.valueChanges
        .pipe(
          debounceTime(400),
          distinctUntilChanged(
            (a, b) => JSON.stringify(a) === JSON.stringify(b)
          )
        )
        .subscribe(() => {
          this.loadCustomers();
        })
    );
  }

  ngOnDestroy(): void {
    if (this.threadPersistCustomerId != null) {
      this.persistThreadState(this.threadPersistCustomerId);
    }
    if (this.listScrollSaveTimer) {
      clearTimeout(this.listScrollSaveTimer);
    }
    if (this.threadScrollSaveTimer) {
      clearTimeout(this.threadScrollSaveTimer);
    }
    this.subs.forEach((s) => s.unsubscribe());
    this.messageService.releaseCache();
  }

  // ────────────────────────────────────────────────────────── customers

  loadCustomers(reset = true): void {
    if (this.listFetchBusy) {
      if (reset) {
        this.pendingListReload = true;
      }
      return;
    }

    if (reset) {
      this.listPage = 0;
      const snap = this.readListPersistence();
      const fk = this.listFiltersStorageKey();
      const maxRestore = this.listRestoreMaxPages();
      if (snap && snap.filtersKey === fk) {
        this.listRestoreTargetPage = Math.max(
          1,
          Math.min(snap.page || 1, maxRestore)
        );
        this.pendingListScrollTop =
          typeof snap.scrollTop === 'number' ? snap.scrollTop : null;
      } else {
        this.listRestoreTargetPage = 1;
        this.pendingListScrollTop = null;
      }
    }

    const pageToFetch = reset ? 1 : this.listPage + 1;
    if (!reset && pageToFetch > this.listLastPage) {
      return;
    }

    const requestSeq = ++this.customersRequestSeq;
    const fetchGen = ++this.listFetchGeneration;
    this.listFetchBusy = true;
    this.listLoadError = '';
    if (reset && !this.customers.length) {
      this.listInitialLoading = true;
    }
    if (reset) {
      this.listLoading = true;
    } else {
      this.listLoadingMore = true;
    }
    const scrollEl = !reset ? this.listScrollArea?.nativeElement : null;
    const scrollBefore = scrollEl?.scrollTop ?? 0;

    const params = this.listQueryParams(pageToFetch);
    this.whatsappService
      .getCustomers(params)
      .pipe(
        finalize(() => {
          if (fetchGen === this.listFetchGeneration) {
            this.endListFetch();
            this.cdr.markForCheck();
          }
        })
      )
      .subscribe({
        next: (res: any) => {
          if (requestSeq !== this.customersRequestSeq) {
            return;
          }
          if (!res?.success) {
            this.listLoadError =
              res?.error || 'تعذر تحميل قائمة المحادثات.';
            return;
          }
          const page = res.data;
          const rows = page?.data ?? (Array.isArray(page) ? page : []);
          const list: any[] = (Array.isArray(rows) ? rows : []).map((c) =>
            this.normalizeCustomerRow(c)
          );
          this.listLastPage = page?.last_page ?? 1;
          this.listPage = page?.current_page ?? pageToFetch;
          this.listTotalCustomers = page?.total ?? list.length;
          this.applyArchiveCounts(res?.archive_counts);

          if (pageToFetch === 1) {
            this.customers = list;
            this.ensureActiveCustomerInList();
            this.sortCustomerListByRecency();
          } else {
            const seen = new Set(this.customers.map((c) => c.id));
            for (const c of list) {
              if (!seen.has(c.id)) {
                this.customers.push(c);
                seen.add(c.id);
              }
            }
            requestAnimationFrame(() => {
              if (scrollEl) {
                scrollEl.scrollTop = scrollBefore;
              }
            });
          }
          this.finishListLoadSequence();
        },
        error: () => {
          if (requestSeq !== this.customersRequestSeq) {
            return;
          }
          this.listLoadError =
            'تعذر تحميل قائمة المحادثات. تحقق من الاتصال وحاول مرة أخرى.';
        },
      });
  }

  /** يضمن ظهور المحادثة المفتوحة في القائمة (مهم عند فتح /chat/:id مباشرة). */
  private ensureActiveCustomerInList(): void {
    const cid = this.customerId;
    if (!cid) {
      return;
    }
    let row =
      this.customer && Number(this.customer.id) === cid
        ? this.normalizeCustomerRow({ ...this.customer })
        : null;
    if (!row) {
      row = this.customers.find((c) => c.id === cid) ?? null;
    }
    if (!row) {
      return;
    }
    const idx = this.customers.findIndex((c) => c.id === cid);
    if (idx >= 0) {
      this.customers[idx] = { ...this.customers[idx], ...row };
    } else {
      this.customers = [row, ...this.customers];
    }
  }

  private endListFetch(): void {
    this.listLoading = false;
    this.listLoadingMore = false;
    this.listInitialLoading = false;
    this.listFetchBusy = false;
    if (this.pendingListReload) {
      this.pendingListReload = false;
      this.loadCustomers(true);
    }
  }

  private listPerPage(): number {
    if (typeof window === 'undefined') {
      return 40;
    }
    return window.innerWidth < 768 ? 40 : 35;
  }

  /** زر تحميل المزيد — ثابت أسفل القائمة على الموبايل. */
  get canLoadMoreCustomers(): boolean {
    return (
      !this.customerId &&
      this.listPage > 0 &&
      this.listPage < this.listLastPage &&
      !this.listInitialLoading
    );
  }

  loadMoreCustomers(ev?: Event): void {
    ev?.preventDefault();
    ev?.stopPropagation();
    if (
      this.listFetchBusy ||
      this.listLoadingMore ||
      this.listPage >= this.listLastPage
    ) {
      return;
    }
    this.loadCustomers(false);
  }

  private normalizeCustomerRow(c: any): any {
    if (!c) {
      return c;
    }
    if (c.last_message_content != null && !Array.isArray(c.messages)) {
      c.messages = [
        {
          id: c.last_message_id,
          content: c.last_message_content,
          type: c.last_message_type || 'text',
          direction:
            c.last_message_direction === 'inbound' ? 'received' : 'sent',
          created_at: c.messages_max_created_at,
        },
      ];
    }
    if (c.is_archived === false || c.is_archived === 0 || c.is_archived === '0') {
      c.whatsapp_archived_at = null;
    }
    c.is_archived = this.isCustomerArchived(c);
    if (c.awaiting_reply == null) {
      const dir = c.last_message_direction || c.messages?.[0]?.direction;
      c.awaiting_reply = dir === 'inbound' || dir === 'received';
    } else {
      c.awaiting_reply = c.awaiting_reply === true || c.awaiting_reply === 1 || c.awaiting_reply === '1';
    }
    return c;
  }

  isCustomerArchived(c: any): boolean {
    if (!c) {
      return false;
    }
    if (c.is_archived === true || c.is_archived === 1 || c.is_archived === '1') {
      return true;
    }
    return !!c.whatsapp_archived_at;
  }

  isAwaitingReply(c: any): boolean {
    if (!c) {
      return false;
    }
    if (c.awaiting_reply === true || c.awaiting_reply === 1 || c.awaiting_reply === '1') {
      return true;
    }
    const dir = c.last_message_direction || c.messages?.[0]?.direction;
    return dir === 'inbound' || dir === 'received';
  }

  canArchiveCustomer(c: any): boolean {
    if (this.isCustomerArchived(c)) {
      return true;
    }
    return !this.isAwaitingReply(c);
  }

  get isOpenConversationAwaitingReply(): boolean {
    if (this.messages.length) {
      return this.messages[this.messages.length - 1]?.direction === 'received';
    }
    return this.isAwaitingReply(this.customer);
  }

  get canArchiveOpenConversation(): boolean {
    if (this.isOpenConversationArchived) {
      return true;
    }
    return !this.isOpenConversationAwaitingReply;
  }

  archiveButtonTooltip(c: any): string {
    if (this.isCustomerArchived(c)) {
      return 'إرجاع للصندوق';
    }
    if (this.isAwaitingReply(c)) {
      return 'لا يمكن الأرشفة قبل الرد على رسالة العميل';
    }
    return 'أرشفة بعد الرد';
  }

  setListArchiveFilter(mode: 'inbox' | 'archived'): void {
    if (this.listArchiveFilter === mode) {
      return;
    }
    this.listArchiveFilter = mode;
    this.loadCustomers(true);
  }

  get canArchiveAllInbox(): boolean {
    return (
      this.listArchiveFilter === 'inbox' &&
      this.listInboxCount > 0 &&
      !this.listInitialLoading
    );
  }

  archiveAllInbox(): void {
    if (!this.canArchiveAllInbox || this.archiveAllBusy) {
      return;
    }
    const lf = this.listFilterForm.value;
    const hasDates = !!(lf.from_date || lf.to_date);
    const count = this.listInboxCount;
    const scope = hasDates
      ? `كل محادثات الصندوق المطابقة لفلتر التاريخ (${count})`
      : `كل محادثات الصندوق (${count})`;

    Swal.fire({
      icon: 'question',
      title: 'أرشفة الجميع؟',
      text: `${scope}. المحادثات التي بعت فيها العميل وما زلنا لم نرد ستبقى في الصندوق.`,
      showCancelButton: true,
      confirmButtonText: 'أرشفة الجميع',
      cancelButtonText: 'إلغاء',
      confirmButtonColor: '#075e54',
    }).then((result) => {
      if (!result.isConfirmed) {
        return;
      }
      this.archiveAllBusy = true;
      const payload: { from_date?: string; to_date?: string } = {};
      if (lf.from_date) {
        payload.from_date = lf.from_date;
      }
      if (lf.to_date) {
        payload.to_date = lf.to_date;
      }
      this.whatsappService.archiveAllCustomers(payload).subscribe({
        next: (res: any) => {
          this.archiveAllBusy = false;
          if (!res?.success) {
            Swal.fire({
              icon: 'error',
              title: 'خطأ',
              text: res?.error || 'تعذر أرشفة المحادثات',
            });
            return;
          }
          this.applyArchiveCounts(res?.archive_counts);
          const archivedCount = Number(res?.data?.archived_count) || 0;
          if (
            this.customer &&
            this.listArchiveFilter === 'inbox' &&
            this.canArchiveCustomer(this.customer)
          ) {
            this.customer = {
              ...this.customer,
              is_archived: true,
              awaiting_reply: false,
              whatsapp_archived_at:
                this.customer.whatsapp_archived_at || new Date().toISOString(),
            };
          }
          const isMobile = typeof window !== 'undefined' && window.innerWidth < 768;
          if (isMobile && this.customerId && this.canArchiveCustomer(this.customer)) {
            this.backToConversations();
          }
          this.loadCustomers(true);
          Swal.fire({
            icon: archivedCount > 0 ? 'success' : 'info',
            title: archivedCount > 0 ? 'تم' : 'تنبيه',
            text:
              archivedCount > 0
                ? `تم أرشفة ${archivedCount} محادثة. المحادثات المنتظرة رداً بقيت في الصندوق.`
                : 'لا توجد محادثات يمكن أرشفتها. رد على رسائل العملاء أولاً.',
            timer: archivedCount > 0 ? 1800 : 2800,
            showConfirmButton: archivedCount === 0,
          });
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.archiveAllBusy = false;
          Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: err?.error?.error || 'تعذر أرشفة المحادثات',
          });
        },
      });
    });
  }

  toggleCustomerArchive(c: any, ev?: Event): void {
    ev?.preventDefault();
    ev?.stopPropagation();
    if (!c?.id || this.archiveBusyId === c.id) {
      return;
    }
    const nextArchived = !this.isCustomerArchived(c);
    if (nextArchived && !this.canArchiveCustomer(c)) {
      return;
    }
    this.setCustomerArchived(c.id, nextArchived);
  }

  toggleOpenConversationArchive(): void {
    if (!this.customerId || !this.canArchiveOpenConversation) {
      return;
    }
    this.setCustomerArchived(this.customerId, !this.isOpenConversationArchived);
  }

  get isOpenConversationArchived(): boolean {
    return this.isCustomerArchived(this.customer);
  }

  private setCustomerArchived(customerId: number, archived: boolean): void {
    this.archiveBusyId = customerId;
    this.whatsappService.setCustomerArchive(customerId, archived).subscribe({
      next: (res: any) => {
        this.archiveBusyId = null;
        if (!res?.success) {
          Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: res?.error || 'تعذر تحديث الأرشيف',
          });
          return;
        }
        const archivedAt = res?.data?.whatsapp_archived_at ?? (archived ? new Date().toISOString() : null);
        this.applyLocalArchiveState(customerId, archived, archivedAt);
        if (archived && this.listArchiveFilter === 'inbox') {
          this.listInboxCount = Math.max(0, this.listInboxCount - 1);
          this.listArchivedCount += 1;
        } else if (!archived && this.listArchiveFilter === 'archived') {
          this.listArchivedCount = Math.max(0, this.listArchivedCount - 1);
          this.listInboxCount += 1;
        }
        const isMobile = typeof window !== 'undefined' && window.innerWidth < 768;
        if (archived && isMobile && this.customerId === customerId) {
          this.backToConversations();
        }
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.archiveBusyId = null;
        Swal.fire({
          icon: 'error',
          title: 'خطأ',
          text: err?.error?.error || 'تعذر تحديث الأرشيف',
        });
      },
    });
  }

  private applyLocalArchiveState(
    customerId: number,
    archived: boolean,
    archivedAt: string | null
  ): void {
    const current =
      this.customers.find((row) => row.id === customerId) || this.customer;
    const patch = {
      is_archived: archived,
      awaiting_reply: archived ? false : this.isAwaitingReply(current),
      whatsapp_archived_at: archived ? archivedAt : null,
    };
    if (this.customer && Number(this.customer.id) === customerId) {
      this.customer = { ...this.customer, ...patch };
    }
    const idx = this.customers.findIndex((row) => row.id === customerId);
    if (idx >= 0) {
      this.customers[idx] = { ...this.customers[idx], ...patch };
    } else if (!archived && this.listArchiveFilter === 'inbox') {
      const base =
        this.customer && Number(this.customer.id) === customerId
          ? this.customer
          : { id: customerId };
      this.customers = [
        this.normalizeCustomerRow({ ...base, ...patch }),
        ...this.customers,
      ];
    }
    this.customers = this.customers.filter((row) => {
      if (archived && this.customerId && row.id === this.customerId) {
        return true;
      }
      return this.matchesListArchiveFilter(row);
    });
    this.sortCustomerListByRecency();
  }

  /** رسالة واردة من العميل تُخرجه من الأرشيف وتعيده للصندوق فوراً. */
  private restoreConversationToInbox(
    customerId: number,
    extras: Record<string, unknown> = {}
  ): void {
    extras = { ...extras, awaiting_reply: true, last_message_direction: 'inbound' };
    const current =
      this.customers.find((c) => c.id === customerId) || this.customer;
    const wasArchived = this.isCustomerArchived(current);
    if (this.customer && Number(this.customer.id) === customerId) {
      this.customer = { ...this.customer, ...extras };
    }
    const idx = this.customers.findIndex((row) => row.id === customerId);
    if (idx >= 0) {
      this.customers[idx] = { ...this.customers[idx], ...extras };
    }
    this.applyLocalArchiveState(customerId, false, null);
    if (wasArchived) {
      this.listInboxCount += 1;
      this.listArchivedCount = Math.max(0, this.listArchivedCount - 1);
    }
  }

  private applyConversationArchiveFromServer(conversation: any): void {
    if (!conversation?.id) {
      return;
    }
    if (this.isCustomerArchived(conversation)) {
      return;
    }
    const current =
      this.customers.find((c) => c.id === conversation.id) || this.customer;
    if (
      !this.isCustomerArchived(current) &&
      this.listArchiveFilter === 'inbox'
    ) {
      return;
    }
    this.restoreConversationToInbox(Number(conversation.id), conversation);
  }

  private matchesListArchiveFilter(c: any): boolean {
    const archived = this.isCustomerArchived(c);
    return this.listArchiveFilter === 'archived' ? archived : !archived;
  }

  private applyArchiveCounts(counts: any): void {
    if (!counts) {
      return;
    }
    this.listInboxCount = Number(counts.inbox) || 0;
    this.listArchivedCount = Number(counts.archived) || 0;
  }

  private listQueryParams(page: number): Record<string, string | number> {
    const lf = this.listFilterForm.value;
    const params: Record<string, string | number> = {
      per_page: this.listPerPage(),
      page,
      archive: this.listArchiveFilter,
    };
    if (lf.from_date) {
      params['from_date'] = lf.from_date;
    }
    if (lf.to_date) {
      params['to_date'] = lf.to_date;
    }
    return params;
  }

  private listRestoreMaxPages(): number {
    if (typeof window === 'undefined') {
      return this.LIST_RESTORE_MAX_PAGES_DESKTOP;
    }
    return window.innerWidth < 768
      ? this.LIST_RESTORE_MAX_PAGES_MOBILE
      : this.LIST_RESTORE_MAX_PAGES_DESKTOP;
  }

  /** أول حرف من الاسم (أو من الرقم) لرمز العميل في القائمة. */
  customerInitial(c: any): string {
    const name = (c?.name || '').toString().trim();
    if (name) {
      return name.charAt(0).toUpperCase();
    }
    const phone = (c?.phone || '').toString().trim();
    return phone ? phone.replace(/\D/g, '').slice(-1) || '#' : '#';
  }

  /** آخر رسالة كنص مختصر يظهر في عمود القائمة. */
  customerLastPreview(c: any): string {
    if (c?.last_message_content != null) {
      const type = c.last_message_type;
      if (type === 'image' || type === 'sticker') return '🖼️ صورة';
      if (type === 'video') return '🎬 فيديو';
      if (type === 'audio') return '🎤 رسالة صوتية';
      if (type === 'document') return '📎 ملف مرفق';
      const text = String(c.last_message_content).trim();
      if (text) {
        return text.length > 60 ? text.slice(0, 57) + '…' : text;
      }
    }
    const last = Array.isArray(c?.messages) ? c.messages[0] : null;
    if (!last) {
      return c?.phone || '';
    }
    const type = last.type;
    if (type === 'image' || type === 'sticker') return '🖼️ صورة';
    if (type === 'video') return '🎬 فيديو';
    if (type === 'audio') return '🎤 رسالة صوتية';
    if (type === 'document') return '📎 ملف مرفق';
    const text = (last.content || last.message || '').toString().trim();
    if (!text) return c?.phone || '';
    return text.length > 60 ? text.slice(0, 57) + '…' : text;
  }

  /** وقت آخر رسالة بصيغة مختصرة (اليوم: HH:MM، أمس، يوم الأسبوع، أو تاريخ). */
  customerLastTime(c: any): string {
    const raw =
      c?.messages_max_created_at ||
      c?.messages?.[0]?.created_at ||
      c?.updated_at;
    if (!raw) return '';
    const d = new Date(String(raw).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '';
    const now = new Date();
    const sameDay =
      d.getFullYear() === now.getFullYear() &&
      d.getMonth() === now.getMonth() &&
      d.getDate() === now.getDate();
    if (sameDay) {
      return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
    }
    const days = Math.floor((now.getTime() - d.getTime()) / (1000 * 60 * 60 * 24));
    if (days === 1) return 'أمس';
    if (days < 7) return d.toLocaleDateString('ar-EG', { weekday: 'short' });
    return d.toLocaleDateString('ar-EG', { day: 'numeric', month: 'short' });
  }

  /** يطابق ترتيب الـ API: الأحدث نشاطاً أولاً ثم id تنازلي. */
  private sortCustomerListByRecency(): void {
    if (this.customers.length < 2) {
      return;
    }
    this.customers.sort((a, b) => {
      const diff = this.customerRecencyTs(b) - this.customerRecencyTs(a);
      if (diff !== 0) {
        return diff;
      }
      return (Number(b?.id) || 0) - (Number(a?.id) || 0);
    });
  }

  /** Infinite scroll for the conversation list + debounced sessionStorage write. */
  onListScroll(): void {
    const el = this.listScrollArea?.nativeElement;
    if (!el) {
      return;
    }
    if (this.listScrollSaveTimer) {
      clearTimeout(this.listScrollSaveTimer);
    }
    this.listScrollSaveTimer = setTimeout(() => {
      this.listScrollSaveTimer = null;
      try {
        const payload: ListScrollPersist = {
          scrollTop: el.scrollTop,
          page: this.listPage,
          filtersKey: this.listFiltersStorageKey(),
        };
        sessionStorage.setItem(this.storageListKey, JSON.stringify(payload));
      } catch {
        /* private mode / quota */
      }
    }, 150);

    const nearBottom =
      el.scrollHeight - el.clientHeight - el.scrollTop < 180;
    if (
      nearBottom &&
      !this.listFetchBusy &&
      !this.listLoadingMore &&
      this.listPage < this.listLastPage
    ) {
      this.loadMoreCustomers();
    }
  }

  private finishListLoadSequence(): void {
    if (
      this.listPage < this.listRestoreTargetPage &&
      this.listPage < this.listLastPage
    ) {
      this.loadCustomers(false);
      return;
    }
    this.listRestoreTargetPage = 1;
    const top = this.pendingListScrollTop;
    this.pendingListScrollTop = null;
    requestAnimationFrame(() => {
      const el = this.listScrollArea?.nativeElement;
      if (el && top != null && top > 0) {
        el.scrollTop = Math.min(top, Math.max(0, el.scrollHeight - el.clientHeight));
      }
    });
  }

  private readListPersistence(): ListScrollPersist | null {
    try {
      const raw = sessionStorage.getItem(this.storageListKey);
      if (!raw) {
        return null;
      }
      return JSON.parse(raw) as ListScrollPersist;
    } catch {
      return null;
    }
  }

  private listFiltersStorageKey(): string {
    return JSON.stringify({
      ...this.listFilterForm.value,
      archive: this.listArchiveFilter,
    });
  }

  /** Merge first page from API so inbound messages reorder chats without dropping deep pages. */
  private refreshCustomerListFromServer(): void {
    if (this.listFetchBusy || this.listLoading) {
      return;
    }
    const params = { ...this.listQueryParams(1), archive: 'all' };
    this.whatsappService.getCustomers(params).subscribe({
      next: (res: any) => {
        if (!res.success) {
          return;
        }
        const page = res.data;
        const rows = page?.data ? page.data : Array.isArray(page) ? page : [];
        const serverRows: any[] = (Array.isArray(rows) ? rows : []).map((c) =>
          this.normalizeCustomerRow(c)
        );
        this.mergeCustomersFromServer(serverRows);
        this.maybeRefreshOpenThreadFromPoll(serverRows);
        this.applyArchiveCounts(res?.archive_counts);
        this.cdr.markForCheck();
      },
      error: () => {
        /* ignore */
      },
    });
  }

  /** If the server shows a newer last message for the open chat, sync the thread. */
  private maybeRefreshOpenThreadFromPoll(serverRows: any[]): void {
    const cid = this.customerId;
    if (!cid || this.loading || this.loadingMore || this.sending) {
      return;
    }
    const row = serverRows.find((c) => c.id === cid);
    if (row) {
      this.applyConversationArchiveFromServer(row);
    }
    if (!this.messages.length) {
      return;
    }
    const el = this.scrollArea?.nativeElement;
    const nearBottom = el
      ? el.scrollHeight - el.scrollTop - el.clientHeight < 120
      : true;
    if (!nearBottom) {
      return;
    }
    const serverLatestId = Number(row?.last_message_id || row?.messages?.[0]?.id);
    const localNewest = this.messages[this.messages.length - 1];
    const localNewestId = Number(localNewest?.id) || 0;
    const hasNewerId =
      Number.isFinite(serverLatestId) && serverLatestId > localNewestId;
    const hasNewerTime =
      !!row && this.customerRecencyTs(row) > this.customerRecencyTs({
        messages_max_created_at: localNewest?.created_at,
      });
    if (hasNewerId || hasNewerTime || !row) {
      this.syncNewMessagesFromPoll();
    }
  }

  /** يضيف الرسائل الجديدة فقط دون مسح الشاشة أو غطاء التحميل العام. */
  private syncNewMessagesFromPoll(): void {
    const cid = this.customerId;
    if (!cid || this.loading || this.loadingMore || this.sending) {
      return;
    }
    this.messageService.getMessages(cid, this.filters()).subscribe({
      next: (res) => {
        if (!res.success) {
          return;
        }
        if (res.conversation) {
          this.applyConversationArchiveFromServer(res.conversation);
        }
        if (!res.data?.length) {
          return;
        }
        const localNewestId = this.messages[this.messages.length - 1]?.id ?? 0;
        const existing = new Set(this.messages.map((m) => m.id));
        const newer = (res.data || [])
          .map((m) => this.decorate(m))
          .filter((m) => m.id > localNewestId && !existing.has(m.id))
          .sort((a, b) => a.id - b.id);
        if (!newer.length) {
          return;
        }
        this.messages = [...this.messages, ...newer];
        if (newer.some((m) => m.direction === 'received')) {
          this.restoreConversationToInbox(cid);
        }
        setTimeout(() => this.scrollToBottom(), 30);
        this.preloadVisibleMedia();
        this.cdr.markForCheck();
      },
      error: () => {
        /* ignore */
      },
    });
  }

  private customerRecencyTs(c: any): number {
    const raw =
      c?.messages_max_created_at ||
      c?.messages?.[0]?.created_at ||
      c?.updated_at;
    if (!raw) {
      return 0;
    }
    const n = new Date(String(raw).replace(' ', 'T')).getTime();
    return Number.isNaN(n) ? 0 : n;
  }

  private mergeCustomersFromServer(serverRows: any[]): void {
    if (!serverRows.length) {
      return;
    }
    const byId = new Map<number, any>();
    for (const c of this.customers) {
      byId.set(c.id, { ...c });
    }
    for (const s of serverRows) {
      const prev = byId.get(s.id);
      byId.set(s.id, { ...(prev || {}), ...s });
    }
    const merged = Array.from(byId.values()).filter((c) => {
      if (
        this.customerId &&
        c.id === this.customerId &&
        this.isCustomerArchived(c)
      ) {
        return true;
      }
      return this.matchesListArchiveFilter(c);
    });
    merged.sort((a, b) => this.customerRecencyTs(b) - this.customerRecencyTs(a));

    const el = this.listScrollArea?.nativeElement;
    const prevTop = el?.scrollTop ?? 0;
    this.customers = merged;
    requestAnimationFrame(() => {
      if (!el) {
        return;
      }
      const maxTop = Math.max(0, el.scrollHeight - el.clientHeight);
      el.scrollTop = Math.min(prevTop, maxTop);
    });
  }

  /** After an agent reply the backend archives the chat; drop it from the inbox immediately. */
  private markConversationArchivedAfterSend(
    customerId: number,
    archivedAt: string | null
  ): void {
    const wasArchived = this.isCustomerArchived(
      this.customers.find((c) => c.id === customerId) || this.customer
    );
    this.applyLocalArchiveState(customerId, true, archivedAt);
    if (!wasArchived && this.listArchiveFilter === 'inbox') {
      this.listInboxCount = Math.max(0, this.listInboxCount - 1);
      this.listArchivedCount += 1;
    }
  }

  findCustomerByPhone(phone: string): void {
    this.loading = true;
    this.whatsappService.findCustomerByPhone(phone).subscribe({
      next: (res: any) => {
        this.loading = false;
        if (res.success && res.customer) {
          this.selectCustomer(res.customer);
          return;
        }
        this.loadCustomers();
        Swal.fire({
          icon: 'info',
          title: 'تنبيه',
          text: 'لم يتم العثور على العميل، يرجى اختياره من القائمة',
        });
      },
      error: () => {
        this.loading = false;
        this.loadCustomers();
      },
    });
  }

  selectCustomer(customer: any): void {
    this.skipRouteListReload = true;
    this.customer = customer;
    this.customerId = customer.id;
    this.threadFiltersOpen = false;
    this.suggestionsOpen = false;
    this.router.navigate(['/dashboard/whatsapp/chat', customer.id], {
      replaceUrl: true,
    });
  }

  toggleThreadFilters(): void {
    this.threadFiltersOpen = !this.threadFiltersOpen;
  }

  toggleSuggestions(): void {
    this.suggestionsOpen = !this.suggestionsOpen;
  }

  /** موبايل: العودة لقائمة المحادثات دون تكديس العمودين */
  backToConversations(): void {
    this.threadFiltersOpen = false;
    this.suggestionsOpen = false;
    this.router.navigate(['/dashboard/whatsapp/chat']);
  }

  // ────────────────────────────────────────────────────────── templates

  loadTemplates(): void {
    this.whatsappService.getTemplates().subscribe({
      next: (res: any) => {
        if (res.success && Array.isArray(res.data)) {
          this.templates = res.data;
        }
      },
      error: () => {
        this.templates = [];
      },
    });
  }

  loadChatMetaTemplates(): void {
    this.whatsappService.getMetaTemplates().subscribe({
      next: (res: any) => {
        const data = res?.data ?? res;
        const raw = Array.isArray(data) ? data : [];
        this.chatMetaTemplates = raw.filter((t: any) => t?.show_in_chat);
      },
      error: () => {
        this.chatMetaTemplates = [];
      },
    });
  }

  sendChatMetaTemplate(tpl: any): void {
    const customerId = this.customerId;
    if (!tpl?.name || !customerId || !this.customer?.phone || this.sendingMetaTemplate) {
      return;
    }
    this.sendingMetaTemplate = true;
    this.whatsappService
      .sendMetaTemplateFromOrder({
        customer_id: customerId,
        template_name: tpl.name,
        language_code: tpl.api_language_code || tpl.language || 'ar',
      })
      .subscribe({
        next: (res: any) => {
          this.sendingMetaTemplate = false;
          if (!res?.success) {
            Swal.fire({
              icon: 'error',
              title: 'خطأ',
              text: res?.error || 'فشل إرسال القالب',
            });
            return;
          }
          const held = String(res.message_status || '').toLowerCase() === 'held_for_quality_assessment';
          if (held) {
            Swal.fire({
              icon: 'warning',
              title: 'واتساب أوقف الرسالة للمراجعة',
              text: 'القالب تسويقي: واتساب قبلها ولم يسلّمها بعد. راجع حالة القالب في مدير أعمال ميتا.',
            });
          }
          if (res.data) {
            const appended: MessageView = this.decorate({
              id: res.data.id,
              message: res.data.content ?? `📋 قالب: ${tpl.ui_label || tpl.name}`,
              type: res.data.type ?? 'text',
              direction: 'sent',
              status: res.data.status,
              media_url: null,
              media_mime_type: null,
              media_filename: null,
              media_caption: null,
              sender: res.data.sender
                ? { id: res.data.sender.id, name: res.data.sender.name }
                : null,
              created_at:
                res.data.created_at || new Date().toISOString().slice(0, 19).replace('T', ' '),
            });
            this.messages = [...this.messages, appended];
            this.markConversationArchivedAfterSend(
              customerId,
              res.conversation?.whatsapp_archived_at || appended.created_at
            );
            setTimeout(() => this.scrollToBottom(), 30);
          } else {
            this.bootstrapConversation();
          }
        },
        error: (err) => {
          this.sendingMetaTemplate = false;
          Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: err.error?.error || 'حدث خطأ أثناء إرسال القالب',
          });
        },
      });
  }

  applySuggestion(text: string): void {
    if (!text) return;
    this.messageForm.patchValue({ message: text });
    this.messageForm.get('message')?.markAsTouched();
  }

  // ────────────────────────────────────────────────────────── messages

  /** Reset state and load the newest page for the active conversation. */
  private bootstrapConversation(): void {
    if (!this.customerId) return;

    if (
      this.lastBootstrappedCustomerId != null &&
      this.lastBootstrappedCustomerId !== this.customerId &&
      this.messages.length
    ) {
      this.persistThreadState(this.lastBootstrappedCustomerId);
    }
    this.lastBootstrappedCustomerId = this.customerId;

    const restoreSnap = this.readThreadState(this.customerId);
    const canRestore =
      !!restoreSnap &&
      restoreSnap.filtersKey === this.threadFiltersStorageKey();

    this.messages = [];
    this.cursor = null;
    this.hasMore = false;
    this.expanded.clear();
    this.resetOrdersPanel();
    this.messageService.releaseCache();
    this.loading = true;

    this.messageService.getMessages(this.customerId, this.filters()).subscribe({
      next: (res) => {
        this.loading = false;
        if (!res.success) {
          this.cdr.markForCheck();
          return;
        }

        if (res.conversation) {
          this.customer = { ...(this.customer ?? {}), ...res.conversation };
          this.ensureActiveCustomerInList();
          this.applyConversationArchiveFromServer(res.conversation);
        }
        this.messages = (res.data || []).map((m) => this.decorate(m));
        this.cursor = res.next_cursor;
        this.hasMore = res.has_more;

        if (canRestore && restoreSnap) {
          this.scheduleThreadScrollRestore(restoreSnap);
        } else {
          setTimeout(() => this.scrollToBottom(), 60);
        }
        this.preloadVisibleMedia();
        this.cdr.markForCheck();
      },
      error: () => {
        this.loading = false;
        this.cdr.markForCheck();
      },
    });
  }

  /** Infinite-scroll-at-top: load older messages and keep scroll anchored. */
  loadMore(): void {
    if (!this.customerId || this.loadingMore || !this.hasMore || !this.cursor) {
      return;
    }

    const container = this.scrollArea?.nativeElement;
    const anchorOffset = container
      ? container.scrollHeight - container.scrollTop
      : 0;

    this.loadingMore = true;

    this.messageService
      .getMessages(this.customerId, { ...this.filters(), cursor: this.cursor })
      .subscribe({
        next: (res) => {
          this.loadingMore = false;
          if (!res.success) return;

          const older = (res.data || []).map((m) => this.decorate(m));
          this.messages = [...older, ...this.messages];
          this.cursor = res.next_cursor;
          this.hasMore = res.has_more;

          // Preserve scroll position so the focused message stays in place.
          setTimeout(() => {
            if (container) {
              container.scrollTop = container.scrollHeight - anchorOffset;
            }
          }, 0);
          this.preloadVisibleMedia();
        },
        error: () => {
          this.loadingMore = false;
        },
      });
  }

  private threadFiltersStorageKey(): string {
    return JSON.stringify(this.filters());
  }

  private persistThreadState(cid: number | null): void {
    if (!cid || !this.messages.length) {
      return;
    }
    const el = this.scrollArea?.nativeElement;
    const oldestId = this.messages[0].id;
    const newestId = this.messages[this.messages.length - 1].id;
    const snap: ThreadScrollPersist = {
      filtersKey: this.threadFiltersStorageKey(),
      oldestId,
      newestId,
      scrollTop: el?.scrollTop ?? 0,
      scrollHeight: el?.scrollHeight ?? 0,
      clientHeight: el?.clientHeight ?? 0,
      nearBottom: el
        ? el.scrollHeight - el.scrollTop - el.clientHeight < 100
        : true,
    };
    try {
      sessionStorage.setItem(
        this.storageThreadPrefix + String(cid),
        JSON.stringify(snap)
      );
    } catch {
      /* noop */
    }
  }

  private readThreadState(cid: number): ThreadScrollPersist | null {
    try {
      const raw = sessionStorage.getItem(this.storageThreadPrefix + String(cid));
      if (!raw) {
        return null;
      }
      return JSON.parse(raw) as ThreadScrollPersist;
    } catch {
      return null;
    }
  }

  private scheduleThreadScrollPersist(): void {
    if (!this.customerId || !this.messages.length) {
      return;
    }
    if (this.threadScrollSaveTimer) {
      clearTimeout(this.threadScrollSaveTimer);
    }
    this.threadScrollSaveTimer = setTimeout(() => {
      this.threadScrollSaveTimer = null;
      if (this.customerId) {
        this.persistThreadState(this.customerId);
      }
    }, 220);
  }

  private scheduleThreadScrollRestore(snap: ThreadScrollPersist): void {
    const latestId = this.messages[this.messages.length - 1]?.id;
    const hasNew =
      snap.newestId != null &&
      latestId != null &&
      latestId > snap.newestId;
    if (hasNew && snap.nearBottom) {
      setTimeout(() => this.scrollToBottom(), 60);
      return;
    }

    const needOlder =
      snap.oldestId != null &&
      this.messages.length > 0 &&
      this.messages[0].id > snap.oldestId;

    const finish = () => {
      setTimeout(() => this.applyThreadScrollSnap(snap), 0);
    };

    if (needOlder) {
      this.loadOlderUntil(snap.oldestId, finish);
    } else {
      finish();
    }
  }

  private loadOlderUntil(targetOldestId: number, done: () => void): void {
    if (!this.customerId || !this.hasMore || !this.cursor) {
      done();
      return;
    }
    if (!this.messages.length || this.messages[0].id <= targetOldestId) {
      done();
      return;
    }

    const container = this.scrollArea?.nativeElement;
    const anchorOffset = container
      ? container.scrollHeight - container.scrollTop
      : 0;

    this.loadingMore = true;
    this.messageService
      .getMessages(this.customerId, { ...this.filters(), cursor: this.cursor })
      .subscribe({
        next: (res) => {
          this.loadingMore = false;
          if (!res.success) {
            done();
            return;
          }
          const older = (res.data || []).map((m) => this.decorate(m));
          this.messages = [...older, ...this.messages];
          this.cursor = res.next_cursor;
          this.hasMore = res.has_more;
          setTimeout(() => {
            if (container) {
              container.scrollTop = container.scrollHeight - anchorOffset;
            }
            this.loadOlderUntil(targetOldestId, done);
          }, 0);
          this.preloadVisibleMedia();
        },
        error: () => {
          this.loadingMore = false;
          done();
        },
      });
  }

  private applyThreadScrollSnap(snap: ThreadScrollPersist): void {
    const el = this.scrollArea?.nativeElement;
    if (!el) {
      return;
    }
    const maxTop = Math.max(0, el.scrollHeight - el.clientHeight);
    if (snap.nearBottom) {
      el.scrollTop = maxTop;
      return;
    }
    el.scrollTop = Math.min(Math.max(0, snap.scrollTop), maxTop);
  }

  onScroll(event: Event): void {
    const el = event.target as HTMLDivElement;
    if (el.scrollTop < 80 && this.hasMore && !this.loadingMore) {
      this.loadMore();
    }
    this.scheduleThreadScrollPersist();
  }

  private filters(): MessagesFilters {
    const raw = this.filtersForm.value;
    return {
      limit: this.PAGE_SIZE,
      search: (raw.search || '').trim() || undefined,
      from_date: raw.from_date || undefined,
      to_date: raw.to_date || undefined,
    };
  }

  // ────────────────────────────────────────────────────────── sending

  sendMessage(): void {
    const customerId = this.customerId;
    if (!this.messageForm.valid || !customerId || !this.customer?.phone) {
      return;
    }
    const text = (this.messageForm.value.message || '').trim();
    if (!text) return;

    this.sending = true;
    this.whatsappService
      .sendMessage({
        customer_phone: this.customer.phone,
        message: text,
      })
      .subscribe({
        next: (res: any) => {
          this.sending = false;
          if (res.success) {
            this.messageForm.reset();
            // Append the new outbound message optimistically.
            if (res.data) {
              const appended: MessageView = this.decorate({
                id: res.data.id,
                message: res.data.content ?? text,
                type: res.data.type ?? 'text',
                direction: 'sent',
                status: res.data.status,
                media_url: null,
                media_mime_type: null,
                media_filename: null,
                media_caption: null,
                sender: res.data.sender
                  ? { id: res.data.sender.id, name: res.data.sender.name }
                  : null,
                created_at:
                  res.data.created_at || new Date().toISOString().slice(0, 19).replace('T', ' '),
              });
              this.messages = [...this.messages, appended];
              this.markConversationArchivedAfterSend(
                customerId,
                res.conversation?.whatsapp_archived_at || appended.created_at
              );
              setTimeout(() => this.scrollToBottom(), 30);
            } else {
              this.bootstrapConversation();
              this.markConversationArchivedAfterSend(
                customerId,
                res.conversation?.whatsapp_archived_at ||
                  new Date().toISOString().slice(0, 19).replace('T', ' ')
              );
            }
          } else {
            Swal.fire({
              icon: 'error',
              title: 'خطأ',
              text: res.error || 'فشل إرسال الرسالة',
            });
          }
        },
        error: (err) => {
          this.sending = false;
          Swal.fire({
            icon: 'error',
            title: 'خطأ',
            text: err.error?.error || 'حدث خطأ أثناء الإرسال',
          });
        },
      });
  }

  // ────────────────────────────────────────────────────────── media

  private decorate(m: ChatMessage): MessageView {
    return { ...m, _mediaObjectUrl: null, _mediaLoading: false };
  }

  isImage(m: MessageView): boolean {
    return m.type === 'image' || m.type === 'sticker';
  }

  isMedia(m: MessageView): boolean {
    return !!m.media_url;
  }

  /** Fetch (cached) blob URL for a media message. Called by [src] bindings. */
  ensureMedia(m: MessageView): void {
    if (!m.media_url || m._mediaObjectUrl || m._mediaLoading) return;
    m._mediaLoading = true;
    this.messageService.getMediaObjectUrl(m.id).subscribe({
      next: (url) => {
        m._mediaObjectUrl = url || null;
        m._mediaLoading = false;
      },
      error: () => {
        m._mediaLoading = false;
      },
    });
  }

  /** Eagerly load media for the currently visible (latest) messages. */
  private preloadVisibleMedia(): void {
    this.messages
      .slice(-12)
      .filter((m) => this.isImage(m))
      .forEach((m) => this.ensureMedia(m));
  }

  openPreview(m: MessageView): void {
    if (!m.media_url) return;
    const kind: PreviewKind =
      m.type === 'image' || m.type === 'sticker'
        ? 'image'
        : m.type === 'video'
        ? 'video'
        : m.type === 'audio'
        ? 'audio'
        : 'other';

    this.ensureMedia(m);
    this.preview = {
      open: true,
      url: m._mediaObjectUrl || '',
      kind,
      msg: m,
    };

    if (!m._mediaObjectUrl) {
      this.messageService.getMediaObjectUrl(m.id).subscribe((url) => {
        if (this.preview.open && this.preview.msg?.id === m.id) {
          this.preview.url = url;
        }
      });
    }
  }

  closePreview(): void {
    this.preview = { open: false, url: '', kind: 'image', msg: null };
  }

  downloadMedia(m: MessageView, event?: Event): void {
    event?.stopPropagation();
    if (!m.media_url) return;
    this.messageService.downloadMedia(
      m.id,
      m.media_filename || undefined
    );
  }

  // ────────────────────────────────────────────────────────── UI helpers

  trackByMessage = (_: number, m: MessageView) => m.id;

  scrollToBottom(): void {
    const el = this.scrollArea?.nativeElement;
    if (el) {
      el.scrollTop = el.scrollHeight;
    }
  }

  openWhatsApp(customer: any): void {
    const phone = (customer.phone || '').replace('+', '');
    window.open(`https://wa.me/${phone}`, '_blank');
  }

  messageOrderId(m: MessageView | null | undefined): number | null {
    if (!m) {
      return null;
    }
    const fromField = Number(m.order_id);
    if (Number.isFinite(fromField) && fromField > 0) {
      return fromField;
    }
    const text = String(m.message || '');
    const match = text.match(/(?:Order|طلب)\s*#?\s*(\d{3,})/i);
    if (!match) {
      return null;
    }
    const parsed = Number(match[1]);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
  }

  openOrderPanel(orderId?: number | null, event?: Event): void {
    event?.stopPropagation();
    if (!this.customerId) {
      return;
    }
    this.ordersPanelOpen = true;
    this.ordersPanelError = '';
    this.focusedOrderId = orderId || null;
    if (orderId) {
      this.expandedOrderIds = new Set([orderId]);
    }
    this.loadConversationOrders(orderId || undefined);
  }

  closeOrderPanel(): void {
    this.ordersPanelOpen = false;
    this.focusedOrderId = null;
  }

  showAllConversationOrders(): void {
    this.focusedOrderId = null;
    this.ordersPanelError = '';
  }

  toggleOrderExpand(orderId: number, event?: Event): void {
    event?.stopPropagation();
    if (this.expandedOrderIds.has(orderId)) {
      this.expandedOrderIds.delete(orderId);
    } else {
      this.expandedOrderIds.add(orderId);
    }
  }

  isOrderExpanded(orderId: number): boolean {
    return this.expandedOrderIds.has(orderId);
  }

  openOrderDetails(orderId: number, event?: Event): void {
    event?.stopPropagation();
    const tree = this.router.createUrlTree(['/dashboard/shipping/orderdetails', orderId]);
    const isMobile = typeof window !== 'undefined' && window.innerWidth < 768;
    if (isMobile) {
      this.router.navigateByUrl(tree);
      return;
    }
    window.open(this.router.serializeUrl(tree), '_blank');
  }

  formatMoney(value: number | string | null | undefined): string {
    const n = Number(value);
    if (!Number.isFinite(n)) {
      return '0';
    }
    return n.toLocaleString('en-US', { maximumFractionDigits: 2 });
  }

  displayedOrders(): ConversationOrder[] {
    if (this.focusedOrderId) {
      const focused = this.conversationOrders.filter(
        (o) => o.id === this.focusedOrderId
      );
      if (focused.length) {
        return focused;
      }
    }
    return this.conversationOrders;
  }

  private loadConversationOrders(includeId?: number): void {
    const cid = this.customerId;
    if (!cid) {
      return;
    }
    const cacheReady = this.ordersCacheCustomerId === cid;
    if (cacheReady && (!includeId || this.conversationOrders.some((o) => o.id === includeId))) {
      if (includeId) {
        this.expandedOrderIds.add(includeId);
      }
      return;
    }

    this.ordersPanelLoading = true;
    this.messageService.getConversationOrders(cid, includeId).subscribe({
      next: (res) => {
        this.ordersPanelLoading = false;
        if (!res?.success) {
          this.ordersPanelError = res?.error || 'تعذر تحميل طلبات العميل.';
          return;
        }
        this.conversationOrders = Array.isArray(res.data) ? res.data : [];
        this.ordersCacheCustomerId = cid;
        const focus = includeId || this.focusedOrderId;
        if (focus && this.conversationOrders.some((o) => o.id === focus)) {
          this.expandedOrderIds.add(focus);
        } else if (!this.expandedOrderIds.size && this.conversationOrders[0]) {
          this.expandedOrderIds.add(this.conversationOrders[0].id);
        }
        if (focus && !this.conversationOrders.some((o) => o.id === focus)) {
          this.ordersPanelError = `لم يتم العثور على الطلب #${focus} أو لا توجد صلاحية لعرضه.`;
        }
        this.cdr.markForCheck();
      },
      error: () => {
        this.ordersPanelLoading = false;
        this.ordersPanelError = 'تعذر تحميل طلبات العميل. حاول مرة أخرى.';
        this.cdr.markForCheck();
      },
    });
  }

  private resetOrdersPanel(): void {
    this.ordersPanelOpen = false;
    this.ordersPanelLoading = false;
    this.ordersPanelError = '';
    this.conversationOrders = [];
    this.focusedOrderId = null;
    this.expandedOrderIds.clear();
    this.ordersCacheCustomerId = null;
  }

  formatBubbleContent(content: string | null | undefined): string {
    if (!content) return '';
    const t = content.trim();
    if (t === '[Button Message]' || t === '[Interactive Message]') {
      return '🔘 رد تفاعلي (زر واتساب)';
    }
    return content;
  }

  formatDate(date: string): string {
    if (!date) return '';
    const d = new Date(date.replace(' ', 'T'));
    const now = new Date();
    const days = Math.floor(
      (now.getTime() - d.getTime()) / (1000 * 60 * 60 * 24)
    );
    const time = d.toLocaleTimeString('ar-EG', {
      hour: '2-digit',
      minute: '2-digit',
    });
    if (days === 0) return time;
    if (days === 1) return 'أمس ' + time;
    if (days < 7) {
      return d.toLocaleDateString('ar-EG', { weekday: 'short' }) + ' ' + time;
    }
    return (
      d.toLocaleDateString('ar-EG', { day: 'numeric', month: 'short' }) +
      ' ' +
      time
    );
  }

  // Read-more toggle for very long text bubbles.
  shouldShowReadMoreToggle(m: MessageView): boolean {
    const text = this.formatBubbleContent(m?.message) || '';
    return text.length > 220;
  }

  isMessageBodyExpanded(m: MessageView): boolean {
    return this.expanded.has(`id:${m.id}`);
  }

  toggleMessageBodyExpand(m: MessageView, event?: Event): void {
    event?.stopPropagation();
    const key = `id:${m.id}`;
    if (this.expanded.has(key)) {
      this.expanded.delete(key);
    } else {
      this.expanded.add(key);
    }
  }

  clearFilters(): void {
    this.filtersForm.setValue({ search: '', from_date: '', to_date: '' });
  }

  clearListSearch(): void {
    this.listSearch.setValue('');
  }

  get hasListFilters(): boolean {
    const lf = this.listFilterForm.value;
    return !!(
      (this.listSearch.value || '').trim() ||
      lf.from_date ||
      lf.to_date
    );
  }

  /** يمسح البحث النصي + نطاق التاريخ ويعيد تحميل القائمة. */
  clearListFiltersAll(): void {
    this.listSearch.setValue('', { emitEvent: false });
    this.listFilterForm.setValue(
      { from_date: '', to_date: '' },
      { emitEvent: false }
    );
    this.loadCustomers();
  }

  /** العملاء الظاهرون في العمود بعد تطبيق البحث (محلي) */
  get filteredCustomers(): any[] {
    const q = (this.listSearch.value || '').toString().trim();
    if (!q) {
      return this.customers;
    }
    const lower = q.toLowerCase();
    const qDigits = q.replace(/\D/g, '');

    const match = (c: any): boolean => {
      const name = (c.name || '').toString().toLowerCase();
      const phoneRaw = (c.phone || '').toString();
      const pDigits = phoneRaw.replace(/\D/g, '');
      const nameMatch = name.includes(lower);
      const phoneMatch =
        qDigits.length > 0
          ? pDigits.includes(qDigits)
          : phoneRaw.toLowerCase().includes(lower);
      return nameMatch || phoneMatch;
    };

    const filtered = this.customers.filter(match);

    if (this.customerId) {
      const active = this.customers.find((c) => c.id === this.customerId);
      if (active && !filtered.some((c) => c.id === this.customerId)) {
        return [active, ...filtered];
      }
    }
    return filtered;
  }
}
