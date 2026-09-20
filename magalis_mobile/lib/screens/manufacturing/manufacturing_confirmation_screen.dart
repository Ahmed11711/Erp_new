import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api_client.dart';
import '../../services/manufacturing_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import 'manufacturing_orders_screen.dart';
import 'product_search_sheet.dart';

const _kWip = 'مخزن منتج تحت التشغيل';
const _kFinished = 'مخزن منتج تام';
const _kRaw = 'مخزن مواد خام';

/// تأكيد أمر التصنيع — mirrors Angular `ManufacturingConfirmationComponent`.
class ManufacturingConfirmationScreen extends StatefulWidget {
  const ManufacturingConfirmationScreen({super.key});

  @override
  State<ManufacturingConfirmationScreen> createState() =>
      _ManufacturingConfirmationScreenState();
}

class _ManufacturingConfirmationScreenState
    extends State<ManufacturingConfirmationScreen> {
  final _qtyCtrl = TextEditingController(text: '1');
  String _warehouse = _kFinished;
  String _status = 'تم الانتهاء';
  String _wipMode = 'auto';
  bool _wipKeep = false;
  bool _updateRecipeOnConfirm = false;

  List<ManufactureProductOption> _products = [];
  List<ManufactureProductOption> _mergeProducts = [];
  List<ManufactureProductOption> _substitutes = [];
  ManufactureProductOption? _product;
  ManufactureProductOption? _mergeTarget;
  double? _costOverride;
  List<ManufactureAdditionItem> _additions = [];
  ManufactureAdditionItem? _addition;

  DateTime _date = DateTime.now();
  bool _loadingProducts = true;
  bool _submitting = false;
  bool _previewPending = false;
  bool _updatingRecipe = false;
  String? _error;

  bool _consumptionApplies = false;
  List<ConsumptionLine> _lines = [];
  double _consumptionTotal = 0;
  bool _allSufficient = true;
  String? _consumptionMessage;
  Timer? _previewTimer;
  double _lastQty = 1;

  double get _qty => double.tryParse(_qtyCtrl.text.trim()) ?? 0;
  double get _unitCost => _costOverride ?? _product?.cost ?? 0;
  double get _total =>
      _unitCost * (_qty <= 0 ? 0 : _qty) + (_addition?.cost ?? 0);
  double get _recipeBasedTotal => _unitCost * (_qty <= 0 ? 0 : _qty);
  double get _costDiff => _consumptionTotal - _recipeBasedTotal;
  bool get _hasCustomized => _lines.any((e) => e.isCustomized);

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void dispose() {
    _previewTimer?.cancel();
    _qtyCtrl.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    setState(() => _loadingProducts = true);
    try {
      final products = await ManufacturingApi.instance.productsByWarehouse(_warehouse);
      final merge = await ManufacturingApi.instance.productsByWarehouse(
        _kFinished,
        allCategories: true,
      );
      final raw = await ManufacturingApi.instance.productsByWarehouse(
        _kRaw,
        allCategories: true,
      );
      final wip = await ManufacturingApi.instance.productsByWarehouse(
        _kWip,
        allCategories: true,
      );
      List<ManufactureAdditionItem> additions = [];
      try {
        additions = await ManufacturingApi.instance.listAdditions();
      } catch (_) {}
      final seen = <String>{};
      final subs = <ManufactureProductOption>[];
      for (final item in [...raw, ...wip]) {
        if (seen.add(item.id)) subs.add(item);
      }
      if (!mounted) return;
      setState(() {
        _products = products;
        _mergeProducts = merge;
        _substitutes = subs;
        _additions = additions;
        _loadingProducts = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _loadingProducts = false;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingProducts = false;
        _error = 'تعذر تحميل المنتجات';
      });
    }
  }

  Future<void> _changeWarehouse(String warehouse) async {
    setState(() {
      _warehouse = warehouse;
      _product = null;
      _costOverride = null;
      _mergeTarget = null;
      _wipKeep = false;
      _lines = [];
      _consumptionApplies = false;
      _error = null;
    });
    try {
      final products =
          await ManufacturingApi.instance.productsByWarehouse(warehouse);
      if (!mounted) return;
      setState(() => _products = products);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    }
  }

  void _schedulePreview([int ms = 350]) {
    _previewTimer?.cancel();
    _previewTimer = Timer(Duration(milliseconds: ms), _loadPreview);
  }

  void _onQtyChanged() {
    final next = _qty;
    if (!_hasCustomized) {
      _lines = [];
    } else if (_lastQty > 0 && next > 0 && (next - _lastQty).abs() > 0.000001) {
      final ratio = next / _lastQty;
      for (final line in _lines) {
        line.quantity =
            double.parse((line.quantity * ratio).toStringAsFixed(6));
        line.sufficient = line.availableQuantity + 0.00001 >= line.quantity;
        line.isCustomized = line.isSubstituted ||
            (line.quantity - line.defaultQuantity).abs() > 0.000001;
      }
    }
    if (next > 0) {
      _lastQty = next;
    }
    setState(() {});
    _schedulePreview();
  }

  Future<void> _loadPreview() async {
    final product = _product;
    if (product == null || _qty <= 0) {
      setState(() {
        _lines = [];
        _consumptionApplies = false;
        _consumptionMessage = null;
      });
      return;
    }
    setState(() {
      _previewPending = true;
      _error = null;
    });
    try {
      final preview = await ManufacturingApi.instance.previewConsumption(
        productId: product.id,
        quantity: _qty,
        status: _status,
        wipKeepUnderProcessing: _wipKeep,
        consumptionLines: _hasCustomized
            ? [for (final l in _lines) l.toPayload()]
            : null,
      );
      if (!mounted) return;
      setState(() {
        _consumptionApplies = preview.applies;
        _lines = preview.lines;
        _consumptionTotal = preview.totalCost;
        _allSufficient = preview.allSufficient;
        _consumptionMessage = preview.message;
        _previewPending = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _previewPending = false;
        _lines = [];
        _consumptionApplies = false;
        _consumptionMessage = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _previewPending = false;
        _consumptionMessage = 'تعذر تحميل مواد الاستهلاك.';
      });
    }
  }

  Future<void> _pickProduct() async {
    final picked = await showProductSearchSheet(
      context: context,
      title: 'اسم المنتج (وصفة)',
      items: _products,
    );
    if (picked == null || !mounted) return;
    setState(() {
      _product = picked;
      _costOverride = null;
      _lines = [];
    });
    _schedulePreview();
  }

  Future<void> _pickMerge() async {
    final picked = await showProductSearchSheet(
      context: context,
      title: 'صنف المنتج التام (الهدف)',
      items: _mergeProducts,
    );
    if (picked == null || !mounted) return;
    setState(() => _mergeTarget = picked);
  }

  Future<void> _substitute(ConsumptionLine line) async {
    final picked = await showProductSearchSheet(
      context: context,
      title: 'استبدال المادة',
      items: _substitutes,
    );
    if (picked == null || !mounted) return;
    setState(() {
      line.resolvedCategoryId = int.tryParse(picked.id) ?? line.resolvedCategoryId;
      line.itemName = picked.name;
      line.isSubstituted =
          line.resolvedCategoryId != (line.defaultResolvedCategoryId ?? line.resolvedCategoryId);
      line.isCustomized = line.isSubstituted ||
          (line.quantity - line.defaultQuantity).abs() > 0.000001;
    });
    _schedulePreview(200);
  }

  Future<void> _saveRecipe() async {
    final product = _product;
    if (product == null || _lines.isEmpty) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('تحديث الوصفة؟'),
        content: const Text(
          'سيتم حفظ المواد والكميات الحالية على الوصفة الأصلية. أوامر التصنيع القادمة ستستخدم هذا التعديل.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('نعم، حفظ'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => _updatingRecipe = true);
    try {
      await ManufacturingApi.instance.updateRecipeFromConsumption(
        productId: product.id,
        quantity: _qty,
        consumptionLines: [for (final l in _lines) l.toPayload()],
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تم تحديث الوصفة'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      setState(() => _lines = []);
      _schedulePreview(100);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: AppColors.danger),
      );
    } finally {
      if (mounted) setState(() => _updatingRecipe = false);
    }
  }

  Future<void> _submit() async {
    final product = _product;
    if (product == null) {
      setState(() => _error = 'اختر المنتج');
      return;
    }
    if (_qty <= 0) {
      setState(() => _error = 'أدخل الكمية');
      return;
    }
    if (_warehouse == _kWip && _status == 'تم الانتهاء' && !_wipKeep) {
      if (_wipMode == 'partial_merge' && _mergeTarget == null) {
        setState(() => _error = 'وضع «دمج في صنف تام» يتطلب اختيار صنف من مخزن المنتج التام.');
        return;
      }
      if (product.quantity > 0 && _qty > product.quantity + 0.0001) {
        setState(() => _error = 'الرصيد المتاح تحت التشغيل: ${product.quantity}');
        return;
      }
    }
    if (_consumptionApplies && !_allSufficient) {
      setState(() => _error = 'عدّل كميات الاستهلاك أو زِد مخزون المواد الخام قبل التأكيد.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final result = await ManufacturingApi.instance.confirm(
        productId: product.id,
        quantity: _qty,
        total: _total,
        status: _status,
        date:
            '${_date.year}-${_date.month.toString().padLeft(2, '0')}-${_date.day.toString().padLeft(2, '0')}',
        wipKeepUnderProcessing:
            _warehouse == _kWip && _status == 'تم الانتهاء' && _wipKeep,
        wipMode: _warehouse == _kWip && _status == 'تم الانتهاء' && !_wipKeep
            ? _wipMode
            : null,
        wipTargetProductId: _wipMode == 'partial_merge' ? _mergeTarget?.id : null,
        consumptionLines: _consumptionApplies && _hasCustomized
            ? [for (final l in _lines) l.toPayload()]
            : null,
        updateRecipe: _updateRecipeOnConfirm && _hasCustomized,
      );
      if (!mounted) return;
      final msg = result.newCategoryId != null
          ? 'تم إنشاء صنف تام جديد رقم ${result.newCategoryId}'
          : (result.stayedUnderProcessing
              ? 'تم استهلاك المواد الخام وزيادة رصيد الصنف في مخزن تحت التشغيل.'
              : 'تم تأكيد أمر التصنيع');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg), behavior: SnackBarBehavior.floating),
      );
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const ManufacturingOrdersScreen()),
        (route) => route.isFirst,
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = 'تعذر تأكيد أمر التصنيع');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  InputDecoration _dec({String? hint}) => InputDecoration(
        hintText: hint,
        filled: true,
        fillColor: AppColors.surface,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      );

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('تأكيد أمر التصنيع'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        bottomNavigationBar: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: FilledButton(
              onPressed: _submitting || _previewPending ? null : _submit,
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.primary,
                minimumSize: const Size.fromHeight(48),
              ),
              child: Text(_submitting ? 'جاري التأكيد…' : 'تأكيد'),
            ),
          ),
        ),
        body: _loadingProducts
            ? const Center(child: CircularProgressIndicator())
            : ListView(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                children: [
                  if (_error != null)
                    Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.danger.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        _error!,
                        style: const TextStyle(
                          color: AppColors.danger,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  DropdownButtonFormField<String>(
                    value: _warehouse,
                    decoration: _dec(),
                    items: const [
                      DropdownMenuItem(value: _kFinished, child: Text(_kFinished)),
                      DropdownMenuItem(value: _kWip, child: Text(_kWip)),
                    ],
                    onChanged: (v) {
                      if (v != null) _changeWarehouse(v);
                    },
                  ),
                  const SizedBox(height: 10),
                  OutlinedButton.icon(
                    onPressed: _pickProduct,
                    icon: const Icon(Icons.search),
                    label: Text(_product?.name ?? 'اسم المنتج (وصفة)'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _qtyCtrl,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                    ],
                    onChanged: (_) => _onQtyChanged(),
                    decoration: _dec(hint: 'الكمية'),
                  ),
                  if (_warehouse == _kWip && _wipKeep)
                    const Padding(
                      padding: EdgeInsets.only(top: 6),
                      child: Text(
                        'كمية الدُفعة: يُستهلك الخام من الوصفة مضروباً بهذه الكمية، وتُزاد بهذا الرقم أصناف تحت التشغيل.',
                        style: TextStyle(color: AppColors.textSecondary, fontSize: 12),
                      ),
                    ),
                  if (_warehouse == _kWip && !_wipKeep && _product != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Text(
                        'متاح للتحويل إلى تام: ${_product!.quantity}',
                        style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
                      ),
                    ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: AppColors.primaryBg,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Row(
                      children: [
                        const Text(
                          'إجمالي التكلفة',
                          style: TextStyle(fontWeight: FontWeight.w700),
                        ),
                        const Spacer(),
                        Text(
                          Formatters.moneyPlain(_total),
                          style: const TextStyle(
                            fontWeight: FontWeight.w900,
                            fontSize: 18,
                            color: AppColors.primary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (_warehouse == _kWip) ...[
                    const SizedBox(height: 12),
                    CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      value: _wipKeep,
                      onChanged: (v) {
                        setState(() {
                          _wipKeep = v ?? false;
                          if (_wipKeep) _mergeTarget = null;
                        });
                        _schedulePreview();
                      },
                      title: const Text(
                        'إنتاج وتخزين كمنتج تحت التشغيل فقط — بدون تحويل إلى منتج تام الآن',
                        style: TextStyle(fontSize: 13),
                      ),
                    ),
                    if (!_wipKeep) ...[
                      const SizedBox(height: 6),
                      const Text(
                        'استراتيجية الإغلاق إلى منتج تام',
                        style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12),
                      ),
                      const SizedBox(height: 6),
                      DropdownButtonFormField<String>(
                        value: _wipMode,
                        isExpanded: true,
                        decoration: _dec(),
                        items: const [
                          DropdownMenuItem(
                            value: 'auto',
                            child: Text('تلقائي: كمية الرصيد كاملة → نفس الصنف، وإلا صنف تام جديد'),
                          ),
                          DropdownMenuItem(
                            value: 'full_same',
                            child: Text('تحويل كامل فقط (يجب أن تساوي الكمية كل الرصيد)'),
                          ),
                          DropdownMenuItem(
                            value: 'partial_new',
                            child: Text('دائماً إنشاء صنف جديد في مخزن تام'),
                          ),
                          DropdownMenuItem(
                            value: 'partial_merge',
                            child: Text('دمج الكمية في صنف منتج تام موجود'),
                          ),
                        ],
                        onChanged: (v) => setState(() => _wipMode = v ?? 'auto'),
                      ),
                      if (_wipMode == 'partial_merge') ...[
                        const SizedBox(height: 8),
                        OutlinedButton.icon(
                          onPressed: _pickMerge,
                          icon: const Icon(Icons.search),
                          label: Text(
                            _mergeTarget?.name ?? 'اختر صنفاً من مخزن المنتج التام',
                          ),
                        ),
                      ],
                    ],
                  ],
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    value: _status,
                    decoration: _dec(),
                    items: const [
                      DropdownMenuItem(value: 'تم الانتهاء', child: Text('تم الانتهاء')),
                      DropdownMenuItem(value: 'في التصنيع', child: Text('في التصنيع')),
                    ],
                    onChanged: (v) {
                      setState(() => _status = v ?? _status);
                      _schedulePreview();
                    },
                  ),
                  const SizedBox(height: 10),
                  if (_additions.isNotEmpty)
                    OutlinedButton.icon(
                      onPressed: () async {
                        final picked = await showModalBottomSheet<ManufactureAdditionItem>(
                          context: context,
                          builder: (ctx) => Directionality(
                            textDirection: TextDirection.rtl,
                            child: SafeArea(
                              child: ListView(
                                children: [
                                  const ListTile(title: Text('اضافة خاصة للعميل')),
                                  ListTile(
                                    title: const Text('بدون إضافة'),
                                    onTap: () => Navigator.pop(
                                      ctx,
                                      const ManufactureAdditionItem(
                                        id: '',
                                        name: '',
                                        cost: 0,
                                      ),
                                    ),
                                  ),
                                  for (final a in _additions)
                                    ListTile(
                                      title: Text(a.name),
                                      subtitle: Text(
                                        [
                                          Formatters.moneyPlain(a.cost),
                                          if (a.unit != null) a.unit!,
                                        ].join(' · '),
                                      ),
                                      onTap: () => Navigator.pop(ctx, a),
                                    ),
                                ],
                              ),
                            ),
                          ),
                        );
                        if (picked == null) return;
                        setState(() =>
                            _addition = picked.id.isEmpty ? null : picked);
                      },
                      icon: const Icon(Icons.add_box_outlined),
                      label: Text(_addition?.name ?? 'اضافة خاصة للعميل'),
                    ),
                  if (_additions.isNotEmpty) const SizedBox(height: 10),
                  InkWell(
                    onTap: () async {
                      final picked = await showDatePicker(
                        context: context,
                        initialDate: _date,
                        firstDate: DateTime(2018),
                        lastDate: DateTime.now().add(const Duration(days: 365)),
                        locale: const Locale('ar'),
                      );
                      if (picked != null) setState(() => _date = picked);
                    },
                    child: InputDecorator(
                      decoration: _dec(),
                      child: Text(
                        '${_date.year}-${_date.month.toString().padLeft(2, '0')}-${_date.day.toString().padLeft(2, '0')}',
                      ),
                    ),
                  ),
                  if (_product != null) ...[
                    const SizedBox(height: 16),
                    _consumptionCard(),
                  ],
                ],
              ),
      ),
    );
  }

  Widget _consumptionCard() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'مواد الخام المطلوب خصمها',
            style: TextStyle(
              fontWeight: FontWeight.w800,
              color: AppColors.navy,
            ),
          ),
          if (_previewPending)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text('جاري الحساب…', style: TextStyle(color: AppColors.textMuted)),
            ),
          if (!_previewPending && _consumptionMessage != null && !_consumptionApplies)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(_consumptionMessage!, style: const TextStyle(color: AppColors.info)),
            ),
          if (_consumptionApplies && _lines.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              '${_lines.length} مادة — إجمالي ${Formatters.moneyPlain(_consumptionTotal)}'
              '${_allSufficient ? '' : ' (رصيد غير كافٍ)'}',
              style: TextStyle(
                color: _allSufficient ? AppColors.textSecondary : AppColors.warning,
                fontWeight: FontWeight.w600,
              ),
            ),
            if (_hasCustomized)
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: TextButton(
                  onPressed: _updatingRecipe ? null : _saveRecipe,
                  child: Text(_updatingRecipe ? 'جاري الحفظ…' : 'حفظ على الوصفة'),
                ),
              ),
            TextButton(
              onPressed: () {
                setState(() => _lines = []);
                _schedulePreview(100);
              },
              child: const Text('إعادة الكميات الافتراضية'),
            ),
            for (final line in _lines) _lineTile(line),
            const SizedBox(height: 8),
            Text(
              'إجمالي تكلفة المواد الخام: ${Formatters.moneyPlain(_consumptionTotal)}',
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
            if (_hasCustomized && _consumptionTotal > 0) ...[
              Text('تكلفة الوصفة المحفوظة: ${Formatters.moneyPlain(_recipeBasedTotal)}'),
              Text('الفرق: ${Formatters.moneyPlain(_costDiff)}'),
              if (_costDiff.abs() > 0.0001)
                TextButton(
                  onPressed: () {
                    if (_qty > 0) {
                      setState(() => _costOverride = _consumptionTotal / _qty);
                    }
                  },
                  child: const Text('اعتماد تكلفة المواد الفعلية للأمر'),
                ),
            ],
            if (!_allSufficient)
              const Text(
                'بعض المواد رصيدها غير كافٍ للكميات المحددة.',
                style: TextStyle(color: AppColors.warning, fontWeight: FontWeight.w700),
              ),
            if (_hasCustomized)
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                value: _updateRecipeOnConfirm,
                onChanged: (v) =>
                    setState(() => _updateRecipeOnConfirm = v ?? false),
                title: const Text('تحديث الوصفة الأصلية أيضاً عند التأكيد'),
              ),
          ],
        ],
      ),
    );
  }

  Widget _lineTile(ConsumptionLine line) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: line.sufficient ? AppColors.bg : AppColors.warning.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(line.itemName, style: const TextStyle(fontWeight: FontWeight.w800)),
            if (line.isSubstituted && line.defaultItemName != null)
              Text(
                'بديل عن: ${line.defaultItemName}',
                style: const TextStyle(color: AppColors.textMuted, fontSize: 12),
              ),
            Text(
              [
                if (line.warehouse != null) line.warehouse!,
                'لكل وحدة ${line.bomUnitQty}',
                'متاح ${line.availableQuantity}',
              ].join(' · '),
              style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                Expanded(
                  child: TextFormField(
                    key: ValueKey('qty-${line.lineKey}-${line.resolvedCategoryId}'),
                    initialValue: _numText(line.quantity),
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: _dec(hint: 'الكمية المخصومة'),
                    onChanged: (raw) {
                      final qty = double.tryParse(raw) ?? 0;
                      if (qty < 0) return;
                      setState(() {
                        line.quantity = qty;
                        line.sufficient = line.availableQuantity + 0.00001 >= qty;
                        line.isCustomized = line.isSubstituted ||
                            (qty - line.defaultQuantity).abs() > 0.000001;
                        _consumptionTotal =
                            _lines.fold<double>(0, (s, e) => s + e.lineCost);
                        _allSufficient = _lines.every((e) => e.sufficient);
                      });
                      _schedulePreview(500);
                    },
                  ),
                ),
                IconButton(
                  tooltip: 'استبدال',
                  onPressed: () => _substitute(line),
                  icon: const Icon(Icons.swap_horiz),
                ),
                if (line.isCustomized)
                  IconButton(
                    tooltip: 'إعادة للوصفة',
                    onPressed: () {
                      setState(() {
                        line.resolvedCategoryId =
                            line.defaultResolvedCategoryId ?? line.resolvedCategoryId;
                        line.quantity = line.defaultQuantity;
                        line.isSubstituted = false;
                        line.isCustomized = false;
                      });
                      _schedulePreview(200);
                    },
                    icon: const Icon(Icons.restart_alt),
                  ),
              ],
            ),
            Text(
              'تكلفة الوحدة ${Formatters.moneyPlain(line.unitCost)} · الإجمالي ${Formatters.moneyPlain(line.lineCost)}',
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    );
  }
}

String _numText(double v) {
  if (v == v.roundToDouble()) return v.toStringAsFixed(0);
  return v.toString();
}
