import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart' hide TextDirection;

import '../../core/api_client.dart';
import '../../services/categories_api.dart';
import '../../services/processing_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/status_chip.dart';

/// تفاصيل إذن الصرف — mirrors Angular `ProcessingOrderDetailComponent` (read view).
class ProcessingOrderDetailScreen extends StatefulWidget {
  const ProcessingOrderDetailScreen({
    super.key,
    required this.orderId,
    this.statusLabels = const {},
  });

  final String orderId;
  final Map<String, String> statusLabels;

  @override
  State<ProcessingOrderDetailScreen> createState() =>
      _ProcessingOrderDetailScreenState();
}

class _ProcessingOrderDetailScreenState
    extends State<ProcessingOrderDetailScreen> {
  ProcessingOrderDetail? _order;
  bool _loading = true;
  String? _error;

  static const _invoiceLabels = <String, String>{
    'draft': 'مسودة',
    'posted': 'مرحّلة',
    'partially_paid': 'مدفوعة جزئياً',
    'paid': 'مدفوعة',
    'cancelled': 'ملغاة',
  };

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
      final order = await ProcessingApi.instance.getOrder(widget.orderId);
      if (!mounted) return;
      setState(() {
        _order = order;
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
        _error = 'تعذر تحميل تفاصيل الإذن';
        _loading = false;
      });
    }
  }

  String _statusLabel(String code) =>
      widget.statusLabels[code] ?? code;

  Color _statusColor(String code) {
    switch (code) {
      case 'completed':
        return AppColors.success;
      case 'partially_received':
      case 'in_progress':
        return AppColors.info;
      case 'approved':
        return AppColors.navy;
      case 'cancelled':
        return AppColors.danger;
      default:
        return AppColors.textMuted;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: Text(_order?.dispatchNumber ?? 'تفاصيل الإذن'),
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

    final o = _order!;
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
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        o.dispatchNumber,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: AppColors.navy,
                        ),
                      ),
                    ),
                    StatusChip(
                      label: _statusLabel(o.status),
                      color: _statusColor(o.status),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                _kv('المورد', o.supplierName),
                _kv('المبلغ على الذمة', Formatters.money(o.amount)),
                if (o.supplierBalance != null)
                  _kv('رصيد المورد', Formatters.money(o.supplierBalance!)),
                if (o.dispatchDate != null) _kv('تاريخ الصرف', o.dispatchDate!),
                if (o.notes != null && o.notes!.isNotEmpty)
                  _kv('ملاحظات', o.notes!),
              ],
            ),
          ),
          const SizedBox(height: 14),
          _sectionTitle('بنود الصرف'),
          if (o.lines.isEmpty)
            _emptyBox('لا توجد بنود')
          else
            for (final line in o.lines) ...[
              _card(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      line.categoryName,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: AppColors.navy,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 6,
                      children: [
                        _miniStat('مصروف', Formatters.moneyPlain(line.dispatchedQty)),
                        _miniStat('مستلم', Formatters.moneyPlain(line.receivedGoodQty)),
                        _miniStat('هالك', Formatters.moneyPlain(line.receivedDamagedQty)),
                        _miniStat('لدى المورد', Formatters.moneyPlain(line.atVendorQty)),
                        _miniStat(
                          'المبلغ',
                          Formatters.moneyPlain(line.expectedServiceAmount),
                          unit: 'EGP',
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
            ],
          const SizedBox(height: 8),
          _sectionTitle('إذونات الاستلام'),
          if (o.receipts.isEmpty)
            _emptyBox('لا توجد إذونات استلام بعد')
          else
            for (final rcpt in o.receipts) ...[
              _card(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'تاريخ الاستلام: ${rcpt.receiptDate}',
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    if (rcpt.status.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        'الحالة: ${rcpt.status}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                    const SizedBox(height: 8),
                    for (final rl in rcpt.lines) ...[
                      Container(
                        margin: const EdgeInsets.only(bottom: 6),
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: AppColors.bg,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(
                              '${rl.categoryName} ← ${rl.destinationName}',
                              style: const TextStyle(fontWeight: FontWeight.w600),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'جيّد: ${Formatters.moneyPlain(rl.goodQty)} · هالك: ${Formatters.moneyPlain(rl.damagedQty)}',
                              style: const TextStyle(
                                fontSize: 12,
                                color: AppColors.textSecondary,
                              ),
                            ),
                            if (rl.resultingUnitCost != null)
                              Text(
                                'تكلفة الوحدة بعد الاستلام: ${Formatters.moneyPlain(rl.resultingUnitCost!)}',
                                style: const TextStyle(
                                  fontSize: 12,
                                  color: AppColors.textSecondary,
                                ),
                              ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 8),
            ],
          const SizedBox(height: 8),
          _ReceiptPanel(
            key: ValueKey(
              '${o.id}-${o.status}-${o.lines.map((l) => l.atVendorQty).join(',')}',
            ),
            order: o,
            onPosted: _load,
          ),
          const SizedBox(height: 8),
          _sectionTitle('الفواتير'),
          if (o.invoices.isEmpty)
            _emptyBox('لا توجد فواتير')
          else
            for (final inv in o.invoices) ...[
              _card(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            inv.invoiceNumber,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              color: AppColors.navy,
                            ),
                          ),
                        ),
                        StatusChip(
                          label: _invoiceLabels[inv.status] ?? inv.status,
                          color: inv.status == 'paid'
                              ? AppColors.success
                              : inv.status == 'cancelled'
                                  ? AppColors.danger
                                  : AppColors.info,
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    _kv('الإجمالي', Formatters.money(inv.grandTotal)),
                    _kv('المدفوع', Formatters.money(inv.paidAmount)),
                    _kv('المتبقي', Formatters.money(inv.dueAmount)),
                  ],
                ),
              ),
              const SizedBox(height: 8),
            ],
        ],
      ),
    );
  }

  Widget _sectionTitle(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(
        text,
        style: const TextStyle(
          fontSize: 15,
          fontWeight: FontWeight.w800,
          color: AppColors.navy,
        ),
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

  Widget _emptyBox(String text) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Text(
        text,
        textAlign: TextAlign.center,
        style: const TextStyle(color: AppColors.textSecondary),
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
          Text(
            v,
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }

  Widget _miniStat(String label, String value, {String? unit}) {
    return Container(
      width: 96,
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
      decoration: BoxDecoration(
        color: AppColors.bg,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(fontSize: 10, color: AppColors.textSecondary),
          ),
          const SizedBox(height: 2),
          Text(
            value,
            style: const TextStyle(
              fontWeight: FontWeight.w800,
              fontSize: 13,
              color: AppColors.navy,
            ),
          ),
          if (unit != null)
            Text(
              unit,
              style: const TextStyle(fontSize: 10, color: AppColors.textMuted),
            ),
        ],
      ),
    );
  }
}

class _ReceiptDraft {
  _ReceiptDraft({
    required this.line,
    required this.atVendor,
  })  : receivedCtrl = TextEditingController(text: _fmt(atVendor)),
        wasteCtrl = TextEditingController(text: '0');

  final ProcessingOrderLine line;
  final double atVendor;
  final TextEditingController receivedCtrl;
  final TextEditingController wasteCtrl;
  CategoryItem? destination;
  bool addNew = false;
  String newProductName = '';
  String costMode = 'weighted_average';

  double get received => double.tryParse(receivedCtrl.text.trim()) ?? 0;
  double get waste => double.tryParse(wasteCtrl.text.trim()) ?? 0;
  double get remaining => (atVendor - received - waste).clamp(0, atVendor);

  double get suggestedUnitCost {
    final qty = received;
    if (qty <= 0) return 0;
    final mat = (line.unitMaterialCost ?? 0) * (qty + waste);
    final ordered = line.orderedQty;
    final service = ordered > 0
        ? (line.expectedServiceAmount * qty) / ordered
        : 0.0;
    return (mat + service) / qty;
  }

  double get serviceCost {
    final qty = received;
    final ordered = line.orderedQty;
    if (ordered <= 0) return 0;
    return (line.expectedServiceAmount * qty) / ordered;
  }

  void dispose() {
    receivedCtrl.dispose();
    wasteCtrl.dispose();
  }

  static String _fmt(double n) {
    if (n == n.roundToDouble()) return n.toStringAsFixed(0);
    return n.toString();
  }
}

class _ReceiptPanel extends StatefulWidget {
  const _ReceiptPanel({
    super.key,
    required this.order,
    required this.onPosted,
  });

  final ProcessingOrderDetail order;
  final Future<void> Function() onPosted;

  @override
  State<_ReceiptPanel> createState() => _ReceiptPanelState();
}

class _ReceiptPanelState extends State<_ReceiptPanel> {
  final _drafts = <_ReceiptDraft>[];
  List<CategoryItem> _products = [];
  DateTime _date = DateTime.now();
  bool _busy = false;
  bool _loadingProducts = true;

  @override
  void initState() {
    super.initState();
    _rebuildDrafts();
    _loadProducts();
  }

  @override
  void didUpdateWidget(covariant _ReceiptPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.order.id != widget.order.id ||
        oldWidget.order.lines.length != widget.order.lines.length) {
      _rebuildDrafts();
    }
  }

  void _rebuildDrafts() {
    for (final d in _drafts) {
      d.dispose();
    }
    _drafts.clear();
    for (final line in widget.order.lines) {
      if (line.atVendorQty <= 0.000001) continue;
      _drafts.add(_ReceiptDraft(line: line, atVendor: line.atVendorQty));
    }
  }

  Future<void> _loadProducts() async {
    try {
      final rows = await CategoriesApi.instance.allCategories();
      if (!mounted) return;
      setState(() {
        _products = rows;
        _loadingProducts = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _loadingProducts = false);
    }
  }

  @override
  void dispose() {
    for (final d in _drafts) {
      d.dispose();
    }
    super.dispose();
  }

  Future<void> _pickDest(_ReceiptDraft d) async {
    var q = '';
    final chosen = await showModalBottomSheet<CategoryItem>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        return Directionality(
          textDirection: TextDirection.rtl,
          child: StatefulBuilder(
            builder: (ctx, setLocal) {
              final needle = q.trim().toLowerCase();
              final filtered = needle.isEmpty
                  ? _products.take(80).toList()
                  : _products.where((c) {
                      return c.name.toLowerCase().contains(needle) ||
                          (c.itemCode ?? '').toLowerCase().contains(needle) ||
                          (c.warehouse ?? '').toLowerCase().contains(needle);
                    }).take(80).toList();
              return SafeArea(
                child: SizedBox(
                  height: MediaQuery.of(ctx).size.height * 0.75,
                  child: Column(
                    children: [
                      const Padding(
                        padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
                        child: Text(
                          'صنف الاستلام (المنتج المجهز)',
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            color: AppColors.navy,
                          ),
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        child: TextField(
                          autofocus: true,
                          onChanged: (v) => setLocal(() => q = v),
                          decoration: InputDecoration(
                            hintText: _loadingProducts
                                ? 'جاري تحميل الأصناف…'
                                : 'ابحث واختر الصنف المجهز…',
                            prefixIcon: const Icon(Icons.search),
                            isDense: true,
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Expanded(
                        child: ListView.builder(
                          itemCount: filtered.length,
                          itemBuilder: (_, i) {
                            final c = filtered[i];
                            return ListTile(
                              title: Text(c.name),
                              subtitle: Text(
                                [
                                  if (c.warehouse != null) c.warehouse!,
                                  if (c.itemCode != null) c.itemCode!,
                                  'رصيد: ${Formatters.moneyPlain(c.quantity)}',
                                ].join(' · '),
                              ),
                              onTap: () => Navigator.pop(ctx, c),
                            );
                          },
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        );
      },
    );
    if (chosen == null) return;
    setState(() {
      d.destination = chosen;
      d.addNew = false;
      d.newProductName = '';
    });
  }

  Future<void> _submit() async {
    final lines = _drafts.where((d) => d.received > 0 || d.waste > 0).toList();
    if (lines.isEmpty) {
      _toast('أدخل كميات الاستلام');
      return;
    }
    if (lines.any((d) => d.received <= 0)) {
      _toast('أدخل الكمية المستلمة لكل سطر (الهالك وحده غير كافٍ)');
      return;
    }
    if (lines.any((d) => d.received + d.waste > d.atVendor + 0.000001)) {
      _toast('المستلم + الهالك أكبر من الكمية لدى المورد');
      return;
    }
    if (lines.any((d) => !d.addNew && d.destination == null)) {
      _toast('اختر صنف الاستلام (المنتج المجهز) لكل سطر');
      return;
    }
    if (lines.any((d) => d.addNew && d.newProductName.trim().isEmpty)) {
      _toast('أدخل اسم المنتج الجديد للصنف المجهز');
      return;
    }

    setState(() => _busy = true);
    try {
      await ProcessingApi.instance.createAndPostReceipt(
        orderId: widget.order.id,
        receiptDate: DateFormat('yyyy-MM-dd').format(_date),
        lines: [
          for (final d in lines)
            {
              'processing_order_line_id':
                  int.tryParse(d.line.id) ?? d.line.id,
              'received_qty': d.received,
              'good_qty': d.received,
              'damaged_qty': d.waste,
              'received_total_cost': d.serviceCost,
              'dest_unit_cost': d.suggestedUnitCost,
              'cost_apply_mode': d.addNew ? 'overwrite' : d.costMode,
              'destination_category_id': d.addNew
                  ? null
                  : (int.tryParse(d.destination!.id) ?? d.destination!.id),
              'new_product_name':
                  d.addNew ? d.newProductName.trim() : null,
            },
        ],
      );
      if (!mounted) return;
      _toast('تم ترحيل إذن الاستلام');
      await widget.onPosted();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      _toast(e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      _toast('فشل ترحيل الاستلام');
    }
  }

  void _toast(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  @override
  Widget build(BuildContext context) {
    if (_drafts.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'تسجيل استلام',
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w800,
            color: AppColors.navy,
          ),
        ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: () async {
            final picked = await showDatePicker(
              context: context,
              initialDate: _date,
              firstDate: DateTime(2020),
              lastDate: DateTime.now().add(const Duration(days: 1)),
              locale: const Locale('ar'),
            );
            if (picked == null) return;
            setState(() => _date = picked);
          },
          icon: const Icon(Icons.calendar_month_outlined),
          label: Text('تاريخ الاستلام: ${DateFormat('yyyy-MM-dd').format(_date)}'),
        ),
        const SizedBox(height: 8),
        for (final d in _drafts) _draftCard(d),
        const SizedBox(height: 8),
        FilledButton(
          onPressed: _busy ? null : _submit,
          style: FilledButton.styleFrom(
            backgroundColor: AppColors.primary,
            minimumSize: const Size.fromHeight(46),
          ),
          child: Text(_busy ? 'جاري الترحيل...' : 'حفظ وترحيل إذن الاستلام'),
        ),
      ],
    );
  }

  Widget _draftCard(_ReceiptDraft d) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            d.line.categoryName,
            style: const TextStyle(
              fontWeight: FontWeight.w800,
              color: AppColors.navy,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'لدى المورد: ${Formatters.moneyPlain(d.atVendor)} · متبقي: ${Formatters.moneyPlain(d.remaining)}',
            style: const TextStyle(
              fontSize: 12,
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: 8),
          if (!d.addNew) ...[
            OutlinedButton(
              onPressed: () => _pickDest(d),
              child: Text(
                d.destination?.name ?? 'اختر صنف الاستلام (المنتج المجهز)',
                overflow: TextOverflow.ellipsis,
              ),
            ),
            TextButton(
              onPressed: () => setState(() {
                d.addNew = true;
                d.destination = null;
                d.costMode = 'overwrite';
              }),
              child: const Text('+ منتج جديد'),
            ),
          ] else ...[
            TextField(
              onChanged: (v) => d.newProductName = v,
              decoration: const InputDecoration(
                hintText: 'اسم المنتج الجديد',
                isDense: true,
                border: OutlineInputBorder(),
              ),
            ),
            TextButton(
              onPressed: () => setState(() {
                d.addNew = false;
                d.newProductName = '';
                d.costMode = 'weighted_average';
              }),
              child: const Text('اختيار صنف موجود'),
            ),
          ],
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: d.receivedCtrl,
                  keyboardType:
                      const TextInputType.numberWithOptions(decimal: true),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'الكمية المستلمة',
                    isDense: true,
                    border: OutlineInputBorder(),
                  ),
                  onChanged: (_) => setState(() {}),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: TextField(
                  controller: d.wasteCtrl,
                  keyboardType:
                      const TextInputType.numberWithOptions(decimal: true),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'الهالك',
                    isDense: true,
                    border: OutlineInputBorder(),
                  ),
                  onChanged: (_) => setState(() {}),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            'تكلفة الوحدة المقترحة: ${Formatters.moneyPlain(d.suggestedUnitCost)}',
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: AppColors.primary,
            ),
          ),
          if (!d.addNew)
            Wrap(
              spacing: 8,
              children: [
                ChoiceChip(
                  label: const Text('متوسط مرجّح'),
                  selected: d.costMode == 'weighted_average',
                  onSelected: (_) =>
                      setState(() => d.costMode = 'weighted_average'),
                ),
                ChoiceChip(
                  label: const Text('استبدال التكلفة'),
                  selected: d.costMode == 'overwrite',
                  onSelected: (_) => setState(() => d.costMode = 'overwrite'),
                ),
              ],
            ),
        ],
      ),
    );
  }
}

