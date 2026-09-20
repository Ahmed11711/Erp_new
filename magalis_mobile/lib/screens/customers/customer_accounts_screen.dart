import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/customers_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/page_bar.dart';
import 'customer_account_details_screen.dart';

enum CustomerAccountsMode { accounts, individuals }

/// حسابات العملاء / العملاء الأفراد — Angular `CustomerAccounts` / `IndividualsClients`.
class CustomerAccountsScreen extends StatefulWidget {
  const CustomerAccountsScreen({
    super.key,
    this.mode = CustomerAccountsMode.accounts,
  });

  final CustomerAccountsMode mode;

  @override
  State<CustomerAccountsScreen> createState() => _CustomerAccountsScreenState();
}

class _CustomerAccountsScreenState extends State<CustomerAccountsScreen> {
  final _searchCtrl = TextEditingController();
  List<CustomerAccountRow> _items = [];
  int _total = 0;
  int _page = 1;
  int _pageSize = 15;
  bool _loading = true;
  String? _error;

  bool get _individuals => widget.mode == CustomerAccountsMode.individuals;

  String get _title => _individuals ? 'العملاء الأفراد' : 'حسابات العملاء';

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await CustomersApi.instance.searchCustomers(
        page: _page,
        itemsPerPage: _pageSize,
        search: _searchCtrl.text,
      );
      if (!mounted) return;
      setState(() {
        _items = page.items;
        _total = page.total;
        _pageSize = page.perPage;
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
        _error = 'تعذر تحميل العملاء';
        _loading = false;
      });
    }
  }

  void _openDetails(CustomerAccountRow row) {
    if (row.phone.isEmpty) return;
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CustomerAccountDetailsScreen(customer: row.phone),
      ),
    );
  }

  double get _footDue => _items.fold(0, (s, r) => s + r.due);
  double get _footPrepaid => _items.fold(0, (s, r) => s + r.totalCredit);
  double get _footNet => _items.fold(0, (s, r) => s + r.totalDebit);
  int get _footOrders => _items.fold(0, (s, r) => s + r.ordersCount);

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: Text(_title),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        body: Column(
          children: [
            Container(
              width: double.infinity,
              color: AppColors.surface,
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _searchCtrl,
                      onSubmitted: (_) {
                        _page = 1;
                        _load();
                      },
                      decoration: InputDecoration(
                        hintText: 'بحث بالاسم أو رقم الموبايل',
                        prefixIcon: const Icon(Icons.search, size: 20),
                        isDense: true,
                        filled: true,
                        fillColor: AppColors.bg,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 10,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  FilledButton(
                    onPressed: () {
                      _page = 1;
                      _load();
                    },
                    child: const Text('بحث'),
                  ),
                  if (_searchCtrl.text.trim().isNotEmpty) ...[
                    const SizedBox(width: 6),
                    IconButton(
                      tooltip: 'مسح',
                      onPressed: () {
                        _searchCtrl.clear();
                        _page = 1;
                        _load();
                      },
                      icon: const Icon(Icons.clear),
                    ),
                  ],
                ],
              ),
            ),
            Expanded(child: _buildBody()),
            if (!_loading && _error == null)
              PageBar(
                page: _page,
                pageSize: _pageSize,
                total: _total,
                label: '$_total عميل',
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
            Icon(Icons.people_outline, size: 48, color: AppColors.textMuted),
            SizedBox(height: 12),
            Center(
              child: Text(
                'لا توجد بيانات',
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
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
        itemCount: _items.length + (_individuals ? 1 : 0),
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          if (_individuals && index == _items.length) {
            return _footerCard();
          }
          final row = _items[index];
          return _customerCard(row);
        },
      ),
    );
  }

  Widget _footerCard() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.navy.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.navy.withValues(alpha: 0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'الإجمالي (الصفحة الحالية)',
            style: TextStyle(fontWeight: FontWeight.w800, color: AppColors.navy),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 16,
            runSpacing: 6,
            children: [
              Text('الطلبات: $_footOrders'),
              Text('مستحق: ${Formatters.moneyPlain(_footDue)}'),
              Text('مقدم: ${Formatters.moneyPlain(_footPrepaid)}'),
              Text('إجمالي: ${Formatters.moneyPlain(_footNet)}'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _customerCard(CustomerAccountRow row) {
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () => _openDetails(row),
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
                      row.name,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                        color: AppColors.navy,
                      ),
                    ),
                  ),
                  if (_individuals)
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: row.linked
                            ? AppColors.success.withValues(alpha: 0.12)
                            : AppColors.warning.withValues(alpha: 0.18),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        row.linked
                            ? (row.treeAccountCode ?? 'مرتبط')
                            : 'غير مربوط',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: row.linked ? AppColors.success : AppColors.navy,
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                [
                  if (row.phone.isNotEmpty) row.phone,
                  if (_individuals) 'فرد',
                  'طلبات: ${row.ordersCount}',
                ].join(' · '),
                style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  _amt('دائن', row.totalCredit, AppColors.success),
                  _amt(
                    _individuals ? 'مستحق' : 'مدين',
                    _individuals ? row.due : row.totalDebit,
                    AppColors.danger,
                  ),
                  if (_individuals)
                    _amt('إجمالي', row.totalDebit, AppColors.navy),
                  const Spacer(),
                  TextButton(
                    onPressed: () => _openDetails(row),
                    child: Text(_individuals ? 'تفاصيل الحساب' : 'تفاصيل'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _amt(String label, double value, Color color) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(end: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(fontSize: 11, color: AppColors.textMuted),
          ),
          Text(
            Formatters.moneyPlain(value),
            textDirection: TextDirection.ltr,
            style: TextStyle(fontWeight: FontWeight.w800, color: color),
          ),
        ],
      ),
    );
  }
}
