import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/auth_storage.dart';
import '../../services/categories_api.dart';
import '../../services/inventory_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import 'category_movements_screen.dart';

/// قائمة الأصناف — mirrors Angular `list-categories`.
class CategoriesListScreen extends StatefulWidget {
  const CategoriesListScreen({super.key});

  @override
  State<CategoriesListScreen> createState() => _CategoriesListScreenState();
}

class _CategoriesListScreenState extends State<CategoriesListScreen> {
  final _searchCtrl = TextEditingController();
  Timer? _debounce;

  List<CategoryItem> _items = [];
  List<LookupOption> _productions = [];
  List<LookupOption> _classifications = [];
  List<String> _warehouses = [];

  String _warehouse = '';
  String _productionId = '';
  String _classificationId = '';
  String _qtyFilter = '';

  int _total = 0;
  int _page = 1;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  bool get _isAdmin =>
      AuthStorage.instance.department.toLowerCase() == 'admin';

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    try {
      final results = await Future.wait([
        CategoriesApi.instance.productions(),
        CategoriesApi.instance.classifications(),
        InventoryApi.instance.listWarehouses(),
      ]);
      if (!mounted) return;
      setState(() {
        _productions = results[0] as List<LookupOption>;
        _classifications = results[1] as List<LookupOption>;
        _warehouses =
            (results[2] as List<WarehouseRow>).map((e) => e.name).toList();
      });
    } catch (_) {
      /* filters optional */
    }
    await _load(reset: true);
  }

  List<CategoryItem> get _visible {
    if (_qtyFilter.isEmpty) return _items;
    return _items.where((item) {
      switch (_qtyFilter) {
        case '0':
          return item.quantity <= 0;
        case '10':
          return item.quantity > 0 && item.quantity <= 10;
        case 'more':
          return item.quantity > 10;
        default:
          return true;
      }
    }).toList();
  }

  Color _qtyColor(double q) {
    if (q <= 0) return const Color(0xFFFFCCCC);
    if (q < 10) return const Color(0xFFFFE5B4);
    return const Color(0xFFCCFFCC);
  }

  Future<void> _load({bool reset = false}) async {
    if (reset) {
      setState(() {
        _loading = true;
        _error = null;
        _page = 1;
      });
    } else {
      setState(() => _loadingMore = true);
    }

    try {
      final page = await CategoriesApi.instance.search(
        page: reset ? 1 : _page,
        itemsPerPage: 50,
        categoryName: _searchCtrl.text,
        warehouse: _warehouse.isEmpty ? null : _warehouse,
        productionId: _productionId.isEmpty ? null : _productionId,
        classificationId:
            _classificationId.isEmpty ? null : _classificationId,
      );
      if (!mounted) return;
      setState(() {
        if (reset) {
          _items = page.items;
        } else {
          _items = [..._items, ...page.items];
        }
        _total = page.total;
        _loading = false;
        _loadingMore = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
        _loadingMore = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'تعذر تحميل الأصناف';
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  void _onSearchChanged(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      _load(reset: true);
    });
  }

  Future<void> _editQuantity(CategoryItem item) async {
    final ctrl = TextEditingController(text: item.quantity.toString());
    final value = await showDialog<double>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('تعديل رصيد (${item.name})'),
        content: TextField(
          controller: ctrl,
          autofocus: true,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: const InputDecoration(hintText: 'الكمية'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('إلغاء'),
          ),
          ElevatedButton(
            onPressed: () =>
                Navigator.pop(ctx, double.tryParse(ctrl.text.trim())),
            child: const Text('حفظ'),
          ),
        ],
      ),
    );
    if (value == null || value < 0) return;
    try {
      await CategoriesApi.instance.updateQuantity(id: item.id, quantity: value);
      if (!mounted) return;
      await _load(reset: true);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: AppColors.danger),
      );
    }
  }

  Future<void> _editAvgCost(CategoryItem item) async {
    final ctrl = TextEditingController(
      text: item.averageUnitCost > 0
          ? item.averageUnitCost.toStringAsFixed(2)
          : '',
    );
    final value = await showDialog<double>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('تعديل متوسط تكلفة الوحدة'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'الصنف: ${item.name} — الرصيد: ${Formatters.moneyPlain(item.quantity)}',
              style: const TextStyle(
                color: AppColors.textSecondary,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: ctrl,
              autofocus: true,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(hintText: 'متوسط التكلفة'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('إلغاء'),
          ),
          ElevatedButton(
            onPressed: () =>
                Navigator.pop(ctx, double.tryParse(ctrl.text.trim())),
            child: const Text('حفظ'),
          ),
        ],
      ),
    );
    if (value == null || value < 0) return;
    try {
      await CategoriesApi.instance.updateAverageUnitCost(
        id: item.id,
        averageUnitCost: value,
      );
      if (!mounted) return;
      await _load(reset: true);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: AppColors.danger),
      );
    }
  }

  Future<void> _delete(CategoryItem item) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('تأكيد الحذف؟'),
        content: Text('حذف «${item.name}»'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('لا'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.danger),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('نعم'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await CategoriesApi.instance.deleteCategory(item.id);
      if (!mounted) return;
      await _load(reset: true);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: AppColors.danger),
      );
    }
  }

  void _openDetails(CategoryItem item) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CategoryMovementsScreen(
          categoryId: item.id,
          categoryName: item.name,
        ),
      ),
    );
  }

  void _showActions(CategoryItem item) {
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.timeline_outlined),
              title: const Text('التفاصيل'),
              onTap: () {
                Navigator.pop(ctx);
                _openDetails(item);
              },
            ),
            ListTile(
              leading: const Icon(Icons.edit_outlined),
              title: const Text('تعديل الرصيد'),
              onTap: () {
                Navigator.pop(ctx);
                _editQuantity(item);
              },
            ),
            ListTile(
              leading: const Icon(Icons.price_change_outlined),
              title: const Text('تعديل متوسط التكلفة'),
              onTap: () {
                Navigator.pop(ctx);
                _editAvgCost(item);
              },
            ),
            ListTile(
              leading: const Icon(Icons.delete_outline, color: AppColors.danger),
              title: const Text(
                'حذف',
                style: TextStyle(color: AppColors.danger),
              ),
              onTap: () {
                Navigator.pop(ctx);
                _delete(item);
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _filterDropdown({
    required String label,
    required String value,
    required List<DropdownMenuItem<String>> items,
    required ValueChanged<String?> onChanged,
  }) {
    final values = items.map((e) => e.value).whereType<String>().toSet();
    final safe = values.contains(value) ? value : '';
    return InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        isDense: true,
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<String>(
          isExpanded: true,
          value: safe,
          items: items,
          onChanged: onChanged,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final visible = _visible;
    final canMore = _items.length < _total && _qtyFilter.isEmpty;

    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        title: const Text('الأصناف'),
        actions: [
          if (_isAdmin)
            IconButton(
              tooltip: 'Admin',
              onPressed: null,
              icon: Icon(
                Icons.admin_panel_settings_outlined,
                color: Colors.white.withValues(alpha: 0.7),
              ),
            ),
          IconButton(
            onPressed: _loading ? null : () => _load(reset: true),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: _filterDropdown(
                        label: 'المخزن',
                        value: _warehouse,
                        items: [
                          const DropdownMenuItem(
                            value: '',
                            child: Text('الكل'),
                          ),
                          ..._warehouses.map(
                            (w) => DropdownMenuItem(value: w, child: Text(w)),
                          ),
                        ],
                        onChanged: (v) {
                          setState(() => _warehouse = v ?? '');
                          _load(reset: true);
                        },
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: _filterDropdown(
                        label: 'فلتر الرصيد',
                        value: _qtyFilter,
                        items: const [
                          DropdownMenuItem(value: '', child: Text('الكل')),
                          DropdownMenuItem(
                            value: '0',
                            child: Text('Out of Stock'),
                          ),
                          DropdownMenuItem(
                            value: '10',
                            child: Text('Low Stock'),
                          ),
                          DropdownMenuItem(
                            value: 'more',
                            child: Text('High Stock'),
                          ),
                        ],
                        onChanged: (v) =>
                            setState(() => _qtyFilter = v ?? ''),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: _filterDropdown(
                        label: 'خط الإنتاج',
                        value: _productionId,
                        items: [
                          const DropdownMenuItem(
                            value: '',
                            child: Text('الكل'),
                          ),
                          ..._productions.map(
                            (p) => DropdownMenuItem(
                              value: p.id,
                              child: Text(p.label),
                            ),
                          ),
                        ],
                        onChanged: (v) {
                          setState(() => _productionId = v ?? '');
                          _load(reset: true);
                        },
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: _filterDropdown(
                        label: 'التصنيف',
                        value: _classificationId,
                        items: [
                          const DropdownMenuItem(
                            value: '',
                            child: Text('الكل'),
                          ),
                          ..._classifications.map(
                            (c) => DropdownMenuItem(
                              value: c.id,
                              child: Text(
                                c.warehouse == null || c.warehouse!.isEmpty
                                    ? c.label
                                    : '${c.label} · ${c.warehouse}',
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ),
                        ],
                        onChanged: (v) {
                          setState(() => _classificationId = v ?? '');
                          _load(reset: true);
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: _searchCtrl,
                  onChanged: _onSearchChanged,
                  decoration: InputDecoration(
                    hintText: 'اسم الصنف أو كود الصنف...',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: _searchCtrl.text.isEmpty
                        ? null
                        : IconButton(
                            onPressed: () {
                              _searchCtrl.clear();
                              _load(reset: true);
                            },
                            icon: const Icon(Icons.clear),
                          ),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text(
                'العدد: ${visible.length}${_qtyFilter.isEmpty ? ' / $_total' : ''}',
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w700,
                  fontSize: 12,
                ),
              ),
            ),
          ),
          const SizedBox(height: 6),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                _error!,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  color: AppColors.danger,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 12),
                              ElevatedButton(
                                onPressed: () => _load(reset: true),
                                child: const Text('إعادة المحاولة'),
                              ),
                            ],
                          ),
                        ),
                      )
                    : visible.isEmpty
                        ? const Center(
                            child: Text(
                              'لا توجد أصناف',
                              style: TextStyle(
                                color: AppColors.textMuted,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          )
                        : RefreshIndicator(
                            onRefresh: () => _load(reset: true),
                            child: ListView.separated(
                              padding:
                                  const EdgeInsets.fromLTRB(16, 8, 16, 24),
                              itemCount:
                                  visible.length + (canMore ? 1 : 0),
                              separatorBuilder: (_, __) =>
                                  const SizedBox(height: 10),
                              itemBuilder: (context, i) {
                                if (canMore && i == visible.length) {
                                  return TextButton(
                                    onPressed: _loadingMore
                                        ? null
                                        : () {
                                            _page += 1;
                                            _load();
                                          },
                                    child: Text(
                                      _loadingMore
                                          ? 'جاري التحميل…'
                                          : 'تحميل المزيد',
                                    ),
                                  );
                                }
                                final item = visible[i];
                                return _CategoryCard(
                                  item: item,
                                  qtyColor: _qtyColor(item.quantity),
                                  onTap: () => _showActions(item),
                                  onQtyTap: () => _editQuantity(item),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}

class _CategoryCard extends StatelessWidget {
  const _CategoryCard({
    required this.item,
    required this.qtyColor,
    required this.onTap,
    required this.onQtyTap,
  });

  final CategoryItem item;
  final Color qtyColor;
  final VoidCallback onTap;
  final VoidCallback onQtyTap;

  @override
  Widget build(BuildContext context) {
    final img = item.imageUrl;
    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: img == null
                    ? Container(
                        width: 52,
                        height: 52,
                        color: AppColors.primaryBg,
                        child: const Icon(
                          Icons.image_outlined,
                          color: AppColors.primary,
                        ),
                      )
                    : Image.network(
                        img,
                        width: 52,
                        height: 52,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => Container(
                          width: 52,
                          height: 52,
                          color: AppColors.primaryBg,
                          child: const Icon(
                            Icons.broken_image_outlined,
                            color: AppColors.primary,
                          ),
                        ),
                      ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.name,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: AppColors.navy,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      [
                        if (item.itemCode != null) 'كود: ${item.itemCode}',
                        if (item.warehouse != null) item.warehouse!,
                        if (item.unit != null) item.unit!,
                      ].join(' · '),
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                        fontSize: 12,
                      ),
                    ),
                    if (item.productionLine != null ||
                        item.classificationName != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        [
                          if (item.productionLine != null)
                            item.productionLine!,
                          if (item.classificationName != null)
                            item.classificationName!,
                        ].join(' · '),
                        style: const TextStyle(
                          color: AppColors.textMuted,
                          fontWeight: FontWeight.w600,
                          fontSize: 11,
                        ),
                      ),
                    ],
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 6,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(
                          'سعر: ${Formatters.moneyPlain(item.price)}',
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 12,
                            color: AppColors.primary,
                          ),
                        ),
                        Text(
                          'متوسط: ${Formatters.moneyPlain(item.averageUnitCost)}',
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 12,
                            color: AppColors.textSecondary,
                          ),
                        ),
                        InkWell(
                          onTap: onQtyTap,
                          borderRadius: BorderRadius.circular(8),
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: qtyColor,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              'رصيد: ${Formatters.moneyPlain(item.quantity)}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 12,
                                color: AppColors.navy,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              IconButton(
                onPressed: onTap,
                icon: const Icon(Icons.more_vert, color: AppColors.primary),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
