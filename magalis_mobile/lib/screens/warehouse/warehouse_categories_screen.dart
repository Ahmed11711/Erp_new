import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../services/inventory_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../categories/category_movements_screen.dart';

/// Live warehouse categories + qty actions — mirrors Angular `cat`.
class WarehouseCategoriesScreen extends StatefulWidget {
  const WarehouseCategoriesScreen({
    super.key,
    required this.warehouse,
  });

  final String warehouse;

  @override
  State<WarehouseCategoriesScreen> createState() =>
      _WarehouseCategoriesScreenState();
}

class _WarehouseCategoriesScreenState extends State<WarehouseCategoriesScreen> {
  final _searchCtrl = TextEditingController();
  late String _warehouse;
  List<WarehouseCategoryLine> _items = [];
  int _total = 0;
  int _page = 1;
  double _listedBalance = 0;
  bool _loading = true;
  bool _loadingMore = false;
  bool _saving = false;
  bool _sort = false;
  String? _error;
  Timer? _debounce;

  static const _pageSize = 50;

  bool get _isFinished => _warehouse == 'مخزن منتج تام';
  bool get _isMaintenance => _warehouse == 'مخزن صيانة';
  bool get _isOperatingSupplies =>
      _warehouse == 'مستلزمات تشغيل وأدوات تشغيل';

  @override
  void initState() {
    super.initState();
    _warehouse = widget.warehouse;
    _load(reset: true);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _refreshWarehouseBalance() async {
    try {
      final rows = await InventoryApi.instance.listWarehouses();
      if (!mounted) return;
      WarehouseRow? match;
      for (final r in rows) {
        if (r.name == _warehouse) {
          match = r;
          break;
        }
      }
      setState(() => _listedBalance = match?.balance ?? 0);
    } catch (_) {
      /* keep last known total */
    }
  }

  Future<void> _load({bool reset = false}) async {
    if (_warehouse.isEmpty) {
      setState(() {
        _items = [];
        _total = 0;
        _page = 1;
        _listedBalance = 0;
        _loading = false;
        _loadingMore = false;
        _error = null;
      });
      return;
    }
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
      final page = await InventoryApi.instance.categoriesByWarehouse(
        warehouse: _warehouse,
        page: reset ? 1 : _page,
        itemsPerPage: _pageSize,
        name: _searchCtrl.text,
        sort: _sort,
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
      if (reset) await _refreshWarehouseBalance();
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
        _error = 'تعذر تحميل أصناف المخزن';
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  void _onSearch(String _) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      _load(reset: true);
    });
  }

  Future<void> _recordSnapshot() async {
    final month = InventoryApi.previousMonthValue();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('تأكيد الجرد الشهري؟'),
        content: Text(
          'سيتم تسجيل لقطة مخزون لشهر $month بناءً على الأرصدة الحالية والتكلفة المرجّحة.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('لا'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('نعم'),
          ),
        ],
      ),
    );
    if (ok != true) return;

    setState(() => _saving = true);
    try {
      final res = await InventoryApi.instance.recordSnapshot(
        warehouse: _warehouse,
        month: month,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            res.success
                ? 'تم التسجيل — الشهر: ${res.month} · ${res.lines} صنفاً'
                : 'تم',
          ),
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تعذر تسجيل الجرد')),
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _openAdjustQty(WarehouseCategoryLine item, String mode) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _adjustQty(item, mode);
    });
  }

  Future<void> _adjustQty(WarehouseCategoryLine item, String mode) async {
    final titles = {
      'add': 'إضافة كمية إلى (${item.categoryName})',
      'remove': 'تقليل كمية من (${item.categoryName})',
      'edit': 'تعديل كمية (${item.categoryName})',
    };
    final result = await showDialog<_AdjustQtyResult>(
      context: context,
      builder: (_) => _AdjustQtyDialog(
        title: titles[mode] ?? 'الكمية',
        initialQty: mode == 'edit' ? item.quantity.toString() : '',
      ),
    );
    if (!mounted || result == null) return;
    final value = result.qty;
    if (mode != 'edit' && value <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('يجب إدخال قيمة صحيحة أكبر من صفر')),
      );
      return;
    }
    if (mode == 'edit' && value < 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('يجب إدخال قيمة صحيحة')),
      );
      return;
    }

    final dateStr = DateFormat('yyyy-MM-dd').format(result.date);

    try {
      if (mode == 'edit') {
        await InventoryApi.instance.changeQuantity(
          id: item.id,
          status: 'edit',
          quantity: value,
          movementDate: dateStr,
        );
      } else if (mode == 'add') {
        await InventoryApi.instance.changeQuantity(
          id: item.id,
          status: 'add',
          quantity: value,
          movementDate: dateStr,
        );
      } else {
        await InventoryApi.instance.changeQuantity(
          id: item.id,
          status: 'add',
          quantity: -value,
          movementDate: dateStr,
        );
      }
      if (!mounted) return;
      await _load(reset: true);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('فشل تحديث الكمية')),
      );
    }
  }

  bool get _canAdjustQty =>
      _warehouse == 'مخزن منتج تام' ||
      _warehouse == 'مخزن مواد خام' ||
      _warehouse == 'مخزن منتج تحت التشغيل' ||
      _warehouse == 'مخزن إنتاج تحت التشغيل' ||
      _isOperatingSupplies;

  void _openItemDetails(WarehouseCategoryLine item) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CategoryMovementsScreen(
          categoryId: item.id,
          categoryName: item.categoryName,
        ),
      ),
    );
  }

  Future<void> _pickWarehouse() async {
    List<String> names = kStandardWarehouses.map((w) => w.nameAr).toList();
    try {
      final rows = await InventoryApi.instance.listWarehouses();
      if (rows.isNotEmpty) names = rows.map((r) => r.name).toList();
    } catch (_) {}
    if (!mounted) return;
    final name = await showModalBottomSheet<String>(
      context: context,
      builder: (ctx) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
              child: Text(
                'اختر المخزن',
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 16,
                  color: AppColors.navy,
                ),
              ),
            ),
            ...names.map(
              (w) => ListTile(
                title: Text(w),
                trailing: w == _warehouse
                    ? const Icon(Icons.check, color: AppColors.primary)
                    : null,
                onTap: () => Navigator.pop(ctx, w),
              ),
            ),
          ],
        ),
      ),
    );
    if (name == null || name == _warehouse) return;
    setState(() => _warehouse = name);
    _load(reset: true);
  }

  void _showItemActions(WarehouseCategoryLine item) {
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
                _openItemDetails(item);
              },
            ),
            if (_canAdjustQty) ...[
              ListTile(
                leading: const Icon(Icons.add_circle_outline),
                title: const Text('إضافة كمية'),
                onTap: () {
                  Navigator.pop(ctx);
                  _openAdjustQty(item, 'add');
                },
              ),
              ListTile(
                leading: const Icon(Icons.remove_circle_outline),
                title: const Text('تقليل كمية'),
                onTap: () {
                  Navigator.pop(ctx);
                  _openAdjustQty(item, 'remove');
                },
              ),
            ],
            if (!_isMaintenance)
              ListTile(
                leading: const Icon(Icons.edit_outlined),
                title: const Text('تعديل الكمية'),
                onTap: () {
                  Navigator.pop(ctx);
                  _openAdjustQty(item, 'edit');
                },
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        title: Text(
          _warehouse.isEmpty ? 'تفاصيل المخزن' : 'تفاصيل $_warehouse',
        ),
        actions: [
          TextButton(
            onPressed: _saving || _warehouse.isEmpty ? null : _recordSnapshot,
            child: _saving
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text(
                    'تسجيل الجرد',
                    style: TextStyle(fontWeight: FontWeight.w800),
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
            child: OutlinedButton.icon(
              onPressed: _pickWarehouse,
              icon: const Icon(Icons.warehouse_outlined, size: 18),
              label: Text(
                _warehouse.isEmpty ? 'اختر المخزن' : _warehouse,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
            child: TextField(
              controller: _searchCtrl,
              onChanged: _onSearch,
              decoration: InputDecoration(
                hintText: 'اسم الصنف',
                prefixIcon: const Icon(Icons.search),
                suffixIconConstraints:
                    const BoxConstraints(minWidth: 0, minHeight: 0),
                suffixIcon: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    IconButton(
                      tooltip: 'ترتيب حسب القيمة',
                      visualDensity: VisualDensity.compact,
                      onPressed: _warehouse.isEmpty
                          ? null
                          : () {
                              setState(() => _sort = !_sort);
                              _load(reset: true);
                            },
                      icon: Icon(
                        Icons.sort_rounded,
                        color: _sort
                            ? AppColors.primary
                            : AppColors.textMuted,
                      ),
                    ),
                    if (_searchCtrl.text.isNotEmpty)
                      IconButton(
                        visualDensity: VisualDensity.compact,
                        onPressed: () {
                          _searchCtrl.clear();
                          _load(reset: true);
                        },
                        icon: const Icon(Icons.clear),
                      ),
                  ],
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Row(
              children: [
                Text(
                  'العدد: $_total',
                  style: const TextStyle(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
                const Spacer(),
                const Text(
                  'الاجمالي',
                  style: TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.primary,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(width: 8),
                Text(
                  Formatters.moneyPlain(_listedBalance),
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    color: AppColors.navy,
                    fontSize: 16,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 6),
          Expanded(child: _buildBody()),
        ],
      ),
    );
  }

  Widget _buildBody() {
    if (_warehouse.isEmpty) {
      return const Center(
        child: Text(
          'اختر مخزناً لعرض الأصناف',
          style: TextStyle(
            color: AppColors.textMuted,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }
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
      );
    }
    if (_items.isEmpty) {
      return const Center(
        child: Text(
          'لا توجد أصناف',
          style: TextStyle(
            color: AppColors.textMuted,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }
    final canLoadMore = _items.length < _total;
    return RefreshIndicator(
      onRefresh: () => _load(reset: true),
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        itemCount: _items.length + (canLoadMore ? 1 : 0),
        separatorBuilder: (_, __) => const SizedBox(height: 10),
        itemBuilder: (context, i) {
          if (canLoadMore && i == _items.length) {
            return TextButton(
              onPressed: _loadingMore
                  ? null
                  : () {
                      _page += 1;
                      _load();
                    },
              child: Text(
                _loadingMore ? 'جاري التحميل…' : 'تحميل المزيد',
              ),
            );
          }
          final item = _items[i];
          final costLabel = Formatters.moneyPlain(item.totalPrice);
          final sellLabel = Formatters.moneyPlain(item.sellTotalPrice);
          return Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.categoryName,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.navy,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  [
                    'الوحدة: ${item.unit}',
                    if (!_isMaintenance)
                      'الكمية: ${Formatters.moneyPlain(item.quantity)}',
                    if (_isFinished)
                      'سعر البيع: ${Formatters.moneyPlain(item.sellPrice ?? 0)}',
                    if (!_isMaintenance) 'التكلفة: $costLabel',
                    if (_isFinished) 'إجمالي البيع: $sellLabel',
                  ].join(' · '),
                  style: const TextStyle(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    if (_canAdjustQty)
                      _QtyBtn(
                        label: '+',
                        color: AppColors.success,
                        onTap: () => _adjustQty(item, 'add'),
                      ),
                    if (_canAdjustQty) const SizedBox(width: 8),
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => _openItemDetails(item),
                        child: const Text('التفاصيل'),
                      ),
                    ),
                    if (_canAdjustQty) const SizedBox(width: 8),
                    if (_canAdjustQty)
                      _QtyBtn(
                        label: '−',
                        color: AppColors.danger,
                        onTap: () => _adjustQty(item, 'remove'),
                      ),
                    const SizedBox(width: 4),
                    IconButton(
                      onPressed: () => _showItemActions(item),
                      icon: const Icon(Icons.more_vert),
                    ),
                  ],
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _AdjustQtyResult {
  const _AdjustQtyResult({required this.qty, required this.date});

  final double qty;
  final DateTime date;
}

/// Owns its Text controller so dispose happens with the dialog route, not after.
class _AdjustQtyDialog extends StatefulWidget {
  const _AdjustQtyDialog({
    required this.title,
    required this.initialQty,
  });

  final String title;
  final String initialQty;

  @override
  State<_AdjustQtyDialog> createState() => _AdjustQtyDialogState();
}

class _AdjustQtyDialogState extends State<_AdjustQtyDialog> {
  late final TextEditingController _qtyCtrl;
  DateTime _movementDate = DateTime.now();
  String? _qtyError;

  @override
  void initState() {
    super.initState();
    _qtyCtrl = TextEditingController(text: widget.initialQty);
  }

  @override
  void dispose() {
    _qtyCtrl.dispose();
    super.dispose();
  }

  void _confirm() {
    final n = double.tryParse(_qtyCtrl.text.trim().replaceAll(',', '.'));
    if (n == null) {
      setState(() => _qtyError = 'يجب إدخال قيمة صحيحة');
      return;
    }
    Navigator.pop(context, _AdjustQtyResult(qty: n, date: _movementDate));
  }

  @override
  Widget build(BuildContext context) {
    final dateLabel = DateFormat('yyyy-MM-dd').format(_movementDate);
    return AlertDialog(
      title: Text(widget.title),
      scrollable: true,
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(
              controller: _qtyCtrl,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              autofocus: true,
              decoration: InputDecoration(
                hintText: 'الكمية',
                errorText: _qtyError,
              ),
              onChanged: (_) {
                if (_qtyError != null) setState(() => _qtyError = null);
              },
            ),
            const SizedBox(height: 12),
            const Text('تاريخ الحركة'),
            TextButton.icon(
              onPressed: () async {
                final picked = await showDatePicker(
                  context: context,
                  initialDate: _movementDate,
                  firstDate: DateTime(2020),
                  lastDate: DateTime.now(),
                  locale: const Locale('ar'),
                );
                if (picked == null || !mounted) return;
                setState(() => _movementDate = picked);
              },
              icon: const Icon(Icons.calendar_month_outlined),
              label: Text(dateLabel),
            ),
            const Text(
              'تظهر هذه الكمية في تقارير المخزون حسب التاريخ المختار',
              style: TextStyle(fontSize: 12, color: Colors.black54),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('إلغاء'),
        ),
        ElevatedButton(
          onPressed: _confirm,
          child: const Text('تأكيد'),
        ),
      ],
    );
  }
}

class _QtyBtn extends StatelessWidget {
  const _QtyBtn({
    required this.label,
    required this.color,
    required this.onTap,
  });

  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: color,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: SizedBox(
          width: 42,
          height: 40,
          child: Center(
            child: Text(
              label,
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w900,
                fontSize: 18,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
