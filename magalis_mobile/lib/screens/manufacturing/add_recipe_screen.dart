import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../config/api_config.dart';
import '../../core/api_client.dart';
import '../../services/categories_api.dart';
import '../../services/recipes_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import 'recipe_import_screen.dart';
import 'recipes_list_screen.dart';

const _kWip = 'مخزن منتج تحت التشغيل';
const _kFinished = 'مخزن منتج تام';
const _kRaw = 'مخزن مواد خام';

/// إضافة / تعديل / تكرار وصفة — mirrors Angular `AddRecipeComponent`.
class AddRecipeScreen extends StatefulWidget {
  const AddRecipeScreen({
    super.key,
    this.recipeId,
    this.duplicateFromId,
    this.preselectProductId,
    this.preselectWarehouse,
    this.preselectProductName,
  });

  final String? recipeId;
  final String? duplicateFromId;
  final String? preselectProductId;
  final String? preselectWarehouse;
  final String? preselectProductName;

  @override
  State<AddRecipeScreen> createState() => _AddRecipeScreenState();
}

class _RecipeLine {
  _RecipeLine({
    required this.itemId,
    required this.name,
    this.itemCode,
    this.warehouse,
    this.unit,
    this.imageUrl,
    required double quantity,
    required double unitCost,
  })  : qtyCtrl = TextEditingController(text: _numText(quantity)),
        costCtrl = TextEditingController(text: _numText(unitCost));

  String itemId;
  String name;
  String? itemCode;
  String? warehouse;
  String? unit;
  String? imageUrl;
  final TextEditingController qtyCtrl;
  final TextEditingController costCtrl;

  double get quantity {
    final q = double.tryParse(qtyCtrl.text.trim()) ?? 0;
    return q > 0 ? q : 0;
  }

  double get unitCost {
    final c = double.tryParse(costCtrl.text.trim()) ?? 0;
    return c < 0 ? 0 : c;
  }

  double get total => quantity * unitCost;

  void applyItem(CategoryItem item, {bool keepQty = false}) {
    itemId = item.id;
    name = item.name;
    itemCode = item.itemCode;
    warehouse = item.warehouse;
    unit = item.unit;
    imageUrl = item.imageUrl;
    if (!keepQty) qtyCtrl.text = '1';
    costCtrl.text = _numText(item.price);
  }

  void dispose() {
    qtyCtrl.dispose();
    costCtrl.dispose();
  }
}

class _ExtraCost {
  _ExtraCost({
    required this.id,
    required this.name,
    required this.type,
    required this.value,
  });

  final int id;
  String name;
  String type;
  double value;
}

class _AddRecipeScreenState extends State<AddRecipeScreen> {
  final _nameCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  final _extraNameCtrl = TextEditingController();
  final _extraValueCtrl = TextEditingController();
  final _variableCtrl = TextEditingController(text: '0');

  String? _warehouse;
  CategoryItem? _product;
  List<CategoryItem> _products = [];
  List<CategoryItem> _materials = [];
  final _lines = <_RecipeLine>[];
  final _extraCosts = <_ExtraCost>[];
  int _nextLocalExtraId = -1;
  String _extraType = 'fixed';
  String _costType = '';
  bool _loadingWarehouse = false;
  bool _bootstrapping = false;
  bool _saving = false;
  bool _bomLocked = false;
  String? _error;

  bool get _isEdit =>
      widget.recipeId != null && widget.recipeId!.isNotEmpty;
  bool get _isDuplicate =>
      widget.duplicateFromId != null && widget.duplicateFromId!.isNotEmpty;

  String get _title {
    if (_isEdit) return 'تعديل وصفة';
    if (_isDuplicate) return 'تكرار وصفة لمنتج آخر';
    return 'إضافة وصفة';
  }

  @override
  void initState() {
    super.initState();
    if (_isEdit) {
      _loadExisting(widget.recipeId!, duplicate: false);
    } else if (_isDuplicate) {
      _loadExisting(widget.duplicateFromId!, duplicate: true);
    } else if ((widget.preselectWarehouse ?? '').isNotEmpty) {
      _loadPreselect();
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _descCtrl.dispose();
    _extraNameCtrl.dispose();
    _extraValueCtrl.dispose();
    _variableCtrl.dispose();
    for (final l in _lines) {
      l.dispose();
    }
    super.dispose();
  }

  double get _materialsTotal =>
      _lines.fold<double>(0, (s, e) => s + e.total);

  double get _variableCost {
    if (_costType != 'متغير') return 0;
    return double.tryParse(_variableCtrl.text.trim()) ?? 0;
  }

  RecipeCostBreakdown get _breakdown {
    final materials = _materialsTotal;
    var fixed = 0.0;
    var pct = 0.0;
    for (final ec in _extraCosts) {
      if (ec.type == 'fixed') {
        fixed += ec.value;
      } else {
        pct += (materials * ec.value) / 100;
      }
    }
    final variable = _variableCost;
    return RecipeCostBreakdown(
      materialsCost: materials,
      fixedCosts: fixed,
      percentageCosts: pct,
      finalCost: materials + fixed + pct + variable,
    );
  }

  double get _total => _breakdown.finalCost;

  Future<void> _loadPreselect() async {
    final warehouse = widget.preselectWarehouse!;
    setState(() => _bootstrapping = true);
    await _loadWarehouse(warehouse, resetLines: true);
    if (!mounted) return;
    CategoryItem? product;
    final id = widget.preselectProductId;
    if (id != null) {
      for (final p in _products) {
        if (p.id == id) {
          product = p;
          break;
        }
      }
      product ??= CategoryItem(
        id: id,
        name: widget.preselectProductName ?? 'منتج',
        warehouse: warehouse,
      );
    }
    setState(() {
      _product = product;
      _bootstrapping = false;
    });
  }

  Future<void> _loadExisting(String id, {required bool duplicate}) async {
    setState(() {
      _bootstrapping = true;
      _error = null;
    });
    try {
      final detail = await RecipesApi.instance.show(id);
      if (!mounted) return;
      _nameCtrl.text = detail.name;
      _descCtrl.text = detail.description ?? '';
      _bomLocked = !duplicate && detail.bomLocked;
      final warehouse = detail.outputWarehouse;
      if (warehouse == null || warehouse.isEmpty) {
        setState(() {
          _bootstrapping = false;
          _error = duplicate
              ? 'الوصفة المصدر لا ترتبط بمنتج نهائي — لا يمكن تكرارها.'
              : 'الوصفة لا ترتبط بمنتج نهائي — لا يمكن تعديلها من هذه الشاشة.';
        });
        return;
      }
      await _loadWarehouse(warehouse, resetLines: false);
      if (!mounted) return;
      if (!duplicate) {
        CategoryItem? product;
        for (final p in _products) {
          if (p.id == detail.outputItemId) {
            product = p;
            break;
          }
        }
        product ??= CategoryItem(
          id: detail.outputItemId ?? '0',
          name: detail.outputName ?? 'منتج',
          itemCode: detail.outputCode,
          warehouse: warehouse,
        );
        _product = product;
      }
      for (final l in _lines) {
        l.dispose();
      }
      _lines
        ..clear()
        ..addAll(
          detail.ingredients.map(
            (ing) => _RecipeLine(
              itemId: ing.itemId,
              name: ing.name,
              itemCode: ing.itemCode,
              warehouse: ing.warehouse,
              unit: ing.unit,
              imageUrl: _imgUrl(ing.image),
              quantity: ing.quantity <= 0 ? 1 : ing.quantity,
              unitCost: ing.unitCost,
            ),
          ),
        );
      _extraCosts
        ..clear()
        ..addAll(
          detail.extraCosts.map(
            (e) => _ExtraCost(
              id: e.id,
              name: e.name,
              type: e.type,
              value: e.value,
            ),
          ),
        );
      setState(() => _bootstrapping = false);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _bootstrapping = false;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _bootstrapping = false;
        _error = 'تعذر تحميل الوصفة';
      });
    }
  }

  Future<void> _loadWarehouse(String warehouse, {bool resetLines = true}) async {
    setState(() {
      _warehouse = warehouse;
      _loadingWarehouse = true;
      _error = null;
      if (resetLines && !_isDuplicate) {
        _product = null;
        for (final l in _lines) {
          l.dispose();
        }
        _lines.clear();
        _extraCosts.clear();
      }
    });
    try {
      final products = await CategoriesApi.instance.byWarehouse(warehouse);
      List<CategoryItem> materials;
      if (warehouse == _kWip) {
        materials = await CategoriesApi.instance.byWarehouse(_kRaw);
      } else {
        final raw = await CategoriesApi.instance.byWarehouse(_kRaw);
        final wip = await CategoriesApi.instance.byWarehouse(_kWip);
        materials = [...raw, ...wip];
      }
      if (!mounted) return;
      setState(() {
        _products = products;
        _materials = materials;
        _loadingWarehouse = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _loadingWarehouse = false;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingWarehouse = false;
        _error = 'تعذر تحميل أصناف المخزن';
      });
    }
  }

  Future<void> _pickProduct() async {
    if (_isEdit || _bomLocked) return;
    final picked = await showModalBottomSheet<CategoryItem>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _CategorySearchSheet(
        title: 'المنتج النهائي',
        items: _products,
      ),
    );
    if (picked == null || !mounted) return;
    setState(() {
      _product = picked;
      if (!_isDuplicate) {
        for (final l in _lines) {
          l.dispose();
        }
        _lines.clear();
        _extraCosts.clear();
      }
    });
  }

  Future<void> _addMaterial() async {
    if (_bomLocked) return;
    if (_product == null) {
      setState(() => _error = 'اختر المنتج النهائي من الخطوة ١ أولاً حتى تُربَط المواد به.');
      return;
    }
    final used = _lines.map((e) => e.itemId).toSet();
    final options = _materials.where((e) => !used.contains(e.id)).toList();
    final picked = await showModalBottomSheet<CategoryItem>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _CategorySearchSheet(
        title: 'إضافة مادة إلى الوصفة',
        items: options,
      ),
    );
    if (picked == null || !mounted) return;
    setState(() {
      _error = null;
      _lines.add(
        _RecipeLine(
          itemId: picked.id,
          name: picked.name,
          itemCode: picked.itemCode,
          warehouse: picked.warehouse,
          unit: picked.unit,
          imageUrl: picked.imageUrl,
          quantity: 1,
          unitCost: picked.price,
        ),
      );
    });
  }

  Future<void> _replaceLine(_RecipeLine line) async {
    if (_bomLocked) return;
    final used = _lines
        .where((e) => e != line)
        .map((e) => e.itemId)
        .toSet();
    final options =
        _materials.where((e) => !used.contains(e.id) && e.id != line.itemId).toList();
    final picked = await showModalBottomSheet<CategoryItem>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _CategorySearchSheet(
        title: 'تغيير الصنف',
        items: options,
      ),
    );
    if (picked == null || !mounted) return;
    setState(() => line.applyItem(picked, keepQty: true));
  }

  void _removeLine(_RecipeLine line) {
    if (_bomLocked) return;
    setState(() {
      _lines.remove(line);
      line.dispose();
    });
  }

  void _addExtraCost() {
    if (_bomLocked) return;
    final name = _extraNameCtrl.text.trim();
    final value = double.tryParse(_extraValueCtrl.text.trim());
    if (name.isEmpty || value == null || value < 0) return;
    setState(() {
      _extraCosts.add(
        _ExtraCost(
          id: _nextLocalExtraId--,
          name: name,
          type: _extraType,
          value: value,
        ),
      );
      _extraNameCtrl.clear();
      _extraValueCtrl.clear();
      _extraType = 'fixed';
    });
  }

  void _removeExtra(_ExtraCost row) {
    if (_bomLocked) return;
    setState(() => _extraCosts.remove(row));
  }

  Future<void> _openImport() async {
    final ok = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const RecipeImportScreen()),
    );
    if (ok == true && mounted) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const RecipesListScreen()),
        (route) => route.isFirst,
      );
    }
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_isEdit && _product == null) {
      setState(() => _error = 'يجب اختيار المنتج النهائي.');
      return;
    }
    if (!_isEdit && _lines.isEmpty) {
      setState(() => _error = 'أضف مكونات للوصفة.');
      return;
    }
    if (_isEdit && !_bomLocked && (_product == null || _lines.isEmpty)) {
      setState(() => _error = 'يجب اختيار المنتج النهائي وإضافة مكونات للوصفة.');
      return;
    }
    for (final line in _lines) {
      if (line.quantity <= 0) {
        setState(() => _error = 'الكمية يجب أن تكون أكبر من صفر لـ «${line.name}».');
        return;
      }
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      if (_isEdit) {
        await RecipesApi.instance.update(
          id: widget.recipeId!,
          recipeName: _nameCtrl.text.trim().isEmpty
              ? null
              : _nameCtrl.text.trim(),
          description: _descCtrl.text.trim().isEmpty
              ? null
              : _descCtrl.text.trim(),
          outputItemId: _bomLocked ? null : _product?.id,
          ingredients: _bomLocked
              ? null
              : [
                  for (final l in _lines)
                    {
                      'item_id': int.tryParse(l.itemId) ?? l.itemId,
                      'quantity': l.quantity,
                      'unit_cost': l.unitCost,
                    },
                ],
          extraCosts: _bomLocked
              ? null
              : [
                  for (final e in _extraCosts)
                    {
                      'name': e.name,
                      'type': e.type,
                      'value': e.value,
                    },
                ],
        );
      } else {
        await RecipesApi.instance.add(
          productId: _product!.id,
          total: _total,
          products: [
            for (final l in _lines)
              {
                'id': int.tryParse(l.itemId) ?? l.itemId,
                'quantity': l.quantity,
                'total_price': l.total,
              },
          ],
          extraCosts: [
            for (final e in _extraCosts)
              {
                'name': e.name,
                'type': e.type,
                'value': e.value,
              },
          ],
        );
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_isEdit ? 'تم حفظ التعديلات' : 'تم تأكيد الوصفة'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const RecipesListScreen()),
        (route) => route.isFirst,
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = _isEdit ? 'تعذر حفظ التعديلات' : 'تعذر حفظ الوصفة');
    } finally {
      if (mounted) setState(() => _saving = false);
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
          title: Text(_title),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
          actions: [
            if (!_isEdit && !_isDuplicate)
              IconButton(
                tooltip: 'استيراد من Excel',
                onPressed: _openImport,
                icon: const Icon(Icons.upload_file_outlined),
              ),
          ],
        ),
        bottomNavigationBar: _warehouse == null || _loadingWarehouse
            ? null
            : SafeArea(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                  child: FilledButton(
                    onPressed: _saving || _bootstrapping ? null : _submit,
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      minimumSize: const Size.fromHeight(48),
                    ),
                    child: Text(
                      _saving
                          ? 'جاري الحفظ…'
                          : (_isEdit ? 'حفظ التعديلات' : 'تأكيد الوصفة'),
                    ),
                  ),
                ),
              ),
        body: _bootstrapping
            ? const Center(child: CircularProgressIndicator())
            : ListView(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                children: [
                  Text(
                    _isEdit
                        ? 'عدّل مكونات الوصفة والتكاليف ثم احفظ التغييرات'
                        : (_isDuplicate
                            ? 'تم نسخ مكونات الوصفة — اختر منتجاً نهائياً آخر ثم احفظ'
                            : 'اختر المنتج النهائي، ثم أضف المواد الخام'),
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  if (_isDuplicate) ...[
                    const SizedBox(height: 10),
                    _banner(
                      'أنت تنشئ وصفة جديدة منسوخة من وصفة قائمة. اختر منتجاً نهائياً مختلفاً (غير مكرر).',
                      AppColors.info,
                    ),
                  ],
                  if (_bomLocked) ...[
                    const SizedBox(height: 10),
                    _banner(
                      'وُجد أمر إنتاج مكتمل لهذه الوصفة — يمكنك تعديل الاسم والوصف فقط.',
                      AppColors.warning,
                    ),
                  ],
                  if (_error != null) ...[
                    const SizedBox(height: 10),
                    _banner(_error!, AppColors.danger),
                  ],
                  if (_isEdit) ...[
                    const SizedBox(height: 16),
                    _card(
                      title: 'بيانات الوصفة',
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const Text('اسم الوصفة', style: _labelStyle),
                          const SizedBox(height: 6),
                          TextField(controller: _nameCtrl, decoration: _dec()),
                          const SizedBox(height: 12),
                          const Text('الوصف', style: _labelStyle),
                          const SizedBox(height: 6),
                          TextField(controller: _descCtrl, decoration: _dec()),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 14),
                  _card(
                    step: '١',
                    title: 'المنتج والمخزن',
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Text(
                          'يحدد المنتج الذي ستُربَط به مكونات الوصفة.',
                          style: TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 12,
                          ),
                        ),
                        const SizedBox(height: 10),
                        const Text('نوع المنتج', style: _labelStyle),
                        const SizedBox(height: 6),
                        DropdownButtonFormField<String>(
                          value: (_warehouse == _kWip || _warehouse == _kFinished)
                              ? _warehouse
                              : null,
                          decoration: _dec(hint: 'اختر نوع المخزن'),
                          items: const [
                            DropdownMenuItem(
                              value: _kWip,
                              child: Text(_kWip),
                            ),
                            DropdownMenuItem(
                              value: _kFinished,
                              child: Text(_kFinished),
                            ),
                          ],
                          onChanged: _isEdit || _bomLocked || _isDuplicate
                              ? null
                              : (v) {
                                  if (v != null) _loadWarehouse(v);
                                },
                        ),
                        if (_loadingWarehouse) ...[
                          const SizedBox(height: 12),
                          const Text(
                            'جاري تحميل الأصناف…',
                            style: TextStyle(color: AppColors.textMuted),
                          ),
                        ],
                        if (_warehouse != null && !_loadingWarehouse) ...[
                          const SizedBox(height: 12),
                          const Text('المنتج النهائي', style: _labelStyle),
                          const SizedBox(height: 6),
                          if (_product != null)
                            _selectedProductTile()
                          else if (_products.isEmpty)
                            const Text(
                              'لا توجد أصناف مسجّلة في هذا المخزن.',
                              style: TextStyle(color: AppColors.warning),
                            )
                          else
                            OutlinedButton.icon(
                              onPressed: _isEdit || _bomLocked ? null : _pickProduct,
                              icon: const Icon(Icons.search),
                              label: const Text('ابحث باسم المنتج…'),
                            ),
                        ],
                      ],
                    ),
                  ),
                  if (_warehouse != null && !_loadingWarehouse) ...[
                    const SizedBox(height: 14),
                    if (!_bomLocked)
                      _card(
                        step: '٢',
                        title: 'إضافة مادة إلى الوصفة',
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            if (_materials.isEmpty)
                              const Text(
                                'لا توجد مواد مطابقة لنوع الوصفة المختار.',
                                style: TextStyle(color: AppColors.textMuted),
                              )
                            else if (_product == null)
                              const Text(
                                'اختر المنتج النهائي من الخطوة ١ أولاً حتى تُربَط المواد به.',
                                style: TextStyle(color: AppColors.warning),
                              )
                            else ...[
                              Text(
                                'جاهز لإضافة مواد الوصفة لـ «${_product!.name}».',
                                style: const TextStyle(
                                  color: AppColors.success,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              const SizedBox(height: 8),
                              FilledButton.icon(
                                onPressed: _addMaterial,
                                style: FilledButton.styleFrom(
                                  backgroundColor: AppColors.primary,
                                ),
                                icon: const Icon(Icons.add),
                                label: const Text('بحث وإضافة مادة'),
                              ),
                            ],
                          ],
                        ),
                      ),
                    const SizedBox(height: 14),
                    _card(
                      step: '٣',
                      title: 'مكونات الوصفة',
                      trailing: _lines.isEmpty
                          ? null
                          : Text(
                              '${_lines.length} صنف',
                              style: const TextStyle(
                                color: AppColors.primary,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                      child: _lines.isEmpty
                          ? const Text(
                              'لا توجد أصناف بعد. استخدم حقل «إضافة مادة إلى الوصفة» في الخطوة ٢.',
                              style: TextStyle(color: AppColors.textMuted),
                            )
                          : Column(
                              children: [
                                for (var i = 0; i < _lines.length; i++) ...[
                                  if (i > 0) const Divider(height: 20),
                                  _lineTile(_lines[i]),
                                ],
                              ],
                            ),
                    ),
                  ],
                  if (_warehouse != null &&
                      !_loadingWarehouse &&
                      _lines.isNotEmpty) ...[
                    const SizedBox(height: 14),
                    _card(
                      step: '٤',
                      title: 'تكاليف إضافية',
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const Text(
                            'أضف تكاليف الماكينة، العمالة، الكهرباء، نسبة الهالك أو أي تكلفة أخرى.',
                            style: TextStyle(
                              color: AppColors.textSecondary,
                              fontSize: 12,
                            ),
                          ),
                          if (!_bomLocked) ...[
                            const SizedBox(height: 10),
                            const Text('الاسم', style: _labelStyle),
                            const SizedBox(height: 6),
                            TextField(
                              controller: _extraNameCtrl,
                              decoration: _dec(hint: 'مثال: تكلفة الماكينة'),
                            ),
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                Expanded(
                                  child: DropdownButtonFormField<String>(
                                    value: _extraType,
                                    decoration: _dec(),
                                    items: const [
                                      DropdownMenuItem(
                                        value: 'fixed',
                                        child: Text('مبلغ ثابت'),
                                      ),
                                      DropdownMenuItem(
                                        value: 'percentage',
                                        child: Text('نسبة مئوية %'),
                                      ),
                                    ],
                                    onChanged: (v) =>
                                        setState(() => _extraType = v ?? 'fixed'),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: TextField(
                                    controller: _extraValueCtrl,
                                    keyboardType:
                                        const TextInputType.numberWithOptions(
                                      decimal: true,
                                    ),
                                    inputFormatters: [
                                      FilteringTextInputFormatter.allow(
                                        RegExp(r'[0-9.]'),
                                      ),
                                    ],
                                    decoration: _dec(hint: 'القيمة'),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            OutlinedButton.icon(
                              onPressed: _addExtraCost,
                              icon: const Icon(Icons.add),
                              label: const Text('إضافة'),
                            ),
                          ],
                          if (_extraCosts.isNotEmpty) ...[
                            const SizedBox(height: 10),
                            for (final ec in _extraCosts)
                              ListTile(
                                contentPadding: EdgeInsets.zero,
                                title: Text(
                                  ec.name,
                                  style: const TextStyle(fontWeight: FontWeight.w700),
                                ),
                                subtitle: Text(
                                  ec.type == 'fixed' ? 'مبلغ ثابت' : 'نسبة مئوية',
                                ),
                                trailing: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Text(
                                      ec.type == 'fixed'
                                          ? Formatters.moneyPlain(ec.value)
                                          : '${Formatters.moneyPlain(ec.value)}%',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                    if (!_bomLocked)
                                      IconButton(
                                        onPressed: () => _removeExtra(ec),
                                        icon: const Icon(
                                          Icons.delete_outline,
                                          color: AppColors.danger,
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                          ],
                          const SizedBox(height: 8),
                          _breakdownGrid(_breakdown),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),
                    _card(
                      title: 'التكلفة والتأكيد',
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const Text('نوع التكلفة', style: _labelStyle),
                          const SizedBox(height: 6),
                          DropdownButtonFormField<String>(
                            value: _costType.isEmpty ? null : _costType,
                            decoration: _dec(hint: 'اختر نوع التكلفة'),
                            items: const [
                              DropdownMenuItem(
                                value: 'ثابت',
                                child: Text('ثابت'),
                              ),
                              DropdownMenuItem(
                                value: 'متغير',
                                child: Text('متغير'),
                              ),
                            ],
                            onChanged: (v) => setState(() {
                              _costType = v ?? '';
                              if (_costType != 'متغير') {
                                _variableCtrl.text = '0';
                              }
                            }),
                          ),
                          if (_costType == 'متغير') ...[
                            const SizedBox(height: 10),
                            const Text('التكلفة المتغيرة', style: _labelStyle),
                            const SizedBox(height: 6),
                            TextField(
                              controller: _variableCtrl,
                              keyboardType:
                                  const TextInputType.numberWithOptions(
                                decimal: true,
                              ),
                              onChanged: (_) => setState(() {}),
                              decoration: _dec(),
                            ),
                          ],
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
                                  style: TextStyle(
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.navy,
                                  ),
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
                        ],
                      ),
                    ),
                  ],
                ],
              ),
      ),
    );
  }

  Widget _selectedProductTile() {
    final p = _product!;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.primaryBg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'المنتج المختار',
                  style: TextStyle(
                    fontSize: 11,
                    color: AppColors.textSecondary,
                  ),
                ),
                Text(
                  p.name,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.navy,
                  ),
                ),
                if (p.itemCode != null)
                  Text(
                    p.itemCode!,
                    style: const TextStyle(
                      color: AppColors.textMuted,
                      fontSize: 12,
                    ),
                  ),
              ],
            ),
          ),
          if (!_bomLocked && !_isEdit)
            TextButton(
              onPressed: () => setState(() => _product = null),
              child: const Text('تغيير'),
            ),
        ],
      ),
    );
  }

  Widget _lineTile(_RecipeLine line) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            if (line.imageUrl != null)
              Padding(
                padding: const EdgeInsets.only(left: 8),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.network(
                    line.imageUrl!,
                    width: 40,
                    height: 40,
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) => const SizedBox(
                      width: 40,
                      height: 40,
                      child: Icon(Icons.image_not_supported_outlined, size: 18),
                    ),
                  ),
                ),
              ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    line.name,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: AppColors.navy,
                    ),
                  ),
                  Text(
                    [
                      if (line.itemCode != null) line.itemCode!,
                      if (line.warehouse != null) line.warehouse!,
                      if (line.unit != null) line.unit!,
                    ].join(' · '),
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            if (!_bomLocked)
              IconButton(
                tooltip: 'حذف',
                onPressed: () => _removeLine(line),
                icon: const Icon(Icons.remove_circle_outline, color: AppColors.danger),
              ),
          ],
        ),
        if (!_bomLocked)
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton(
              onPressed: () => _replaceLine(line),
              child: const Text('ابحث لتغيير الصنف…'),
            ),
          ),
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: line.qtyCtrl,
                enabled: !_bomLocked,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                onChanged: (_) => setState(() {}),
                decoration: _dec(hint: 'الكمية'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: TextField(
                controller: line.costCtrl,
                enabled: !_bomLocked,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                onChanged: (_) => setState(() {}),
                decoration: _dec(hint: 'التكلفة'),
              ),
            ),
            const SizedBox(width: 8),
            SizedBox(
              width: 88,
              child: Text(
                Formatters.moneyPlain(line.total),
                textAlign: TextAlign.end,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _breakdownGrid(RecipeCostBreakdown b) {
    Widget box(String label, double value, {bool total = false}) {
      return Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: total ? AppColors.primaryBg : AppColors.bg,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          children: [
            Text(
              label,
              style: const TextStyle(
                fontSize: 11,
                color: AppColors.textSecondary,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              Formatters.moneyPlain(value),
              style: TextStyle(
                fontWeight: FontWeight.w900,
                color: total ? AppColors.primary : AppColors.navy,
              ),
            ),
          ],
        ),
      );
    }

    return Column(
      children: [
        Row(
          children: [
            Expanded(child: box('تكلفة المواد الخام', b.materialsCost)),
            const SizedBox(width: 8),
            Expanded(child: box('تكاليف ثابتة', b.fixedCosts)),
          ],
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(child: box('تكاليف نسبية', b.percentageCosts)),
            const SizedBox(width: 8),
            Expanded(child: box('التكلفة النهائية', b.finalCost, total: true)),
          ],
        ),
      ],
    );
  }

  Widget _card({
    String? step,
    required String title,
    Widget? trailing,
    required Widget child,
  }) {
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
          Row(
            children: [
              if (step != null) ...[
                CircleAvatar(
                  radius: 12,
                  backgroundColor: AppColors.primary,
                  child: Text(
                    step,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
              ],
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 15,
                    color: AppColors.navy,
                  ),
                ),
              ),
              ?trailing,
            ],
          ),
          const SizedBox(height: 10),
          child,
        ],
      ),
    );
  }

  Widget _banner(String text, Color color) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Text(
        text,
        style: TextStyle(color: color, fontWeight: FontWeight.w700),
      ),
    );
  }
}

const _labelStyle = TextStyle(
  fontSize: 12,
  color: AppColors.textSecondary,
  fontWeight: FontWeight.w700,
);

String? _imgUrl(String? img) {
  final v = img?.trim();
  if (v == null || v.isEmpty) return null;
  if (v.startsWith('http')) return v;
  return '${ApiConfig.imgUrl}$v';
}

String _numText(double v) {
  if (v == v.roundToDouble()) return v.toStringAsFixed(0);
  return v.toString();
}

class _CategorySearchSheet extends StatefulWidget {
  const _CategorySearchSheet({
    required this.title,
    required this.items,
  });

  final String title;
  final List<CategoryItem> items;

  @override
  State<_CategorySearchSheet> createState() => _CategorySearchSheetState();
}

class _CategorySearchSheetState extends State<_CategorySearchSheet> {
  final _ctrl = TextEditingController();

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  List<CategoryItem> get _visible {
    final q = _ctrl.text.trim().toLowerCase();
    if (q.isEmpty) return widget.items;
    return widget.items.where((e) {
      return e.name.toLowerCase().contains(q) ||
          (e.itemCode ?? '').toLowerCase().contains(q) ||
          (e.warehouse ?? '').toLowerCase().contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final items = _visible;
    return Directionality(
      textDirection: TextDirection.rtl,
      child: SafeArea(
        child: SizedBox(
          height: MediaQuery.of(context).size.height * 0.8,
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                child: Text(
                  widget.title,
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
                  controller: _ctrl,
                  autofocus: true,
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    hintText: 'ابحث باسم المنتج أو الكود…',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: _ctrl.text.isEmpty
                        ? null
                        : IconButton(
                            onPressed: () {
                              _ctrl.clear();
                              setState(() {});
                            },
                            icon: const Icon(Icons.clear),
                          ),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Expanded(
                child: items.isEmpty
                    ? const Center(
                        child: Text(
                          'لا توجد نتائج',
                          style: TextStyle(color: AppColors.textMuted),
                        ),
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        itemCount: items.length,
                        separatorBuilder: (_, _) => const Divider(height: 1),
                        itemBuilder: (context, i) {
                          final item = items[i];
                          return ListTile(
                            onTap: () => Navigator.pop(context, item),
                            title: Text(
                              item.name,
                              style: const TextStyle(fontWeight: FontWeight.w700),
                            ),
                            subtitle: Text(
                              [
                                if (item.itemCode != null) item.itemCode!,
                                if (item.warehouse != null) item.warehouse!,
                              ].join(' · '),
                            ),
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
