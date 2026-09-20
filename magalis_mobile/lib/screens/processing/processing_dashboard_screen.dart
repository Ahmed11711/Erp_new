import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/processing_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import 'processing_order_detail_screen.dart';
import 'processing_orders_screen.dart';
import 'processing_dispatch_form_screen.dart';

/// لوحة التشغيل الخارجي — mirrors Angular `ProcessingDashboardComponent`.
class ProcessingDashboardScreen extends StatefulWidget {
  const ProcessingDashboardScreen({super.key});

  @override
  State<ProcessingDashboardScreen> createState() =>
      _ProcessingDashboardScreenState();
}

class _ProcessingDashboardScreenState extends State<ProcessingDashboardScreen> {
  ProcessingKpis _kpis = const ProcessingKpis();
  List<MaterialAtVendor> _materials = [];
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
      final results = await Future.wait([
        ProcessingApi.instance.kpis(),
        ProcessingApi.instance.materialsAtVendor(),
      ]);
      if (!mounted) return;
      setState(() {
        _kpis = results[0] as ProcessingKpis;
        _materials = results[1] as List<MaterialAtVendor>;
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
        _error = 'تعذر تحميل لوحة التشغيل الخارجي';
        _loading = false;
      });
    }
  }

  void _openOrders() {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const ProcessingOrdersScreen()),
    );
  }

  void _openOrder(String id) {
    if (id.isEmpty || id == '0') return;
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ProcessingOrderDetailScreen(orderId: id),
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
          title: const Text('لوحة التشغيل الخارجي'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
          actions: [
            TextButton(
              onPressed: _openOrders,
              child: const Text(
                'الإذونات',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ),
        body: _buildBody(),
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

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        primary: true,
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 28),
        children: [
          const Text(
            'صرف المواد للمطابع ومعالجي التجهيز — استلام — فواتير — ذمم',
            style: TextStyle(
              color: AppColors.textSecondary,
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 14),
          LayoutBuilder(
            builder: (context, constraints) {
              final wide = constraints.maxWidth >= 560;
              final cards = [
                _kpiCard(
                  label: 'أوامر مفتوحة',
                  value: Formatters.moneyPlain(_kpis.openOrders),
                  color: AppColors.navy,
                  icon: Icons.assignment_outlined,
                ),
                _kpiCard(
                  label: 'كمية لدى المعالجين',
                  value: Formatters.moneyPlain(_kpis.qtyAtVendor),
                  color: AppColors.warning,
                  icon: Icons.inventory_2_outlined,
                ),
                _kpiCard(
                  label: 'ذمم مستحقة',
                  value: Formatters.money(_kpis.outstandingAp),
                  color: AppColors.danger,
                  icon: Icons.account_balance_wallet_outlined,
                ),
                _kpiCard(
                  label: 'فواتير متأخرة',
                  value: Formatters.moneyPlain(_kpis.overdueInvoices),
                  color: AppColors.info,
                  icon: Icons.schedule_outlined,
                ),
              ];
              if (wide) {
                return Row(
                  children: [
                    for (var i = 0; i < cards.length; i++) ...[
                      if (i > 0) const SizedBox(width: 8),
                      Expanded(child: cards[i]),
                    ],
                  ],
                );
              }
              return Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final c in cards)
                    SizedBox(
                      width: (constraints.maxWidth - 8) / 2,
                      child: c,
                    ),
                ],
              );
            },
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              const Expanded(
                child: Text(
                  'مواد حالياً خارج المخزن (لدى المعالج)',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: AppColors.navy,
                  ),
                ),
              ),
              TextButton(
                onPressed: _openOrders,
                child: const Text('عرض الإذونات'),
              ),
              TextButton(
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const ProcessingDispatchFormScreen(),
                    ),
                  );
                },
                child: const Text('إذن صرف جديد'),
              ),
            ],
          ),
          const SizedBox(height: 8),
          if (_materials.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppColors.border),
              ),
              child: const Text(
                'لا توجد مواد لدى معالجين حالياً.',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.textSecondary),
              ),
            )
          else
            for (final m in _materials) ...[
              Material(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(12),
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: () => _openOrder(m.orderId),
                  child: Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.border),
                    ),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                m.orderNumber,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.navy,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                m.supplierName,
                                style: const TextStyle(
                                  fontSize: 13,
                                  color: AppColors.textSecondary,
                                ),
                              ),
                            ],
                          ),
                        ),
                        Text(
                          Formatters.moneyPlain(m.qtyAtVendor),
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 16,
                            color: AppColors.warning,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
        ],
      ),
    );
  }

  Widget _kpiCard({
    required String label,
    required String value,
    required Color color,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: color, size: 18),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: color,
            ),
          ),
        ],
      ),
    );
  }
}
