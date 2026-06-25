import { Component, OnInit } from '@angular/core';
import { TreeAccountService } from '../services/tree-account.service';
import { AccountingReportService } from '../services/accounting-report.service';
import { TreeAccount, TreeAccountAuditEntry } from '../interfaces/tree-account.interface';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-accounting-tree',
  templateUrl: './accounting-tree.component.html',
  styleUrls: ['./accounting-tree.component.css']
})
export class AccountingTreeComponent implements OnInit {
  accounts: TreeAccount[] = [];
  treeData: TreeAccount[] = [];
  /** شجرة العرض: كاملة أو بعد تطبيق البحث */
  displayTree: TreeAccount[] = [];
  searchTerm = '';
  loading = false;
  recalculating = false;
  syncingInventoryGl = false;
  showAddDialog = false;
  showEditDialog = false;
  selectedAccount: TreeAccount | null = null;
  accountAudits: TreeAccountAuditEntry[] = [];
  loadingAccountMeta = false;
  expandedNodes: Set<number> = new Set();

  accountTypes = [
    { value: 'asset', label: 'أصول' },
    { value: 'liability', label: 'خصوم' },
    { value: 'equity', label: 'حقوق ملكية' },
    { value: 'revenue', label: 'إيرادات' },
    { value: 'expense', label: 'مصروفات' },
    { value: 'settlement', label: 'تسوية' }
  ];

  newAccount: TreeAccount = {
    name: '',
    type: 'asset',
    balance: 0,
    debit_balance: 0,
    credit_balance: 0,
    is_trading_account: false
  };

  constructor(
    private treeAccountService: TreeAccountService,
    private accountingReportService: AccountingReportService
  ) { }

  ngOnInit(): void {
    this.loadAccounts();
  }

  loadAccounts(): void {
    this.loading = true;
    // Prefer the fully nested tree endpoint; fall back to the generic list if needed
    this.treeAccountService.getTree().subscribe({
      next: (data: any) => {
        // Reporting endpoint returns plain array of accounts (already nested)
        this.accounts = Array.isArray(data) ? data : (data?.data ?? []);
        this.buildTree();
        this.loading = false;
      },
      error: () => {
        // Fallback to the standard endpoint
        this.treeAccountService.getAll().subscribe({
          next: (response) => {
            if (response.success && response.data) {
              this.accounts = Array.isArray(response.data) ? response.data : [];
            } else {
              this.accounts = [];
            }
            this.buildTree();
            this.loading = false;
          },
          error: (error) => {
            console.error('Error loading accounts:', error);
            this.accounts = [];
            this.treeData = [];
            this.displayTree = [];
            this.loading = false;
          }
        });
      }
    });
  }

  buildTree(): void {
    // Check if accounts already have nested children structure
    const hasNestedChildren = this.accounts.some(acc => acc.children && acc.children.length > 0);
    
    if (hasNestedChildren) {
      // Use the nested structure directly
      this.treeData = this.accounts.filter(acc => !acc.parent_id);
      this.sortTreeByCode(this.treeData);
    } else {
      // Build tree from flat list
      const accountMap = new Map<number, TreeAccount>();
      const rootAccounts: TreeAccount[] = [];

      // Create a map of all accounts
      this.accounts.forEach(account => {
        accountMap.set(account.id!, { ...account, children: [] });
      });

      // Build the tree structure
      this.accounts.forEach(account => {
        const accountNode = accountMap.get(account.id!);
        if (accountNode) {
          if (account.parent_id) {
            const parent = accountMap.get(account.parent_id);
            if (parent && parent.children) {
              parent.children!.push(accountNode);
            }
          } else {
            rootAccounts.push(accountNode);
          }
        }
      });

      this.treeData = rootAccounts;
      this.sortTreeByCode(this.treeData);
    }
    this.applySearchFilter();
  }

  clearSearch(): void {
    this.searchTerm = '';
    this.applySearchFilter();
  }

  /** هل يطابق الحساب نص البحث (كود أو اسم عربي/إنجليزي) */
  isSearchMatch(node: TreeAccount): boolean {
    const q = this.searchTerm.trim().toLowerCase();
    if (!q) {
      return false;
    }
    return this.nodeMatchesTerm(node, q);
  }

  private nodeMatchesTerm(node: TreeAccount, lower: string): boolean {
    const code = String(node.code ?? '');
    if (code.toLowerCase().includes(lower)) {
      return true;
    }
    if ((node.name || '').toLowerCase().includes(lower)) {
      return true;
    }
    if ((node.name_en || '').toLowerCase().includes(lower)) {
      return true;
    }
    return false;
  }

  /**
   * يبقي الفروع التي فيها تطابق (اسم/كود) أو فرع يحتوي تطابقاً.
   * إذا طابق الحساب الأب دون أبناء مطابقين، تُعرض الأبناء كاملة لسياق واضح.
   */
  private filterTree(nodes: TreeAccount[], q: string): TreeAccount[] {
    const lower = q.trim().toLowerCase();
    if (!lower) {
      return nodes;
    }

    const walk = (list: TreeAccount[]): TreeAccount[] => {
      const out: TreeAccount[] = [];
      for (const node of list) {
        const matchSelf = this.nodeMatchesTerm(node, lower);
        const childFiltered = node.children?.length ? walk(node.children) : [];
        if (matchSelf || childFiltered.length > 0) {
          const children =
            childFiltered.length > 0
              ? childFiltered
              : matchSelf && node.children?.length
                ? [...node.children]
                : [];
          out.push({ ...node, children });
        }
      }
      return out;
    };

    return walk(nodes);
  }

  applySearchFilter(): void {
    const q = this.searchTerm.trim();
    if (!q) {
      this.displayTree = this.treeData;
      return;
    }
    this.displayTree = this.filterTree(this.treeData, q);
    this.expandAllInTree(this.displayTree);
  }

  private expandAllInTree(nodes: TreeAccount[]): void {
    for (const n of nodes) {
      if (n.id != null && n.children && n.children.length > 0) {
        this.expandedNodes.add(n.id);
        this.expandAllInTree(n.children);
      }
    }
  }

  toggleNode(nodeId: number): void {
    if (this.expandedNodes.has(nodeId)) {
      this.expandedNodes.delete(nodeId);
    } else {
      this.expandedNodes.add(nodeId);
    }
  }

  isExpanded(nodeId: number): boolean {
    return this.expandedNodes.has(nodeId);
  }

  openAddDialog(parentId?: number): void {
    this.newAccount = {
      name: '',
      type: 'asset',
      balance: 0,
      debit_balance: 0,
      credit_balance: 0,
      is_trading_account: false,
      parent_id: parentId
    };
    if (parentId) {
      const parent = this.findAccountById(parentId);
      if (parent) {
        this.newAccount.type = parent.type;
      }
    }
    this.showAddDialog = true;
  }

  openEditDialog(account: TreeAccount): void {
    if (!account.id) {
      return;
    }
    this.selectedAccount = { ...account };
    this.accountAudits = [];
    this.showEditDialog = true;
    this.loadingAccountMeta = true;

    this.treeAccountService.getById(account.id).subscribe({
      next: (response) => {
        if (response?.data) {
          this.selectedAccount = response.data;
        }
        this.loadingAccountMeta = false;
      },
      error: () => {
        this.loadingAccountMeta = false;
      }
    });

    this.treeAccountService.getAudits(account.id).subscribe({
      next: (response) => {
        this.accountAudits = Array.isArray(response?.data) ? response.data : [];
      },
      error: () => {
        this.accountAudits = [];
      }
    });
  }

  formatAuditChanges(entry: TreeAccountAuditEntry): string {
    const changes = entry.changes ?? {};
    const labels: Record<string, string> = {
      name: 'الاسم',
      name_en: 'الاسم بالإنجليزية',
      type: 'النوع',
      parent_id: 'الحساب الأب',
      is_trading_account: 'حساب تداول',
      budget_type: 'نوع الموازنة',
      budget_amount: 'مبلغ الموازنة',
      budget_period: 'فترة الموازنة',
      main_account_id: 'الحساب الرئيسي',
      account_type: 'نوع الحساب',
      detail_type: 'نوع التفصيل',
    };

    return Object.entries(changes)
      .map(([field, value]) => {
        const label = labels[field] ?? field;
        const oldVal = value?.old ?? '—';
        const newVal = value?.new ?? '—';
        return `${label}: ${oldVal} ← ${newVal}`;
      })
      .join(' · ');
  }

  closeDialogs(): void {
    this.showAddDialog = false;
    this.showEditDialog = false;
    this.selectedAccount = null;
    this.accountAudits = [];
    this.loadingAccountMeta = false;
  }

  saveAccount(): void {
    if (!this.newAccount.name || !this.newAccount.type) {
      alert('الرجاء إدخال اسم الحساب ونوعه');
      return;
    }

    this.loading = true;
    const { account_type: _ignoredAt, children: _c, parent: _p, main_account: _m, safes: _s, ...rest } =
      this.newAccount as TreeAccount & { children?: unknown; parent?: unknown; main_account?: unknown; safes?: unknown };
    const payload = {
      ...rest,
      balance: 0,
      debit_balance: 0,
      credit_balance: 0
    };
    this.treeAccountService.create(payload).subscribe({
      next: (response) => {
        this.loadAccounts();
        this.closeDialogs();
        this.loading = false;
      },
      error: (error) => {
        console.error('Error creating account:', error);
        alert(this.getHttpErrorMessage(error, 'حدث خطأ أثناء إضافة الحساب'));
        this.loading = false;
      }
    });
  }

  updateAccount(): void {
    if (!this.selectedAccount) return;

    if (!this.selectedAccount.name || !this.selectedAccount.type) {
      alert('الرجاء إدخال اسم الحساب ونوعه');
      return;
    }

    this.loading = true;
    const a = this.selectedAccount;
    const payload: Partial<TreeAccount> = {
      name: a.name,
      name_en: a.name_en,
      type: a.type,
      is_trading_account: a.is_trading_account,
      budget_type: a.budget_type,
      budget_amount: a.budget_amount,
      budget_period: a.budget_period
    };
    this.treeAccountService.update(a.id!, payload as TreeAccount).subscribe({
      next: (response) => {
        this.loadAccounts();
        this.closeDialogs();
        this.loading = false;
      },
      error: (error) => {
        console.error('Error updating account:', error);
        alert(this.getHttpErrorMessage(error, 'حدث خطأ أثناء تحديث الحساب'));
        this.loading = false;
      }
    });
  }

  deleteAccount(account: TreeAccount): void {
    if (!confirm(`هل أنت متأكد من حذف الحساب "${account.name}" وجميع الحسابات الفرعية؟`)) {
      return;
    }

    this.loading = true;
    this.treeAccountService.delete(account.id!).subscribe({
      next: (response) => {
        this.loadAccounts();
        this.loading = false;
      },
      error: (error) => {
        console.error('Error deleting account:', error);
        alert(this.getHttpErrorMessage(error, 'حدث خطأ أثناء حذف الحساب'));
        this.loading = false;
      }
    });
  }

  findAccountById(id: number): TreeAccount | null {
    return this.findInTree(this.accounts, id);
  }

  private findInTree(nodes: TreeAccount[], id: number): TreeAccount | null {
    for (const node of nodes) {
      if (node.id === id) {
        return node;
      }
      if (node.children?.length) {
        const found = this.findInTree(node.children, id);
        if (found) {
          return found;
        }
      }
    }
    return null;
  }

  recalculateAllBalances(): void {
    if (this.recalculating) return;
    if (!confirm('هل تريد إعادة حساب جميع أرصدة الشجرة؟ سيتم تحديث كل الحسابات بناءً على القيود.')) return;

    this.recalculating = true;
    this.accountingReportService.recalculateAllHierarchyBalances().subscribe({
      next: (response: any) => {
        this.recalculating = false;
        if (response.success) {
          alert(response.message || 'تم تحديث الأرصدة بنجاح');
          this.loadAccounts();
        } else {
          alert(response.message || 'حدث خطأ أثناء إعادة الحساب');
        }
      },
      error: (err) => {
        this.recalculating = false;
        console.error('Recalculate error:', err);
        alert(err?.error?.message || 'فشل إعادة حساب الأرصدة');
      }
    });
  }

  private escHtml(s: string): string {
    return String(s ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  private formatMoney(n: number): string {
    const x = Number(n ?? 0);
    if (Number.isNaN(x)) {
      return '0.00';
    }
    return x.toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  /**
   * مطابقة أرصدة حسابات المخزون في الشجرة مع التكلفة الفعلية للأصناف في المخازن.
   * يعرض الفروقات أولاً، ثم يُرحّل قيد تسوية واحد بالفرق فقط (آمن وقابل للتكرار).
   */
  openInventoryGlSync(): void {
    if (this.syncingInventoryGl) {
      return;
    }

    this.syncingInventoryGl = true;
    this.accountingReportService.previewInventoryGlSync().subscribe({
      next: (preview: any) => {
        this.syncingInventoryGl = false;
        const rows: any[] = preview?.adjustments ?? [];
        const willPost = !!preview?.will_post;

        let table =
          '<div dir="rtl" style="max-height:320px;overflow:auto;text-align:right;font-size:13px;">';
        if (!willPost || rows.length === 0) {
          table +=
            '<p class="mb-2">لا توجد فروقات تتطلب ترحيلاً؛ أرصدة حسابات المخزون متطابقة بالفعل مع مجموع تكلفة الأصناف في المخازن.</p>';
        } else {
          table +=
            '<table class="table table-sm table-bordered mb-0"><thead><tr>' +
            '<th>الحساب</th><th>الكود</th><th>تكلفة الأصناف</th><th>رصيد القيود</th><th>فرق التسوية</th>' +
            '</tr></thead><tbody>';
          for (const r of rows) {
            const nm = this.escHtml(String(r.account_name ?? ''));
            const adj = Number(r.adjustment ?? 0);
            const adjCls = adj >= 0 ? 'text-success' : 'text-danger';
            table += `<tr><td>${nm}</td><td>${this.escHtml(String(r.account_code ?? ''))}</td>` +
              `<td>${this.formatMoney(Number(r.target_cost_from_items ?? 0))}</td>` +
              `<td>${this.formatMoney(Number(r.book_balance_from_entries ?? 0))}</td>` +
              `<td class="${adjCls}">${this.formatMoney(adj)}</td></tr>`;
          }
          table += '</tbody></table>';
        }
        table += '</div>';

        Swal.fire({
          title: 'تصحيح أرصدة المخزون',
          html:
            '<p class="text-muted small mb-2">يُحسب مجموع تكلفة الأصناف لكل حساب مخزون ويُقارن برصيد القيود، ثم يُنشأ <strong>قيد يومية واحد</strong> بالفرق فقط مع طرف مقابل فروقات الجرد.</p>' +
            table,
          width: '720px',
          showCancelButton: willPost && rows.length > 0,
          confirmButtonText: willPost && rows.length > 0 ? 'ترحيل قيد التسوية' : 'حسناً',
          cancelButtonText: 'إلغاء',
        }).then((res: { isConfirmed: boolean }) => {
          if (!res.isConfirmed || !willPost || rows.length === 0) {
            return;
          }
          this.syncingInventoryGl = true;
          this.accountingReportService.postInventoryGlSync().subscribe({
            next: (out: any) => {
              this.syncingInventoryGl = false;
              if (!out?.success) {
                Swal.fire({ icon: 'error', title: 'لم يتم الترحيل', text: String(out?.message ?? '') });
                return;
              }
              if (!out?.posted) {
                Swal.fire({ icon: 'info', title: out?.message ?? 'لا يوجد ما يُرحَّل' });
                return;
              }

              const jl: any[] = out?.journal_lines ?? [];
              let jt =
                '<div dir="rtl" style="max-height:280px;overflow:auto;font-size:13px;">' +
                `<p class="mb-2"><strong>رقم القيد:</strong> ${this.escHtml(String(out.entry_number ?? ''))} ` +
                `(معرّف ${this.escHtml(String(out.daily_entry_id ?? ''))})</p>` +
                '<table class="table table-sm table-bordered mb-0"><thead><tr>' +
                '<th>الحساب</th><th>مدين</th><th>دائن</th><th>البيان</th>' +
                '</tr></thead><tbody>';
              for (const ln of jl) {
                const nm = this.escHtml(String(ln.account_name ?? ln.account_code ?? ''));
                jt += `<tr><td>${nm}</td><td>${this.formatMoney(Number(ln.debit ?? 0))}</td>` +
                  `<td>${this.formatMoney(Number(ln.credit ?? 0))}</td>` +
                  `<td>${this.escHtml(String(ln.note ?? ''))}</td></tr>`;
              }
              jt += '</tbody></table></div>';

              Swal.fire({
                icon: 'success',
                title: 'تم إنشاء القيد وتحديث الشجرة',
                html: jt,
                width: '720px',
              });
              this.loadAccounts();
            },
            error: (err: any) => {
              this.syncingInventoryGl = false;
              const msg =
                err?.error?.message ||
                (err?.status === 403
                  ? 'ليست لديك صلاحية لترحيل تسوية المخزون.'
                  : 'فشل ترحيل قيد التسوية.');
              Swal.fire({ icon: 'error', title: 'خطأ', text: String(msg) });
            },
          });
        });
      },
      error: (err: any) => {
        this.syncingInventoryGl = false;
        const msg =
          err?.error?.message ||
          (err?.status === 403
            ? 'ليست لديك صلاحية لمعاينة تسوية المخزون.'
            : 'فشل تحميل معاينة التسوية.');
        Swal.fire({ icon: 'error', title: 'خطأ', text: String(msg) });
      },
    });
  }

  getAccountTypeLabel(type: string): string {
    const accountType = this.accountTypes.find(t => t.value === type);
    return accountType ? accountType.label : type;
  }

  /**
   * الرصيد المخزَّن = مدين − دائن. للخصوم/الإيرادات/حقوق الملكية نعرض الرصيد الطبيعي (−balance)
   * ليتوافق مع «ما على الشركة للمورد» مثل رصيد المورد في شاشة الموردين.
   */
  getDisplayBalance(node: TreeAccount): number {
    const b = node.balance ?? 0;
    if (node.type === 'liability' || node.type === 'equity' || node.type === 'revenue' || node.type === 'settlement') {
      return -b;
    }
    return b;
  }

  getIndentLevel(level: number): string {
    return `${level * 20}px`;
  }

  /** يسمح بأبناء حتى عمق معقول؛ الخلفية تدعم المستويات الأعمق عبر default في توليد الكود */
  canAddChild(node: TreeAccount): boolean {
    const lvl = node.level ?? 1;
    return lvl < 50;
  }

  /** رسالة خطأ واضحة من Laravel (validation أو ApiResponse) */
  private getHttpErrorMessage(err: any, fallback: string): string {
    const body = err?.error;
    if (body == null) {
      return typeof err?.message === 'string' ? err.message : fallback;
    }
    if (typeof body === 'string') {
      return body || fallback;
    }
    const errs = body.errors;
    if (errs && typeof errs === 'object') {
      for (const key of Object.keys(errs)) {
        const arr = errs[key];
        if (Array.isArray(arr) && arr.length && arr[0]) {
          return String(arr[0]);
        }
      }
    }
    if (typeof body.message === 'string' && body.message.trim()) {
      return body.message;
    }
    return fallback;
  }

  // Sort recursively by code for better presentation
  private sortTreeByCode(nodes: TreeAccount[]): void {
    nodes.sort((a, b) => (a.code ?? 0) - (b.code ?? 0));
    nodes.forEach(n => {
      if (n.children && n.children.length) {
        this.sortTreeByCode(n.children);
      }
    });
  }
}
