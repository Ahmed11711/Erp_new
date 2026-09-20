import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/customers_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/page_bar.dart';
import '../suppliers/supplier_details_screen.dart';

/// حسابات الموردين — Angular `SupplierAccountsComponent`.
class SupplierAccountsScreen extends StatefulWidget {
  const SupplierAccountsScreen({super.key});

  @override
  State<SupplierAccountsScreen> createState() => _SupplierAccountsScreenState();
}

class _SupplierAccountsScreenState extends State<SupplierAccountsScreen> {
  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  String _status = '';
  List<SupplierAccountRow> _items = [];
  int _total = 0;
  int _page = 1;
  int _pageSize = 15;
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
      final page = await CustomersApi.instance.searchSuppliers(
        page: _page,
        itemsPerPage: _pageSize,
        name: _nameCtrl.text,
        phone: _phoneCtrl.text,
        status: _status.isEmpty ? null : _status,
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
        _error = 'تعذر تحميل حسابات الموردين';
        _loading = false;
      });
    }
  }

  void _runSearch() {
    _page = 1;
    _load();
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('حسابات الموردين'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        body: Column(
          children: [
            Container(
              width: double.infinity,
              color: AppColors.surface,
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextField(
                    controller: _nameCtrl,
                    onSubmitted: (_) => _runSearch(),
                    decoration: _field('اسم المورد'),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _phoneCtrl,
                    keyboardType: TextInputType.phone,
                    onSubmitted: (_) => _runSearch(),
                    decoration: _field('رقم الموبايل'),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    children: [
                      _chip('الكل', ''),
                      _chip('مدين', 'want'),
                      _chip('دائن', 'own'),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: FilledButton(
                          onPressed: _runSearch,
                          child: const Text('بحث'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {
                            _nameCtrl.clear();
                            _phoneCtrl.clear();
                            _status = '';
                            _runSearch();
                          },
                          child: const Text('مسح'),
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
                label: '$_total مورد',
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

  Widget _chip(String label, String value) {
    final selected = _status == value;
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) {
        setState(() => _status = value);
        _runSearch();
      },
    );
  }

  InputDecoration _field(String hint) {
    return InputDecoration(
      hintText: hint,
      isDense: true,
      filled: true,
      fillColor: AppColors.bg,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
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
            Icon(Icons.store_outlined, size: 48, color: AppColors.textMuted),
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
        itemCount: _items.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final row = _items[index];
          return Material(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) =>
                        SupplierDetailsScreen(supplierId: row.supplierId),
                  ),
                );
              },
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      row.name,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                        color: AppColors.navy,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      [
                        if (row.phone != null) row.phone!,
                        'فواتير: ${row.orderCount}',
                      ].join(' · '),
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Text(
                          'دائن ${Formatters.moneyPlain(row.credit)}',
                          textDirection: TextDirection.ltr,
                          style: const TextStyle(
                            color: AppColors.success,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(width: 16),
                        Text(
                          'مدين ${Formatters.moneyPlain(row.debit)}',
                          textDirection: TextDirection.ltr,
                          style: const TextStyle(
                            color: AppColors.danger,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const Spacer(),
                        const Text('تفاصيل حساب المورد'),
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
