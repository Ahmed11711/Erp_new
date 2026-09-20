import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart' hide TextDirection;

import '../../core/api_client.dart';
import '../../services/categories_api.dart';
import '../../services/processing_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import 'processing_order_detail_screen.dart';

/// إنشاء إذن صرف وترحيله — mirrors Angular `openForm` + `save`.
class ProcessingDispatchFormScreen extends StatefulWidget {
  const ProcessingDispatchFormScreen({super.key});

  @override
  State<ProcessingDispatchFormScreen> createState() =>
      _ProcessingDispatchFormScreenState();
}

class _LineDraft {
  _LineDraft()
      : qtyCtrl = TextEditingController(),
        amountCtrl = TextEditingController(text: '0');

  final TextEditingController qtyCtrl;
  final TextEditingController amountCtrl;
  String? categoryId;
  String categoryLabel = '';
  double availableQty = 0;

  double get qty => double.tryParse(qtyCtrl.text.trim()) ?? 0;
  double get amount => double.tryParse(amountCtrl.text.trim()) ?? 0;

  void dispose() {
    qtyCtrl.dispose();
    amountCtrl.dispose();
  }
}

class _ProcessingDispatchFormScreenState
    extends State<ProcessingDispatchFormScreen> {
  final _notesCtrl = TextEditingController();
  final _extRepCtrl = TextEditingController();

  ProcessingMeta _meta = ProcessingMeta.fromJson({});
  List<ProcessingVendor> _vendors = [];
  List<({String id, String name})> _reps = [];
  final _lines = <_LineDraft>[_LineDraft()];

  ProcessingVendor? _vendor;
  String _dispatchType = 'goods_to_supplier';
  DateTime _date = DateTime.now();
  String? _repType;
  String? _repId;
  String _repName = '';

  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void dispose() {
    _notesCtrl.dispose();
    _extRepCtrl.dispose();
    for (final l in _lines) {
      l.dispose();
    }
    super.dispose();
  }

  Future<void> _bootstrap() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        ProcessingApi.instance.meta(),
        ProcessingApi.instance.listVendors(),
        ProcessingApi.instance.listRepresentatives(),
      ]);
      if (!mounted) return;
      setState(() {
        _meta = results[0] as ProcessingMeta;
        _vendors = results[1] as List<ProcessingVendor>;
        _reps = results[2] as List<({String id, String name})>;
        if (_meta.dispatchTypes.isNotEmpty) {
          _dispatchType = _meta.dispatchTypes.first.value;
        }
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
        _error = 'تعذر تحميل بيانات النموذج';
        _loading = false;
      });
    }
  }

  double get _totalQty => _lines.fold<double>(0, (s, l) => s + l.qty);
  double get _totalAmount => _lines.fold<double>(0, (s, l) => s + l.amount);

  void _addLine() => setState(() => _lines.add(_LineDraft()));

  void _removeLine(int i) {
    if (_lines.length <= 1) return;
    setState(() {
      _lines.removeAt(i).dispose();
    });
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 1)),
      locale: const Locale('ar'),
    );
    if (picked == null) return;
    setState(() => _date = picked);
  }

  Future<void> _pickVendor() async {
    final chosen = await _showSearchSheet<ProcessingVendor>(
      title: 'يصرف إلى (المورد)',
      hint: 'ابحث عن المورد…',
      items: _vendors,
      labelOf: (v) => v.name,
      subtitleOf: (v) => v.typeName,
    );
    if (chosen == null) return;
    setState(() => _vendor = chosen);
  }

  Future<void> _pickRep() async {
    final chosen = await _showSearchSheet<({String id, String name})>(
      title: 'مندوب بالنظام',
      hint: 'ابحث عن المندوب…',
      items: _reps,
      labelOf: (r) => r.name,
    );
    if (chosen == null) return;
    setState(() {
      _repId = chosen.id;
      _repName = chosen.name;
    });
  }

  Future<void> _pickCategory(_LineDraft line) async {
    final chosen = await showModalBottomSheet<CategoryItem>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => const _RawCategorySearchSheet(),
    );
    if (chosen == null) return;
    setState(() {
      line.categoryId = chosen.id;
      line.categoryLabel = chosen.name;
      line.availableQty = chosen.quantity;
    });
  }

  Future<T?> _showSearchSheet<T>({
    required String title,
    required String hint,
    required List<T> items,
    required String Function(T) labelOf,
    String? Function(T)? subtitleOf,
  }) {
    return showModalBottomSheet<T>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        var q = '';
        return Directionality(
          textDirection: TextDirection.rtl,
          child: StatefulBuilder(
            builder: (ctx, setLocal) {
              final filtered = items.where((e) {
                if (q.trim().isEmpty) return true;
                final name = labelOf(e).toLowerCase();
                final sub = (subtitleOf?.call(e) ?? '').toLowerCase();
                final needle = q.trim().toLowerCase();
                return name.contains(needle) || sub.contains(needle);
              }).toList();
              return SafeArea(
                child: SizedBox(
                  height: MediaQuery.of(ctx).size.height * 0.7,
                  child: Column(
                    children: [
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                        child: Text(
                          title,
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 16,
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
                            hintText: hint,
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
                        child: filtered.isEmpty
                            ? const Center(child: Text('لا توجد نتائج'))
                            : ListView.builder(
                                itemCount: filtered.length,
                                itemBuilder: (_, i) {
                                  final item = filtered[i];
                                  final sub = subtitleOf?.call(item);
                                  return ListTile(
                                    title: Text(labelOf(item)),
                                    subtitle: sub == null || sub.isEmpty
                                        ? null
                                        : Text(sub),
                                    onTap: () => Navigator.pop(ctx, item),
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
  }

  Future<void> _save() async {
    if (_vendor == null) {
      _toast('اختر المورد (يصرف إلى)');
      return;
    }
    if (_repType == 'internal' && (_repId == null || _repId!.isEmpty)) {
      _toast('اختر مندوباً من قائمة المناديب');
      return;
    }
    if (_repType == 'external' && _extRepCtrl.text.trim().isEmpty) {
      _toast('أدخل اسم المندوب الخارجي');
      return;
    }
    final valid = _lines
        .where((l) => l.categoryId != null && l.qty > 0)
        .toList();
    if (valid.isEmpty) {
      _toast('أضف صنفاً واحداً على الأقل مع كمية أكبر من صفر');
      return;
    }

    setState(() => _saving = true);
    try {
      final result = await ProcessingApi.instance.submitDispatchVoucher(
        supplierId: _vendor!.id,
        dispatchDate: DateFormat('yyyy-MM-dd').format(_date),
        dispatchType: _dispatchType,
        notes: _notesCtrl.text,
        representativeType: _repType,
        shippingCompanyId: _repType == 'internal' ? _repId : null,
        externalRepresentativeName:
            _repType == 'external' ? _extRepCtrl.text : null,
        expectedServiceTotal: _totalAmount,
        lines: [
          for (final l in valid)
            {
              'category_id': int.tryParse(l.categoryId!) ?? l.categoryId,
              'ordered_qty': l.qty,
              'expected_service_amount': l.amount,
            },
        ],
      );
      if (!mounted) return;
      final no = result.dispatchNumber;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            no.isEmpty
                ? 'تم ترحيل إذن الصرف وإضافة المبلغ لذمة المورد'
                : 'تم ترحيل إذن الصرف $no وإضافة المبلغ لذمة المورد',
          ),
        ),
      );
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => ProcessingOrderDetailScreen(
            orderId: result.orderId,
            statusLabels: _meta.orderStatuses,
          ),
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _saving = false);
      _toast(e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      _toast('فشل الحفظ');
    }
  }

  void _toast(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  InputDecoration _dec({String? hint}) {
    return InputDecoration(
      hintText: hint,
      isDense: true,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('إذن صرف جديد'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : _error != null
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(_error!, textAlign: TextAlign.center),
                          const SizedBox(height: 12),
                          FilledButton(
                            onPressed: _bootstrap,
                            child: const Text('إعادة المحاولة'),
                          ),
                        ],
                      ),
                    ),
                  )
                : ListView(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
                    children: [
                      if (_meta.nextDispatchNumber != null)
                        Text(
                          'رقم الفاتورة: ${_meta.nextDispatchNumber}',
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            color: AppColors.navy,
                          ),
                        ),
                      const SizedBox(height: 12),
                      const Text(
                        'نوع الصرف',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          for (final t in _meta.dispatchTypes)
                            ChoiceChip(
                              label: Text(t.label),
                              selected: _dispatchType == t.value,
                              onSelected: (_) =>
                                  setState(() => _dispatchType = t.value),
                            ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      const Text(
                        'يصرف إلى (المورد) *',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 6),
                      OutlinedButton.icon(
                        onPressed: _pickVendor,
                        icon: const Icon(Icons.person_outline),
                        label: Text(
                          _vendor?.name ?? 'ابحث عن أي مورد…',
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(height: 12),
                      const Text(
                        'التاريخ',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 6),
                      OutlinedButton.icon(
                        onPressed: _pickDate,
                        icon: const Icon(Icons.calendar_month_outlined),
                        label: Text(DateFormat('yyyy-MM-dd').format(_date)),
                      ),
                      const SizedBox(height: 14),
                      const Text(
                        'مندوب الشحن',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 8,
                        children: [
                          ChoiceChip(
                            label: const Text('مندوب بالنظام'),
                            selected: _repType == 'internal',
                            onSelected: (_) => setState(() {
                              _repType = 'internal';
                              _extRepCtrl.clear();
                            }),
                          ),
                          ChoiceChip(
                            label: const Text('مندوب خارجي'),
                            selected: _repType == 'external',
                            onSelected: (_) => setState(() {
                              _repType = 'external';
                              _repId = null;
                              _repName = '';
                            }),
                          ),
                          ChoiceChip(
                            label: const Text('بدون'),
                            selected: _repType == null,
                            onSelected: (_) => setState(() {
                              _repType = null;
                              _repId = null;
                              _repName = '';
                              _extRepCtrl.clear();
                            }),
                          ),
                        ],
                      ),
                      if (_repType == 'internal') ...[
                        const SizedBox(height: 8),
                        OutlinedButton.icon(
                          onPressed: _pickRep,
                          icon: const Icon(Icons.badge_outlined),
                          label: Text(
                            _repName.isEmpty
                                ? 'اختر مندوباً من قائمة المناديب…'
                                : _repName,
                          ),
                        ),
                      ],
                      if (_repType == 'external') ...[
                        const SizedBox(height: 8),
                        TextField(
                          controller: _extRepCtrl,
                          decoration: _dec(hint: 'اسم المندوب الخارجي'),
                        ),
                      ],
                      const SizedBox(height: 18),
                      const Text(
                        'بنود الصرف',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 16,
                          color: AppColors.navy,
                        ),
                      ),
                      const SizedBox(height: 8),
                      for (var i = 0; i < _lines.length; i++)
                        _lineCard(_lines[i], i),
                      TextButton.icon(
                        onPressed: _addLine,
                        icon: const Icon(Icons.add),
                        label: const Text('إضافة سطر'),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'الإجمالي: ${Formatters.moneyPlain(_totalQty)} · ${Formatters.money(_totalAmount)}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          color: AppColors.primary,
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _notesCtrl,
                        maxLines: 2,
                        decoration: _dec(hint: 'ملاحظات (اختياري)'),
                      ),
                    ],
                  ),
        bottomNavigationBar: _loading || _error != null
            ? null
            : SafeArea(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                  child: FilledButton(
                    onPressed: _saving ? null : _save,
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      minimumSize: const Size.fromHeight(48),
                    ),
                    child: Text(
                      _saving ? 'جاري الترحيل...' : 'حفظ وترحيل إذن الصرف',
                    ),
                  ),
                ),
              ),
      ),
    );
  }

  Widget _lineCard(_LineDraft line, int index) {
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
          Row(
            children: [
              Expanded(
                child: Text(
                  line.categoryLabel.isEmpty
                      ? 'اختر الصنف (مواد خام)'
                      : line.categoryLabel,
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    color: line.categoryId == null
                        ? AppColors.textMuted
                        : AppColors.navy,
                  ),
                ),
              ),
              if (_lines.length > 1)
                IconButton(
                  onPressed: () => _removeLine(index),
                  icon: const Icon(Icons.close, color: AppColors.danger),
                ),
            ],
          ),
          OutlinedButton(
            onPressed: () => _pickCategory(line),
            child: Text(
              line.categoryId == null ? 'بحث باسم الصنف…' : 'تغيير الصنف',
            ),
          ),
          if (line.categoryId != null)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(
                'متاح: ${Formatters.moneyPlain(line.availableQty)}',
                style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textSecondary,
                ),
              ),
            ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: line.qtyCtrl,
                  keyboardType:
                      const TextInputType.numberWithOptions(decimal: true),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                  ],
                  decoration: _dec(hint: 'الكمية'),
                  onChanged: (_) => setState(() {}),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: TextField(
                  controller: line.amountCtrl,
                  keyboardType:
                      const TextInputType.numberWithOptions(decimal: true),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                  ],
                  decoration: _dec(hint: 'المبلغ'),
                  onChanged: (_) => setState(() {}),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _RawCategorySearchSheet extends StatefulWidget {
  const _RawCategorySearchSheet();

  @override
  State<_RawCategorySearchSheet> createState() =>
      _RawCategorySearchSheetState();
}

class _RawCategorySearchSheetState extends State<_RawCategorySearchSheet> {
  final _ctrl = TextEditingController();
  Timer? _debounce;
  List<CategoryItem> _items = [];
  bool _loading = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _ctrl.dispose();
    super.dispose();
  }

  Future<void> _search(String q) async {
    final query = q.trim();
    if (query.isEmpty) {
      setState(() => _items = []);
      return;
    }
    setState(() => _loading = true);
    try {
      final page = await CategoriesApi.instance.search(
        categoryName: query,
        warehouse: 'مخزن مواد خام',
        itemsPerPage: 50,
      );
      if (!mounted) return;
      setState(() {
        _items = page.items;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _items = [];
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: SafeArea(
        child: SizedBox(
          height: MediaQuery.of(context).size.height * 0.75,
          child: Column(
            children: [
              const Padding(
                padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
                child: Text(
                  'بحث في مخزن المواد الخام',
                  style: TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                    color: AppColors.navy,
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: TextField(
                  controller: _ctrl,
                  autofocus: true,
                  onChanged: (v) {
                    _debounce?.cancel();
                    _debounce = Timer(
                      const Duration(milliseconds: 300),
                      () => _search(v),
                    );
                  },
                  decoration: InputDecoration(
                    hintText: 'اكتب اسم الصنف…',
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
                child: _loading
                    ? const Center(child: CircularProgressIndicator())
                    : _items.isEmpty
                        ? const Center(
                            child: Text('لا يوجد صنف — اكتب اسم الصنف للبحث'),
                          )
                        : ListView.builder(
                            itemCount: _items.length,
                            itemBuilder: (_, i) {
                              final c = _items[i];
                              return ListTile(
                                title: Text(c.name),
                                subtitle: Text(
                                  'متاح: ${Formatters.moneyPlain(c.quantity)}'
                                  '${c.itemCode != null ? ' · ${c.itemCode}' : ''}',
                                ),
                                onTap: () => Navigator.pop(context, c),
                              );
                            },
                          ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
