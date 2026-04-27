import {
  Component,
  ElementRef,
  OnDestroy,
  OnInit,
  ViewChild,
} from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { FormControl, FormGroup, Validators } from '@angular/forms';
import { Subscription, combineLatest, timer } from 'rxjs';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';
import Swal from 'sweetalert2';

import { WhatsAppService } from '../../services/whatsapp.service';
import {
  ChatMessage,
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

  messages: MessageView[] = [];
  hasMore = false;
  cursor: string | null = null;

  loading = false;
  /** تحميل عمود «المحادثات» فقط (لا يعطل منطقة الرسائل) */
  listLoading = false;
  loadingMore = false;
  sending = false;

  templates: any[] = [];
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

  private readonly expanded = new Set<string>();
  private subs: Subscription[] = [];

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private whatsappService: WhatsAppService,
    private messageService: MessageService
  ) {}

  // ────────────────────────────────────────────────────────── lifecycle

  ngOnInit(): void {
    this.loadTemplates();

    this.subs.push(
      combineLatest([this.route.paramMap, this.route.queryParamMap]).subscribe(
        ([params, query]) => {
          const cid = params.get('customerId');
          if (!cid && this.threadPersistCustomerId != null) {
            this.persistThreadState(this.threadPersistCustomerId);
            this.threadPersistCustomerId = null;
          }
          if (cid) {
            this.customerId = +cid;
            this.threadPersistCustomerId = this.customerId;
            if (this.customers.length === 0) {
              this.loadCustomers();
            }
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

    // Light polling: reorder list when new inbound messages land (no websocket on this page yet).
    this.subs.push(
      timer(12000, 20000).subscribe(() => {
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
    if (reset) {
      this.listPage = 0;
      const snap = this.readListPersistence();
      const fk = this.listFiltersStorageKey();
      if (snap && snap.filtersKey === fk) {
        this.listRestoreTargetPage = Math.max(1, Math.min(snap.page || 1, 100));
        this.pendingListScrollTop =
          typeof snap.scrollTop === 'number' ? snap.scrollTop : null;
      } else {
        this.listRestoreTargetPage = 1;
        this.pendingListScrollTop = null;
      }
    }

    const pageToFetch = reset ? 1 : this.listPage + 1;
    if (!reset && (pageToFetch > this.listLastPage || this.listLoading)) {
      return;
    }

    const fullPageLoader = !this.customerId && reset;
    if (fullPageLoader) {
      this.loading = true;
    } else {
      this.listLoading = true;
    }
    const lf = this.listFilterForm.value;
    const params: Record<string, string | number> = {
      per_page: 50,
      page: pageToFetch,
    };
    if (lf.from_date) {
      params['from_date'] = lf.from_date;
    }
    if (lf.to_date) {
      params['to_date'] = lf.to_date;
    }
    this.whatsappService.getCustomers(params).subscribe({
      next: (res: any) => {
        if (fullPageLoader) {
          this.loading = false;
        } else {
          this.listLoading = false;
        }
        if (res.success) {
          const page = res.data;
          const rows = page?.data ? page.data : Array.isArray(page) ? page : [];
          const list: any[] = Array.isArray(rows) ? rows : [];
          this.listLastPage = page?.last_page ?? 1;
          this.listPage = page?.current_page ?? pageToFetch;

          if (pageToFetch === 1) {
            if (this.customerId && this.customer && !list.some((c) => c.id === this.customerId)) {
              this.customers = [this.customer, ...list];
            } else {
              this.customers = list;
            }
          } else {
            const seen = new Set(this.customers.map((c) => c.id));
            for (const c of list) {
              if (!seen.has(c.id)) {
                this.customers.push(c);
                seen.add(c.id);
              }
            }
          }

          this.finishListLoadSequence();
        }
      },
      error: () => {
        if (fullPageLoader) {
          this.loading = false;
        } else {
          this.listLoading = false;
        }
      },
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
    if (nearBottom && !this.listLoading && this.listPage < this.listLastPage) {
      this.loadCustomers(false);
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
    return JSON.stringify(this.listFilterForm.value);
  }

  /** Merge first page from API so inbound messages reorder chats without dropping deep pages. */
  private refreshCustomerListFromServer(): void {
    if (this.listLoading) {
      return;
    }
    const lf = this.listFilterForm.value;
    const params: Record<string, string | number> = { per_page: 50, page: 1 };
    if (lf.from_date) {
      params['from_date'] = lf.from_date;
    }
    if (lf.to_date) {
      params['to_date'] = lf.to_date;
    }
    this.whatsappService.getCustomers(params).subscribe({
      next: (res: any) => {
        if (!res.success) {
          return;
        }
        const page = res.data;
        const rows = page?.data ? page.data : Array.isArray(page) ? page : [];
        const serverRows: any[] = Array.isArray(rows) ? rows : [];
        this.mergeCustomersFromServer(serverRows);
        this.maybeRefreshOpenThreadFromPoll(serverRows);
      },
      error: () => {
        /* ignore */
      },
    });
  }

  /** If the server shows a newer last message for the open chat, sync the thread. */
  private maybeRefreshOpenThreadFromPoll(serverRows: any[]): void {
    const cid = this.customerId;
    if (!cid || !this.messages.length || this.loading || this.loadingMore || this.sending) {
      return;
    }
    const el = this.scrollArea?.nativeElement;
    const nearBottom = el
      ? el.scrollHeight - el.scrollTop - el.clientHeight < 120
      : true;
    if (!nearBottom) {
      return;
    }
    const row = serverRows.find((c) => c.id === cid);
    const serverLatestId = row?.messages?.[0]?.id;
    const localNewestId = this.messages[this.messages.length - 1]?.id;
    if (
      typeof serverLatestId === 'number' &&
      typeof localNewestId === 'number' &&
      serverLatestId > localNewestId
    ) {
      this.bootstrapConversation();
    }
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
    const merged = Array.from(byId.values());
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

  /** Move a row to the top after outbound send (instant feedback before the next poll). */
  private promoteCustomerAfterSend(customerId: number, atIso: string): void {
    const idx = this.customers.findIndex((c) => c.id === customerId);
    if (idx < 0) {
      return;
    }
    const [row] = this.customers.splice(idx, 1);
    row.messages_max_created_at = atIso;
    this.customers.unshift(row);
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
    this.customerId = customer.id;
    this.customer = customer;
    this.router.navigate(['/dashboard/whatsapp/chat', customer.id], {
      replaceUrl: true,
    });
    this.bootstrapConversation();
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
    this.messageService.releaseCache();
    this.loading = true;

    this.messageService.getMessages(this.customerId, this.filters()).subscribe({
      next: (res) => {
        this.loading = false;
        if (!res.success) return;

        if (res.conversation) {
          this.customer = { ...(this.customer ?? {}), ...res.conversation };
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
      },
      error: () => {
        this.loading = false;
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
              this.promoteCustomerAfterSend(
                customerId,
                appended.created_at ||
                  new Date().toISOString().slice(0, 19).replace('T', ' ')
              );
              setTimeout(() => this.scrollToBottom(), 30);
            } else {
              this.bootstrapConversation();
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
