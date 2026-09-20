import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/customers_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/page_bar.dart';
import 'customer_account_details_screen.dart';

/// تقرير الأوردرات — Angular `ReportNewOrdersComponent`.
class OrdersReportScreen extends StatefulWidget {
  const OrdersReportScreen({super.key});

  @override
  State<OrdersReportScreen> createState() => _OrdersReportScreenState();
}

class _OrdersReportScreenState extends State<OrdersReportScreen> {
  final _searchCtrl = TextEditingController();
  List<OrderReportRow> _raw = [];
  int _page = 1;
  int _pageSize = 15;
  bool _loading = true;
  String? _error;
  final _expanded = <String>{};

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
      final rows = await CustomersApi.instance.orderReport();
      if (!mounted) return;
      setState(() {
        _raw = rows;
        _page = 1;
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
        _error = 'تعذر تحميل تقرير الأوردرات';
        _loading = false;
      });
    }
  }

  List<OrderReportRow> get _filtered {
    final q = _searchCtrl.text.trim().toLowerCase();
    if (q.isEmpty) return _raw;
    return _raw.where((r) {
      final hay = [
        r.orderId,
        r.customerName,
        r.entryBatchCode,
      ].map((v) => (v ?? '').toLowerCase()).join(' ');
      return hay.contains(q);
    }).toList();
  }

  List<OrderReportRow> get _pageRows {
    final filtered = _filtered;
    final start = (_page - 1) * _pageSize;
    if (start >= filtered.length) return const [];
    return filtered.sublist(
      start,
      (start + _pageSize).clamp(0, filtered.length),
    );
  }

  double get _sumDebit =>
      _filtered.fold(0, (s, r) => s + r.totalDebit);
  double get _sumCredit =>
      _filtered.fold(0, (s, r) => s + r.totalCredit);

  @override
  Widget build(BuildContext context) {
    final filtered = _filtered;
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('تقرير الأوردرات'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
          actions: [
            IconButton(
              tooltip: 'تحديث',
              onPressed: _loading ? null : _load,
              icon: const Icon(Icons.refresh),
            ),
          ],
        ),
        body: Column(
          children: [
            Container(
              color: AppColors.surface,
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              child: TextField(
                controller: _searchCtrl,
                onChanged: (_) => setState(() => _page = 1),
                decoration: InputDecoration(
                  hintText: 'بحث برقم الأوردر أو اسم العميل أو رمز القيد…',
                  prefixIcon: const Icon(Icons.search, size: 20),
                  isDense: true,
                  filled: true,
                  fillColor: AppColors.bg,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ),
            if (!_loading && _raw.isNotEmpty)
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
                child: Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _sumChip('سجلات', '${filtered.length}', AppColors.navy),
                    _sumChip(
                      'مدين',
                      Formatters.moneyPlain(_sumDebit),
                      AppColors.danger,
                    ),
                    _sumChip(
                      'دائن',
                      Formatters.moneyPlain(_sumCredit),
                      AppColors.success,
                    ),
                    _sumChip(
                      'صافي',
                      Formatters.moneyPlain(_sumDebit - _sumCredit),
                      AppColors.primary,
                    ),
                  ],
                ),
              ),
            Expanded(child: _buildBody(filtered)),
            if (!_loading && _error == null && filtered.isNotEmpty)
              PageBar(
                page: _page,
                pageSize: _pageSize,
                total: filtered.length,
                label: '${filtered.length} سجل',
                onPageChanged: (p) => setState(() => _page = p),
                onPageSizeChanged: (s) => setState(() {
                  _pageSize = s;
                  _page = 1;
                }),
              ),
          ],
        ),
      ),
    );
  }

  Widget _sumChip(String label, String value, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          Text(
            label,
            style: TextStyle(fontSize: 11, color: color),
          ),
          Text(
            value,
            style: TextStyle(fontWeight: FontWeight.w800, color: color),
          ),
        ],
      ),
    );
  }

  Widget _buildBody(List<OrderReportRow> filtered) {
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
    if (_raw.isEmpty) {
      return const Center(child: Text('لا توجد بيانات.'));
    }
    if (filtered.isEmpty) {
      return const Center(child: Text('لا توجد نتائج تطابق البحث.'));
    }

    final rows = _pageRows;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
        itemCount: rows.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final row = rows[index];
          final expanded = _expanded.contains(row.key);
          return Container(
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              children: [
                ListTile(
                  title: Text(
                    row.customerName ?? '—',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  subtitle: Text(
                    [
                      if (row.orderId != null) 'أوردر ${row.orderId}',
                      'دائن ${Formatters.moneyPlain(row.totalCredit)}',
                      'مدين ${Formatters.moneyPlain(row.totalDebit)}',
                    ].join(' · '),
                  ),
                  trailing: Icon(
                    expanded ? Icons.expand_less : Icons.expand_more,
                  ),
                  onTap: () {
                    setState(() {
                      if (expanded) {
                        _expanded.remove(row.key);
                      } else {
                        _expanded.add(row.key);
                      }
                    });
                  },
                ),
                if (expanded)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text('رمز القيد: ${row.entryBatchCode ?? '—'}'),
                        const SizedBox(height: 8),
                        Align(
                          alignment: Alignment.centerLeft,
                          child: TextButton.icon(
                            onPressed: row.orderId == null
                                ? null
                                : () {
                                    Navigator.of(context).push(
                                      MaterialPageRoute(
                                        builder: (_) =>
                                            OrderReportDetailsScreen(
                                          orderId: row.orderId!,
                                        ),
                                      ),
                                    );
                                  },
                            icon: const Icon(Icons.info_outline),
                            label: const Text('تفاصيل القيد والأوردر'),
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

/// تفاصيل تقرير الأوردر — Angular `report-order-new-details`.
class OrderReportDetailsScreen extends StatefulWidget {
  const OrderReportDetailsScreen({super.key, required this.orderId});

  final String orderId;

  @override
  State<OrderReportDetailsScreen> createState() =>
      _OrderReportDetailsScreenState();
}

class _OrderReportDetailsScreenState extends State<OrderReportDetailsScreen> {
  final _searchCtrl = TextEditingController();
  List<OrderReportDetailRow> _raw = [];
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
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final rows = await CustomersApi.instance.orderReportDetails(
        orderId: widget.orderId,
      );
      if (!mounted) return;
      setState(() {
        _raw = rows;
        _page = 1;
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
        _error = 'تعذر تحميل التفاصيل';
        _loading = false;
      });
    }
  }

  List<OrderReportDetailRow> get _filtered {
    final q = _searchCtrl.text.trim().toLowerCase();
    if (q.isEmpty) return _raw;
    return _raw.where((r) {
      final hay = [
        r.entryBatchCode,
        r.orderId,
        r.assetName,
      ].map((v) => (v ?? '').toLowerCase()).join(' ');
      return hay.contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _filtered;
    final start = (_page - 1) * _pageSize;
    final pageRows = start >= filtered.length
        ? const <OrderReportDetailRow>[]
        : filtered.sublist(
            start,
            (start + _pageSize).clamp(0, filtered.length),
          );

    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('تفاصيل تقرير الأوردرات'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        body: Column(
          children: [
            Container(
              color: AppColors.surface,
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              child: TextField(
                controller: _searchCtrl,
                onChanged: (_) => setState(() => _page = 1),
                decoration: InputDecoration(
                  hintText: 'بحث في الجدول…',
                  prefixIcon: const Icon(Icons.search, size: 20),
                  isDense: true,
                  filled: true,
                  fillColor: AppColors.bg,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : _error != null
                      ? Center(child: Text(_error!))
                      : filtered.isEmpty
                          ? const Center(child: Text('لا توجد بيانات.'))
                          : ListView.separated(
                              padding: const EdgeInsets.all(12),
                              itemCount: pageRows.length,
                              separatorBuilder: (_, __) =>
                                  const SizedBox(height: 8),
                              itemBuilder: (context, index) {
                                final row = pageRows[index];
                                return Container(
                                  padding: const EdgeInsets.all(14),
                                  decoration: BoxDecoration(
                                    color: AppColors.surface,
                                    borderRadius: BorderRadius.circular(14),
                                    border: Border.all(color: AppColors.border),
                                  ),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        row.entryBatchCode ?? '—',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w800,
                                          color: AppColors.navy,
                                        ),
                                      ),
                                      Text('أوردر ${row.orderId ?? '—'}'),
                                      if (row.assetName != null)
                                        Text(row.assetName!),
                                      const SizedBox(height: 6),
                                      Row(
                                        children: [
                                          Text(
                                            'دائن ${Formatters.moneyPlain(row.credit)}',
                                            textDirection: TextDirection.ltr,
                                          ),
                                          const SizedBox(width: 16),
                                          Text(
                                            'مدين ${Formatters.moneyPlain(row.debit)}',
                                            textDirection: TextDirection.ltr,
                                          ),
                                          const Spacer(),
                                          if (row.customerPhone != null)
                                            TextButton(
                                              onPressed: () {
                                                Navigator.of(context).push(
                                                  MaterialPageRoute(
                                                    builder: (_) =>
                                                        CustomerAccountDetailsScreen(
                                                      customer:
                                                          row.customerPhone!,
                                                    ),
                                                  ),
                                                );
                                              },
                                              child: const Text('تفاصيل'),
                                            ),
                                        ],
                                      ),
                                    ],
                                  ),
                                );
                              },
                            ),
            ),
            if (!_loading && _error == null && filtered.isNotEmpty)
              PageBar(
                page: _page,
                pageSize: _pageSize,
                total: filtered.length,
                onPageChanged: (p) => setState(() => _page = p),
                onPageSizeChanged: (s) => setState(() {
                  _pageSize = s;
                  _page = 1;
                }),
              ),
          ],
        ),
      ),
    );
  }
}
