import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/companies_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/page_bar.dart';
import '../offers/offers_screen.dart';
import '../orders/order_details_screen.dart';

/// كشف حساب عميل شركة — Angular `CustomerCompanyBalanceComponent`.
class CompanyBalanceScreen extends StatefulWidget {
  const CompanyBalanceScreen({
    super.key,
    required this.companyId,
    this.companyName,
  });

  final int companyId;
  final String? companyName;

  @override
  State<CompanyBalanceScreen> createState() => _CompanyBalanceScreenState();
}

class _CompanyBalanceScreenState extends State<CompanyBalanceScreen> {
  String _name = '';
  double _balance = 0;
  List<CompanyBalanceRow> _rows = [];
  int _total = 0;
  int _page = 1;
  int _pageSize = 15;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _name = widget.companyName ?? '';
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await CompaniesApi.instance.balance(
        id: widget.companyId,
        page: _page,
        itemsPerPage: _pageSize,
      );
      if (!mounted) return;
      setState(() {
        _name = page.name.isEmpty ? _name : page.name;
        _balance = page.balance;
        _rows = page.rows;
        _total = page.total;
        _pageSize = page.perPage;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message.isNotEmpty ? e.message : 'تعذر تحميل كشف الحساب';
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'تعذر تحميل كشف الحساب';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: Text('كشف حساب ${_name.isEmpty ? '' : '($_name)'}'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const OffersScreen()),
                );
              },
              child: const Text('عروضه', style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
        body: Column(
          children: [
            Container(
              width: double.infinity,
              color: AppColors.navy,
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'إجمالي رصيد الحساب',
                    style: TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                  Text(
                    Formatters.money(_balance),
                    textDirection: TextDirection.ltr,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 22,
                    ),
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
                pageSizeOptions: const [15, 50],
                label: '$_total حركة',
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
    if (_rows.isEmpty) {
      return const Center(child: Text('لا توجد حركات على هذا الحساب'));
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
        itemCount: _rows.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final row = _rows[index];
          return Material(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: row.isOrderRef && row.ref != null
                  ? () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => OrderDetailsScreen(orderId: row.ref!),
                        ),
                      );
                    }
                  : null,
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
                            row.ref ?? '—',
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              color: AppColors.navy,
                            ),
                          ),
                        ),
                        if (row.type != null)
                          Text(
                            row.type!,
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.textSecondary,
                            ),
                          ),
                      ],
                    ),
                    if (row.details != null) ...[
                      const SizedBox(height: 4),
                      Text(row.details!),
                    ],
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Text(
                          Formatters.moneyPlain(row.amount),
                          textDirection: TextDirection.ltr,
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        const Spacer(),
                        Text(
                          'قبل ${Formatters.moneyPlain(row.balanceBefore)} → بعد ${Formatters.moneyPlain(row.balanceAfter)}',
                          textDirection: TextDirection.ltr,
                          style: const TextStyle(
                            fontSize: 11,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                    if (row.date != null || row.byName != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          [row.date, row.byName]
                              .whereType<String>()
                              .join(' · '),
                          style: const TextStyle(
                            fontSize: 11,
                            color: AppColors.textMuted,
                          ),
                        ),
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
