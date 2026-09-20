import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/purchases_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';

/// تفاصيل فاتورة مشتريات — mirrors Angular `PurchaseDetailsComponent` (read).
class PurchaseDetailsScreen extends StatefulWidget {
  const PurchaseDetailsScreen({super.key, required this.purchaseId});

  final String purchaseId;

  @override
  State<PurchaseDetailsScreen> createState() => _PurchaseDetailsScreenState();
}

class _PurchaseDetailsScreenState extends State<PurchaseDetailsScreen> {
  PurchaseInvoiceDetail? _invoice;
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
      final inv = await PurchasesApi.instance.show(widget.purchaseId);
      if (!mounted) return;
      setState(() {
        _invoice = inv;
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
        _error = 'تعذر تحميل تفاصيل الفاتورة';
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
          title: Text(_invoice?.invoiceNumber ?? 'تفاصيل الفاتورة'),
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

    final inv = _invoice!;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        primary: true,
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 28),
        children: [
          _card(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  inv.invoiceNumber,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: AppColors.navy,
                  ),
                ),
                const SizedBox(height: 10),
                _kv('المورد', inv.supplierName),
                _kv('نوع الفاتورة', inv.invoiceType),
                if (inv.receiptDate != null)
                  _kv('تاريخ الاستلام', inv.receiptDate!),
                if (inv.externalInvoiceNo != null)
                  _kv('رقم فاتورة المورد', inv.externalInvoiceNo!),
                _kv('الإجمالي', Formatters.money(inv.totalPrice)),
                _kv('المدفوع', Formatters.money(inv.paidAmount)),
                _kv('المتبقي', Formatters.money(inv.dueAmount)),
                _kv('تكلفة النقل', Formatters.money(inv.transportCost)),
              ],
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            'بنود الفاتورة',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: AppColors.navy,
            ),
          ),
          const SizedBox(height: 8),
          if (inv.lines.isEmpty)
            _card(
              child: const Text(
                'لا توجد بنود',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.textSecondary),
              ),
            )
          else
            for (final line in inv.lines) ...[
              _card(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      line.productName,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: AppColors.navy,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '${Formatters.moneyPlain(line.quantity)} ${line.productUnit} × ${Formatters.moneyPlain(line.price)} = ${Formatters.money(line.total)}',
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
            ],
        ],
      ),
    );
  }

  Widget _card({required Widget child}) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: child,
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
