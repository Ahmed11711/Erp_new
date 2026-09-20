import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/inventory_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';

/// Saved monthly snapshot records — mirrors Angular `monthlyinventory`.
class MonthlyInventoryScreen extends StatefulWidget {
  const MonthlyInventoryScreen({
    super.key,
    required this.warehouse,
  });

  final String warehouse;

  @override
  State<MonthlyInventoryScreen> createState() => _MonthlyInventoryScreenState();
}

class _MonthlyInventoryScreenState extends State<MonthlyInventoryScreen> {
  final _searchCtrl = TextEditingController();
  late String _warehouse;
  late String _month;
  List<MonthlyInventoryLine> _items = [];
  int _total = 0;
  int _page = 1;
  bool _loading = true;
  bool _loadingMore = false;
  bool _saving = false;
  bool _sort = false;
  String? _error;
  Timer? _debounce;

  static const _pageSize = 50;

  bool get _isFinished => _warehouse == 'مخزن منتج تام';
  bool get _isMaintenance => _warehouse == 'مخزن صيانة';

  @override
  void initState() {
    super.initState();
    _warehouse = widget.warehouse;
    _month = InventoryApi.previousMonthValue();
    _load(reset: true);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load({bool reset = false}) async {
    if (_warehouse.isEmpty) {
      setState(() {
        _items = [];
        _total = 0;
        _page = 1;
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
      final page = await InventoryApi.instance.monthlyDetails(
        warehouse: _warehouse,
        month: _month,
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
        _error = 'تعذر تحميل سجلات الجرد';
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

  Future<void> _pickMonth() async {
    final parts = _month.split('-');
    final initial = DateTime(
      int.tryParse(parts.first) ?? DateTime.now().year,
      int.tryParse(parts.length > 1 ? parts[1] : '1') ?? 1,
    );
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
      helpText: 'اختر شهراً',
      locale: const Locale('ar'),
    );
    if (picked == null) return;
    setState(() {
      _month =
          '${picked.year}-${picked.month.toString().padLeft(2, '0')}';
    });
    _load(reset: true);
  }

  Future<void> _pickWarehouse() async {
    List<String> names = kStandardWarehouses.map((w) => w.nameAr).toList();
    try {
      final rows = await InventoryApi.instance.listWarehouses();
      if (rows.isNotEmpty) {
        names = rows.map((r) => r.name).toList();
      }
    } catch (_) {
      /* keep standard list */
    }
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

  Future<void> _saveSnapshot() async {
    if (_warehouse.isEmpty) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('تأكيد حفظ لقطة الجرد الشهري؟'),
        content: Text(
          'سيتم تسجيل لقطة مخزون للشهر $_month بناءً على الأرصدة الحالية.',
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
        month: _month,
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
      await _load(reset: true);
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        title: Text('سجلات الجرد'),
        actions: [
          IconButton(
            tooltip: 'حفظ لقطة جرد',
            onPressed: _saving || _warehouse.isEmpty ? null : _saveSnapshot,
            icon: _saving
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.save_outlined),
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
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFE8F4FC),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFB6D9F0)),
              ),
              child: const Text(
                'الجدول يعرض السجلات المحفوظة سابقاً للشهر المحدد. إن ظهر فارغاً فلم يُسجَّل جرد لهذا الشهر بعد.',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: AppColors.navy,
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _pickWarehouse,
                    icon: const Icon(Icons.warehouse_outlined, size: 18),
                    label: Text(
                      _warehouse.isEmpty ? 'اختر المخزن' : _warehouse,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                OutlinedButton.icon(
                  onPressed: _warehouse.isEmpty ? null : _pickMonth,
                  icon: const Icon(Icons.calendar_month_outlined, size: 18),
                  label: Text(_month),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
            child: TextField(
              controller: _searchCtrl,
              onChanged: _onSearch,
              enabled: _warehouse.isNotEmpty,
              decoration: InputDecoration(
                hintText: 'بحث باسم الصنف...',
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
                        color: _sort ? AppColors.primary : AppColors.textMuted,
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
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text(
                'العدد: $_total',
                style: const TextStyle(
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w700,
                  fontSize: 12,
                ),
              ),
            ),
          ),
          const SizedBox(height: 6),
          Expanded(child: _buildBody()),
        ],
      ),
      floatingActionButton: _warehouse.isEmpty
          ? null
          : FloatingActionButton.extended(
              onPressed: _saving ? null : _saveSnapshot,
              icon: const Icon(Icons.playlist_add_check_rounded),
              label: const Text('حفظ لقطة جرد'),
            ),
    );
  }

  Widget _buildBody() {
    if (_warehouse.isEmpty) {
      return const Center(
        child: Text(
          'اختر مخزناً لعرض سجلات الجرد الشهري',
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
          'لا توجد سجلات محفوظة لهذا الشهر',
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
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 88),
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
          final valueLabel = _isFinished
              ? Formatters.moneyPlain(item.sellTotalPrice)
              : Formatters.moneyPlain(item.totalPrice);
          final valueTitle = _isFinished ? 'إجمالي البيع' : 'التكلفة';
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
                const SizedBox(height: 6),
                Wrap(
                  spacing: 12,
                  runSpacing: 4,
                  children: [
                    _meta('الوحدة', item.unit),
                    if (!_isMaintenance)
                      _meta('الكمية', Formatters.moneyPlain(item.quantity)),
                    if (_isFinished && item.sellPrice != null)
                      _meta(
                        'سعر البيع',
                        Formatters.moneyPlain(item.sellPrice!),
                      ),
                    if (!_isMaintenance) _meta(valueTitle, valueLabel),
                    _meta('بواسطة', item.by),
                  ],
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _meta(String label, String value) {
    return Text(
      '$label: $value',
      style: const TextStyle(
        color: AppColors.textSecondary,
        fontWeight: FontWeight.w600,
        fontSize: 12,
      ),
    );
  }
}
