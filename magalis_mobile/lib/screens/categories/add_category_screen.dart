import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api_client.dart';
import '../../services/categories_api.dart';
import '../../services/inventory_api.dart';
import '../../theme/app_colors.dart';

/// إضافة صنف — mirrors Angular `AddCategoryComponent` (`/categories/add_category`).
class AddCategoryScreen extends StatefulWidget {
  const AddCategoryScreen({super.key});

  @override
  State<AddCategoryScreen> createState() => _AddCategoryScreenState();
}

class _AddCategoryScreenState extends State<AddCategoryScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  final _codeCtrl = TextEditingController();
  final _colorCtrl = TextEditingController();
  final _priceCtrl = TextEditingController();
  final _initialCtrl = TextEditingController();
  final _minQtyCtrl = TextEditingController();

  List<WarehouseRow> _warehouses = [];
  List<LookupOption> _allUnits = [];
  List<LookupOption> _allProductions = [];
  List<LookupOption> _allClassifications = [];

  String? _warehouse;
  String? _unitId;
  String? _productionId;
  String? _classificationId;

  bool _loadingLookups = true;
  bool _saving = false;
  String? _error;
  String _lastAutoCode = '';

  List<LookupOption> get _units => _allUnits
      .where((e) => e.warehouse == null || e.warehouse == _warehouse)
      .toList();

  List<LookupOption> get _productions => _allProductions
      .where((e) => e.warehouse == null || e.warehouse == _warehouse)
      .toList();

  List<LookupOption> get _classifications => _allClassifications
      .where((e) => e.warehouse == null || e.warehouse == _warehouse)
      .toList();

  @override
  void initState() {
    super.initState();
    _loadLookups();
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _codeCtrl.dispose();
    _colorCtrl.dispose();
    _priceCtrl.dispose();
    _initialCtrl.dispose();
    _minQtyCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadLookups() async {
    setState(() {
      _loadingLookups = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        InventoryApi.instance.listWarehouses(),
        CategoriesApi.instance.measurements(),
        CategoriesApi.instance.productions(),
        CategoriesApi.instance.classifications(),
      ]);
      if (!mounted) return;
      setState(() {
        _warehouses = results[0] as List<WarehouseRow>;
        _allUnits = results[1] as List<LookupOption>;
        _allProductions = results[2] as List<LookupOption>;
        _allClassifications = results[3] as List<LookupOption>;
        _loadingLookups = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loadingLookups = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'تعذر تحميل بيانات النموذج';
        _loadingLookups = false;
      });
    }
  }

  void _onWarehouseChanged(String? value) {
    setState(() {
      _warehouse = value;
      _classificationId = null;
      final units = _allUnits
          .where((e) => e.warehouse == null || e.warehouse == value)
          .toList();
      final prods = _allProductions
          .where((e) => e.warehouse == null || e.warehouse == value)
          .toList();
      _unitId = units.isNotEmpty ? units.first.id : null;
      _productionId = prods.isNotEmpty ? prods.first.id : null;
    });
    _previewNextItemCode(value);
  }

  Future<void> _previewNextItemCode(String? warehouse) async {
    if (warehouse == null || warehouse.isEmpty) return;
    final next = await CategoriesApi.instance.nextItemCode(warehouse);
    if (!mounted || next == null || next.isEmpty) return;
    final current = _codeCtrl.text.trim();
    if (current.isEmpty || current == _lastAutoCode) {
      setState(() {
        _lastAutoCode = next;
        _codeCtrl.text = next;
      });
    }
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) return;
    if (_warehouse == null || _warehouse!.isEmpty) {
      setState(() => _error = 'من فضلك اختر المخزن');
      return;
    }
    if (_unitId == null || _productionId == null) {
      setState(() => _error = 'من فضلك اختر وحدة القياس وخط الإنتاج');
      return;
    }

    final price = double.tryParse(_priceCtrl.text.trim());
    final initial = double.tryParse(_initialCtrl.text.trim());
    final minQty = double.tryParse(_minQtyCtrl.text.trim());
    if (price == null || initial == null || minQty == null) {
      setState(() => _error = 'تحقق من الأرقام المدخلة');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      WarehouseRow? stock;
      for (final w in _warehouses) {
        if (w.name == _warehouse) {
          stock = w;
          break;
        }
      }
      await CategoriesApi.instance.addCategory(
        categoryName: _nameCtrl.text,
        categoryPrice: price,
        initialBalance: initial,
        minimumQuantity: minQty,
        warehouse: _warehouse!,
        measurementId: _unitId!,
        productionId: _productionId!,
        itemCode: _codeCtrl.text,
        color: _colorCtrl.text,
        itemClassificationId: _classificationId,
        stockId: stock?.id,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تم اضافة الصنف بنجاح'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      _resetForm();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = 'تعذر إضافة الصنف');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _resetForm() {
    _formKey.currentState?.reset();
    _nameCtrl.clear();
    _codeCtrl.clear();
    _lastAutoCode = '';
    _colorCtrl.clear();
    _priceCtrl.clear();
    _initialCtrl.clear();
    _minQtyCtrl.clear();
    setState(() {
      _warehouse = null;
      _unitId = null;
      _productionId = null;
      _classificationId = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('إضافة صنف'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        body: _loadingLookups
            ? const Center(child: CircularProgressIndicator())
            : Form(
                key: _formKey,
                child: ListView(
                  primary: true,
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                  children: [
                    if (_error != null) ...[
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.danger.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(
                            color: AppColors.danger.withValues(alpha: 0.35),
                          ),
                        ),
                        child: Text(
                          _error!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: AppColors.danger,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      const SizedBox(height: 14),
                    ],
                    _field(
                      label: 'اسم الصنف',
                      child: TextFormField(
                        controller: _nameCtrl,
                        textInputAction: TextInputAction.next,
                        decoration: _decoration('اسم الصنف'),
                        validator: (v) =>
                            (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
                      ),
                    ),
                    _field(
                      label: 'كود الصنف',
                      hint: 'يُولَّد تلقائياً حسب المخزن (خامات 101001، منتج تام 301001، …)',
                      child: TextFormField(
                        controller: _codeCtrl,
                        textInputAction: TextInputAction.next,
                        maxLength: 64,
                        decoration: _decoration('كود الصنف'),
                      ),
                    ),
                    _field(
                      label: 'اللون',
                      child: TextFormField(
                        controller: _colorCtrl,
                        textInputAction: TextInputAction.next,
                        maxLength: 128,
                        decoration: _decoration('مثال: Beige / Fur'),
                      ),
                    ),
                    _field(
                      label: 'سعر الصنف',
                      child: TextFormField(
                        controller: _priceCtrl,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        inputFormatters: [
                          FilteringTextInputFormatter.allow(
                            RegExp(r'[0-9.]'),
                          ),
                        ],
                        decoration: _decoration('0'),
                        validator: _nonNegRequired,
                      ),
                    ),
                    _field(
                      label: 'الرصيد الافتتاحي',
                      child: TextFormField(
                        controller: _initialCtrl,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        inputFormatters: [
                          FilteringTextInputFormatter.allow(
                            RegExp(r'[0-9.]'),
                          ),
                        ],
                        decoration: _decoration('0'),
                        validator: _nonNegRequired,
                      ),
                    ),
                    _field(
                      label: 'الحد الأدنى للمخزون',
                      child: TextFormField(
                        controller: _minQtyCtrl,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        inputFormatters: [
                          FilteringTextInputFormatter.allow(
                            RegExp(r'[0-9.]'),
                          ),
                        ],
                        decoration: _decoration('0'),
                        validator: _nonNegRequired,
                      ),
                    ),
                    _field(
                      label: 'المخزن',
                      child: DropdownButtonFormField<String>(
                        value: _warehouse,
                        decoration: _decoration('اختر المخزن'),
                        items: [
                          for (final w in _warehouses)
                            DropdownMenuItem(
                              value: w.name,
                              child: Text(w.name),
                            ),
                        ],
                        onChanged: _onWarehouseChanged,
                        validator: (v) =>
                            (v == null || v.isEmpty) ? 'مطلوب' : null,
                      ),
                    ),
                    _field(
                      label: 'وحدة القياس',
                      child: DropdownButtonFormField<String>(
                        value: _unitId,
                        decoration: _decoration('اختر وحدة القياس'),
                        items: [
                          for (final u in _units)
                            DropdownMenuItem(
                              value: u.id,
                              child: Text(u.label),
                            ),
                        ],
                        onChanged: _warehouse == null
                            ? null
                            : (v) => setState(() => _unitId = v),
                        validator: (v) =>
                            (v == null || v.isEmpty) ? 'مطلوب' : null,
                      ),
                    ),
                    _field(
                      label: 'خط الإنتاج',
                      child: DropdownButtonFormField<String>(
                        value: _productionId,
                        decoration: _decoration('اختر خط الإنتاج'),
                        items: [
                          for (final p in _productions)
                            DropdownMenuItem(
                              value: p.id,
                              child: Text(p.label),
                            ),
                        ],
                        onChanged: _warehouse == null
                            ? null
                            : (v) => setState(() => _productionId = v),
                        validator: (v) =>
                            (v == null || v.isEmpty) ? 'مطلوب' : null,
                      ),
                    ),
                    _field(
                      label: 'التصنيف',
                      child: DropdownButtonFormField<String>(
                        value: _classificationId ?? '',
                        decoration: _decoration('— بدون —'),
                        items: [
                          const DropdownMenuItem(
                            value: '',
                            child: Text('— بدون —'),
                          ),
                          for (final c in _classifications)
                            DropdownMenuItem(
                              value: c.id,
                              child: Text(c.label),
                            ),
                        ],
                        onChanged: _warehouse == null
                            ? null
                            : (v) => setState(
                                  () => _classificationId =
                                      (v == null || v.isEmpty) ? null : v,
                                ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    SizedBox(
                      height: 48,
                      child: FilledButton(
                        onPressed: _saving ? null : _submit,
                        style: FilledButton.styleFrom(
                          backgroundColor: AppColors.primary,
                        ),
                        child: _saving
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Text(
                                'أضف',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
      ),
    );
  }

  String? _nonNegRequired(String? v) {
    if (v == null || v.trim().isEmpty) return 'مطلوب';
    final n = double.tryParse(v.trim());
    if (n == null || n < 0) return 'قيمة غير صحيحة';
    return null;
  }

  InputDecoration _decoration(String hint) {
    return InputDecoration(
      hintText: hint,
      filled: true,
      fillColor: AppColors.surface,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      counterText: '',
    );
  }

  Widget _field({
    required String label,
    required Widget child,
    String? hint,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.navy,
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 6),
          child,
          if (hint != null) ...[
            const SizedBox(height: 4),
            Text(
              hint,
              style: const TextStyle(
                fontSize: 11,
                color: AppColors.textMuted,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
