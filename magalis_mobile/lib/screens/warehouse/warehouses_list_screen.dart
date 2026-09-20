import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/inventory_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import 'monthly_inventory_screen.dart';
import 'warehouse_categories_screen.dart';
import 'warehouse_tracking_screen.dart';

/// قائمة المخازن — mirrors Angular `list-warehouse` (route `/warehouse/list`).
class WarehousesListScreen extends StatefulWidget {
  const WarehousesListScreen({super.key});

  @override
  State<WarehousesListScreen> createState() => _WarehousesListScreenState();
}

class _WarehousesListScreenState extends State<WarehousesListScreen> {
  final _searchCtrl = TextEditingController();
  List<WarehouseRow> _rows = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  List<WarehouseRow> get _visible {
    final q = _searchCtrl.text.trim().toLowerCase();
    if (q.isEmpty) return _rows;
    return _rows.where((r) => r.name.toLowerCase().contains(q)).toList();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final rows = await InventoryApi.instance.listWarehouses();
      if (!mounted) return;
      setState(() {
        _rows = rows;
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
        _error = 'تعذر تحميل المخازن';
        _loading = false;
      });
    }
  }

  void _openDetails(WarehouseRow row) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => WarehouseCategoriesScreen(warehouse: row.name),
      ),
    );
  }

  void _openTracking(WarehouseRow row) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => WarehouseTrackingScreen(warehouse: row.name),
      ),
    );
  }

  void _openInventoryRecords(WarehouseRow row) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MonthlyInventoryScreen(warehouse: row.name),
      ),
    );
  }

  Future<void> _delete(WarehouseRow row) async {
    final id = row.id;
    if (id == null) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('حذف المخزن؟'),
        content: Text('سيتم حذف «${row.name}» إن لم يكن مرتبطاً ببيانات.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('إلغاء'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.danger),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('حذف'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await InventoryApi.instance.deleteStock(id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تم الحذف'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  void _showActions(WarehouseRow row) {
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.inventory_2_outlined),
              title: const Text('التفاصيل / أصناف وجرد'),
              subtitle: const Text('أصناف المخزن والكميات وتسجيل الجرد'),
              onTap: () {
                Navigator.pop(ctx);
                _openDetails(row);
              },
            ),
            ListTile(
              leading: const Icon(Icons.history_rounded),
              title: const Text('سجلات الجرد'),
              subtitle: const Text('لقطات الجرد الشهري المحفوظة'),
              onTap: () {
                Navigator.pop(ctx);
                _openInventoryRecords(row);
              },
            ),
            ListTile(
              leading: const Icon(Icons.timeline_outlined),
              title: const Text('تتبع'),
              subtitle: const Text('سجل حركات المخزن'),
              onTap: () {
                Navigator.pop(ctx);
                _openTracking(row);
              },
            ),
            ListTile(
              leading: const Icon(Icons.swap_horiz_rounded),
              title: const Text('التحويلات'),
              subtitle: const Text('عرض الأصناف للتحويل/التعديل'),
              onTap: () {
                Navigator.pop(ctx);
                _openDetails(row);
              },
            ),
            if (row.id != null)
              ListTile(
                leading: const Icon(Icons.delete_outline, color: AppColors.danger),
                title: const Text(
                  'حذف',
                  style: TextStyle(color: AppColors.danger),
                ),
                onTap: () {
                  Navigator.pop(ctx);
                  _delete(row);
                },
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final items = _visible;

    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        title: const Text('المخازن'),
        actions: [
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
            child: TextField(
              controller: _searchCtrl,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                hintText: 'بحث في المخازن...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchCtrl.text.isEmpty
                    ? null
                    : IconButton(
                        onPressed: () {
                          _searchCtrl.clear();
                          setState(() {});
                        },
                        icon: const Icon(Icons.clear),
                      ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text(
                'العدد: ${items.length}',
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
                                onPressed: _load,
                                child: const Text('إعادة المحاولة'),
                              ),
                            ],
                          ),
                        ),
                      )
                    : items.isEmpty
                        ? const Center(
                            child: Text(
                              'لا توجد مخازن',
                              style: TextStyle(
                                color: AppColors.textMuted,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          )
                        : RefreshIndicator(
                            onRefresh: _load,
                            child: ListView.separated(
                              padding:
                                  const EdgeInsets.fromLTRB(16, 8, 16, 24),
                              itemCount: items.length,
                              separatorBuilder: (_, __) =>
                                  const SizedBox(height: 10),
                              itemBuilder: (context, i) {
                                final row = items[i];
                                return Material(
                                  color: AppColors.surface,
                                  borderRadius: BorderRadius.circular(14),
                                  child: InkWell(
                                    borderRadius: BorderRadius.circular(14),
                                    onTap: () => _openDetails(row),
                                    onLongPress: () => _showActions(row),
                                    child: Container(
                                      padding: const EdgeInsets.all(14),
                                      decoration: BoxDecoration(
                                        borderRadius:
                                            BorderRadius.circular(14),
                                        border: Border.all(
                                          color: AppColors.border,
                                        ),
                                      ),
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Row(
                                            children: [
                                              Container(
                                                width: 40,
                                                height: 40,
                                                decoration: BoxDecoration(
                                                  color: AppColors.primaryBg,
                                                  borderRadius:
                                                      BorderRadius.circular(
                                                    12,
                                                  ),
                                                ),
                                                child: const Icon(
                                                  Icons.warehouse_outlined,
                                                  color: AppColors.primary,
                                                  size: 20,
                                                ),
                                              ),
                                              const SizedBox(width: 12),
                                              Expanded(
                                                child: Text(
                                                  row.name,
                                                  style: const TextStyle(
                                                    fontWeight:
                                                        FontWeight.w800,
                                                    color: AppColors.navy,
                                                  ),
                                                ),
                                              ),
                                              IconButton(
                                                onPressed: () =>
                                                    _showActions(row),
                                                icon: const Icon(
                                                  Icons.more_vert,
                                                ),
                                              ),
                                            ],
                                          ),
                                          const SizedBox(height: 8),
                                          Text(
                                            'الكمية: ${Formatters.moneyPlain(row.quantityTotal)} · الرصيد: ${Formatters.moneyPlain(row.balance)}',
                                            style: const TextStyle(
                                              color: AppColors.textSecondary,
                                              fontWeight: FontWeight.w600,
                                              fontSize: 12,
                                            ),
                                          ),
                                          const SizedBox(height: 10),
                                          Wrap(
                                            spacing: 8,
                                            runSpacing: 8,
                                            children: [
                                              _ActionChip(
                                                label: 'أصناف وجرد',
                                                icon: Icons
                                                    .inventory_2_outlined,
                                                filled: true,
                                                onTap: () =>
                                                    _openDetails(row),
                                              ),
                                              _ActionChip(
                                                label: 'سجلات الجرد',
                                                icon: Icons.history_rounded,
                                                onTap: () =>
                                                    _openInventoryRecords(
                                                  row,
                                                ),
                                              ),
                                              _ActionChip(
                                                label: 'تتبع',
                                                icon: Icons
                                                    .timeline_outlined,
                                                onTap: () =>
                                                    _openTracking(row),
                                              ),
                                            ],
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
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

class _ActionChip extends StatelessWidget {
  const _ActionChip({
    required this.label,
    required this.icon,
    required this.onTap,
    this.filled = false,
  });

  final String label;
  final IconData icon;
  final VoidCallback onTap;
  final bool filled;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: filled ? AppColors.primary : AppColors.primaryBg,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                icon,
                size: 16,
                color: filled ? Colors.white : AppColors.primary,
              ),
              const SizedBox(width: 6),
              Text(
                label,
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 12,
                  color: filled ? Colors.white : AppColors.primary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
