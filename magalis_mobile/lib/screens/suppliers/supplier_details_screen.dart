import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/suppliers_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';

/// تفاصيل حساب المورد — mirrors Angular `SupplierDetailsComponent` (ledger).
class SupplierDetailsScreen extends StatefulWidget {
  const SupplierDetailsScreen({super.key, required this.supplierId});

  final String supplierId;

  @override
  State<SupplierDetailsScreen> createState() => _SupplierDetailsScreenState();
}

class _SupplierDetailsScreenState extends State<SupplierDetailsScreen> {
  SupplierItem? _supplier;
  List<SupplierLedgerRow> _rows = [];
  int _total = 0;
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
      final page = await SuppliersApi.instance.details(id: widget.supplierId);
      if (!mounted) return;
      setState(() {
        _supplier = page.supplier;
        _rows = page.rows;
        _total = page.total;
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
        _error = 'تعذر تحميل تفاصيل المورد';
        _loading = false;
      });
    }
  }

  String _kindLabel(SupplierLedgerRow r) {
    if (r.isPayment || r.entryKind == 'payment') return 'سداد';
    if (r.entryKind == 'processing') return 'تشغيل خارجي';
    if (r.entryKind == 'purchase') return 'مشتريات';
    return r.documentType.isNotEmpty ? r.documentType : r.entryKind;
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: Text(_supplier?.name ?? 'تفاصيل المورد'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
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

    final s = _supplier!;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        primary: true,
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 28),
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  s.name,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: AppColors.navy,
                  ),
                ),
                const SizedBox(height: 8),
                _kv('الرصيد / الذمة', Formatters.money(s.balance)),
                if (s.phone != null) _kv('الهاتف', s.phone!),
                if (s.address != null) _kv('العنوان', s.address!),
                if (_total > 0) _kv('عدد الحركات', '$_total'),
              ],
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            'كشف الحساب',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: AppColors.navy,
            ),
          ),
          const SizedBox(height: 8),
          if (_rows.isEmpty)
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppColors.border),
              ),
              child: const Text(
                'لا توجد حركات',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.textSecondary),
              ),
            )
          else
            for (final r in _rows) ...[
              Container(
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            r.invoiceNumber ?? r.details,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
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
                            color: AppColors.primaryBg,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            _kindLabel(r),
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: AppColors.primary,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (r.details.isNotEmpty && r.invoiceNumber != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        r.details,
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 10,
                      runSpacing: 4,
                      children: [
                        if (r.receiptDate != null)
                          Text(
                            r.receiptDate!,
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.textMuted,
                            ),
                          ),
                        Text(
                          'إجمالي: ${Formatters.moneyPlain(r.totalPrice)}',
                          style: const TextStyle(fontSize: 12),
                        ),
                        Text(
                          'مدفوع: ${Formatters.moneyPlain(r.paidAmount)}',
                          style: const TextStyle(fontSize: 12),
                        ),
                        if (r.balanceAfter != null)
                          Text(
                            'الرصيد بعد: ${Formatters.moneyPlain(r.balanceAfter!)}',
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
        ],
      ),
    );
  }

  Widget _kv(String k, String v) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        children: [
          Expanded(
            child: Text(
              k,
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          Text(v, style: const TextStyle(fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}
