import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api_client.dart';
import '../../services/companies_api.dart';
import '../../services/suppliers_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/page_bar.dart';
import '../offers/offers_screen.dart';
import 'company_balance_screen.dart';
import 'company_form_dialog.dart';

/// عملاء الشركات — Angular `CompaniesComponent`.
class CompaniesListScreen extends StatefulWidget {
  const CompaniesListScreen({super.key});

  @override
  State<CompaniesListScreen> createState() => _CompaniesListScreenState();
}

class _CompaniesListScreenState extends State<CompaniesListScreen> {
  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();

  List<CustomerCompany> _items = [];
  int _total = 0;
  int _page = 1;
  int _pageSize = 15;
  int _unlinkedCount = 0;
  bool _unlinkedOnly = false;
  bool _linkingAll = false;
  int? _linkingId;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await CompaniesApi.instance.search(
        page: _page,
        itemsPerPage: _pageSize,
        name: _nameCtrl.text,
        phone: _phoneCtrl.text,
        unlinkedOnly: _unlinkedOnly,
      );
      int unlinked = _unlinkedCount;
      try {
        unlinked = await CompaniesApi.instance.unlinkedCount();
      } catch (_) {/* optional */}
      if (!mounted) return;
      setState(() {
        _items = page.items;
        _total = page.total;
        _pageSize = page.perPage;
        _unlinkedCount = unlinked;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'تعذر تحميل الشركات';
        _loading = false;
      });
    }
  }

  void _snack(String msg, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg),
        backgroundColor: error ? AppColors.danger : null,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  Future<void> _addOrEdit({CustomerCompany? company}) async {
    final ok = await CompanyFormDialog.open(context, company: company);
    if (ok) await _load();
  }

  Future<void> _linkAll() async {
    if (_unlinkedCount < 1) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('ربط غير المربوطين'),
        content: Text(
          'سيتم ربط $_unlinkedCount شركة غير مربوطة بحسابات تحت «عملاء شركات» في شجرة الحسابات. المتابعة؟',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('ربط'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => _linkingAll = true);
    try {
      final msg = await CompaniesApi.instance.linkUnlinked();
      if (!mounted) return;
      _snack(msg);
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      _snack(e.message, error: true);
    } finally {
      if (mounted) setState(() => _linkingAll = false);
    }
  }

  Future<void> _linkOne(CustomerCompany c) async {
    if (c.linked) return;
    setState(() => _linkingId = c.id);
    try {
      await CompaniesApi.instance.linkAccount(c.id);
      if (!mounted) return;
      _snack('تم ربط ${c.name} بالحساب');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      _snack(e.message, error: true);
    } finally {
      if (mounted) setState(() => _linkingId = null);
    }
  }

  Future<void> _collect(CustomerCompany c) async {
    PaymentSources? sources;
    try {
      sources = await SuppliersApi.instance.paymentSources();
    } on ApiException catch (e) {
      if (!mounted) return;
      _snack(e.message, error: true);
      return;
    }
    if (!mounted) return;

    final amountCtrl = TextEditingController();
    String paymentType = 'bank';
    String? sourceId;
    final formKey = GlobalKey<FormState>();

    List<PaymentSourceItem> sourcesFor(String type) {
      switch (type) {
        case 'safe':
          return sources!.safes;
        case 'service_account':
          return sources!.serviceAccounts;
        default:
          return sources!.banks;
      }
    }

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setLocal) {
            final list = sourcesFor(paymentType);
            return AlertDialog(
              title: Text('تحصيل من (${c.name})'),
              content: Form(
                key: formKey,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        Formatters.money(c.balance),
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 18,
                          color: AppColors.navy,
                        ),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        value: paymentType,
                        decoration: const InputDecoration(
                          labelText: 'نوع المصدر',
                          border: OutlineInputBorder(),
                        ),
                        items: const [
                          DropdownMenuItem(value: 'safe', child: Text('خزينة')),
                          DropdownMenuItem(value: 'bank', child: Text('بنك')),
                          DropdownMenuItem(
                            value: 'service_account',
                            child: Text('حساب خدمي'),
                          ),
                        ],
                        onChanged: (v) {
                          setLocal(() {
                            paymentType = v ?? 'bank';
                            sourceId = null;
                          });
                        },
                      ),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String>(
                        value: sourceId,
                        decoration: InputDecoration(
                          labelText: paymentType == 'safe'
                              ? 'الخزينة'
                              : paymentType == 'bank'
                                  ? 'البنك'
                                  : 'الحساب الخدمي',
                          border: const OutlineInputBorder(),
                        ),
                        items: [
                          for (final src in list)
                            DropdownMenuItem(
                              value: src.id,
                              child: Text(
                                '${src.name} (${Formatters.moneyPlain(src.balance)})',
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                        ],
                        onChanged: (v) => setLocal(() => sourceId = v),
                        validator: (v) =>
                            (v == null || v.isEmpty) ? 'اختر المصدر' : null,
                      ),
                      const SizedBox(height: 10),
                      TextFormField(
                        controller: amountCtrl,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        inputFormatters: [
                          FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                        ],
                        decoration: const InputDecoration(
                          labelText: 'ادخل المبلغ',
                          border: OutlineInputBorder(),
                        ),
                        validator: (v) {
                          final n = double.tryParse((v ?? '').trim());
                          if (n == null || n < 1) return 'أدخل مبلغاً صحيحاً';
                          return null;
                        },
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx, false),
                  child: const Text('الغاء'),
                ),
                FilledButton(
                  onPressed: () {
                    if (formKey.currentState?.validate() != true) return;
                    Navigator.pop(ctx, true);
                  },
                  child: const Text('تحصيل'),
                ),
              ],
            );
          },
        );
      },
    );

    final amount = double.tryParse(amountCtrl.text.trim());
    final selectedSource = sourceId;
    final selectedType = paymentType;
    amountCtrl.dispose();
    if (ok != true || amount == null || selectedSource == null) return;

    try {
      await CompaniesApi.instance.collect(
        id: c.id,
        amount: amount,
        paymentType: selectedType,
        bankId: selectedType == 'bank' ? selectedSource : null,
        safeId: selectedType == 'safe' ? selectedSource : null,
        serviceAccountId:
            selectedType == 'service_account' ? selectedSource : null,
      );
      if (!mounted) return;
      _snack('تم التحصيل');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      _snack(e.message, error: true);
    }
  }

  void _showActions(CustomerCompany c) {
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: Text(
                c.name,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(Formatters.money(c.balance)),
            ),
            const Divider(height: 1),
            ListTile(
              leading: const Icon(Icons.edit_outlined),
              title: const Text('تعديل / ربط الحساب'),
              onTap: () {
                Navigator.pop(ctx);
                _addOrEdit(company: c);
              },
            ),
            if (!c.linked)
              ListTile(
                leading: const Icon(Icons.link),
                title: Text(
                  _linkingId == c.id ? 'جاري الربط...' : 'إنشاء حساب تلقائي',
                ),
                onTap: _linkingId == c.id
                    ? null
                    : () {
                        Navigator.pop(ctx);
                        _linkOne(c);
                      },
              ),
            ListTile(
              leading: const Icon(Icons.receipt_long_outlined),
              title: const Text('كشف حساب'),
              onTap: () {
                Navigator.pop(ctx);
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => CompanyBalanceScreen(
                      companyId: c.id,
                      companyName: c.name,
                    ),
                  ),
                );
              },
            ),
            ListTile(
              leading: const Icon(Icons.request_quote_outlined),
              title: const Text('عروض الأسعار'),
              onTap: () {
                Navigator.pop(ctx);
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const OffersScreen()),
                );
              },
            ),
            ListTile(
              leading: const Icon(Icons.payments_outlined),
              title: const Text('تحصيل'),
              onTap: () {
                Navigator.pop(ctx);
                _collect(c);
              },
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('عملاء الشركات'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: () => _addOrEdit(),
          icon: const Icon(Icons.add),
          label: const Text('اضافة'),
        ),
        body: Column(
          children: [
            Container(
              width: double.infinity,
              color: AppColors.surface,
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _nameCtrl,
                          onSubmitted: (_) {
                            _page = 1;
                            _load();
                          },
                          decoration: _field('اسم الشركة', Icons.business),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: TextField(
                          controller: _phoneCtrl,
                          keyboardType: TextInputType.phone,
                          onSubmitted: (_) {
                            _page = 1;
                            _load();
                          },
                          decoration: _field('رقم الموبايل', Icons.phone),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: _linkingAll || _unlinkedCount < 1
                              ? null
                              : _linkAll,
                          child: Text(
                            _linkingAll
                                ? 'جاري الربط...'
                                : 'ربط غير المربوطين ($_unlinkedCount)',
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {
                            setState(() {
                              _unlinkedOnly = !_unlinkedOnly;
                              _page = 1;
                            });
                            _load();
                          },
                          style: _unlinkedOnly
                              ? OutlinedButton.styleFrom(
                                  backgroundColor:
                                      AppColors.navy.withValues(alpha: 0.08),
                                )
                              : null,
                          child: Text(
                            _unlinkedOnly
                                ? 'عرض الكل'
                                : 'عرض غير المربوطين فقط',
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            Expanded(child: _buildBody()),
            if (!_loading && _error == null)
              PageBar(
                page: _page,
                pageSize: _pageSize,
                total: _total,
                label: '$_total شركة',
                onPageChanged: (p) {
                  setState(() => _page = p);
                  _load();
                },
                onPageSizeChanged: (s) {
                  setState(() {
                    _pageSize = s;
                    _page = 1;
                  });
                  _load();
                },
              ),
          ],
        ),
      ),
    );
  }

  InputDecoration _field(String hint, IconData icon) {
    return InputDecoration(
      hintText: hint,
      prefixIcon: Icon(icon, size: 20),
      isDense: true,
      filled: true,
      fillColor: AppColors.bg,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                _error!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.danger),
              ),
              const SizedBox(height: 12),
              FilledButton(onPressed: _load, child: const Text('إعادة المحاولة')),
            ],
          ),
        ),
      );
    }
    if (_items.isEmpty) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const [
            SizedBox(height: 120),
            Icon(Icons.apartment_outlined, size: 48, color: AppColors.textMuted),
            SizedBox(height: 12),
            Center(
              child: Text(
                'لا توجد شركات',
                style: TextStyle(color: AppColors.textSecondary),
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 88),
        itemCount: _items.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final c = _items[index];
          return Material(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () => _showActions(c),
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            c.name,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 15,
                              color: AppColors.navy,
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: c.linked
                                ? AppColors.success.withValues(alpha: 0.12)
                                : AppColors.warning.withValues(alpha: 0.18),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            c.accountLabel,
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color:
                                  c.linked ? AppColors.success : AppColors.navy,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      [
                        if (c.phone1 != null) c.phone1!,
                        if (c.governorate != null) c.governorate!,
                        if (c.city != null) c.city!,
                      ].join(' · '),
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                    if (c.address != null)
                      Text(
                        c.address!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 11,
                          color: AppColors.textMuted,
                        ),
                      ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Text('طلبات: ${c.numberOfOrders}'),
                        const Spacer(),
                        Text(
                          Formatters.money(c.balance),
                          textDirection: TextDirection.ltr,
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        IconButton(
                          onPressed: () => _showActions(c),
                          icon: const Icon(Icons.more_vert),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
