import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/customers_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/page_bar.dart';
import '../orders/order_details_screen.dart';

/// تفاصيل حساب عميل — Angular `CustomerAccountDetailsComponent`.
class CustomerAccountDetailsScreen extends StatefulWidget {
  const CustomerAccountDetailsScreen({super.key, required this.customer});

  final String customer;

  @override
  State<CustomerAccountDetailsScreen> createState() =>
      _CustomerAccountDetailsScreenState();
}

class _CustomerAccountDetailsScreenState
    extends State<CustomerAccountDetailsScreen> {
  List<CustomerLedgerRow> _items = [];
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

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await CustomersApi.instance.customerDetails(
        customer: widget.customer,
        page: _page,
        itemsPerPage: _pageSize,
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
        _error = 'تعذر تحميل تفاصيل العميل';
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
          title: Text('تفاصيل عميل ${widget.customer}'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        body: Column(
          children: [
            Expanded(child: _buildBody()),
            if (!_loading && _error == null)
              PageBar(
                page: _page,
                pageSize: _pageSize,
                total: _total,
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
    if (_items.isEmpty) {
      return const Center(child: Text('لا توجد حركات'));
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
              onTap: row.orderId.isEmpty || row.orderId == '—'
                  ? null
                  : () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) =>
                              OrderDetailsScreen(orderId: row.orderId),
                        ),
                      );
                    },
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppColors.border),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'أوردر ${row.orderId}',
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              color: AppColors.navy,
                            ),
                          ),
                          if (row.createdAt != null)
                            Text(
                              Formatters.date(row.createdAt!),
                              style: const TextStyle(
                                fontSize: 12,
                                color: AppColors.textSecondary,
                              ),
                            ),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          'دائن ${Formatters.moneyPlain(row.credit)}',
                          textDirection: TextDirection.ltr,
                          style: const TextStyle(
                            color: AppColors.success,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        Text(
                          'مدين ${Formatters.moneyPlain(row.debit)}',
                          textDirection: TextDirection.ltr,
                          style: const TextStyle(
                            color: AppColors.danger,
                            fontWeight: FontWeight.w700,
                          ),
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
