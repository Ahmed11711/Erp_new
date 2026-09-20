import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/inventory_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import 'monthly_inventory_screen.dart';
import 'warehouse_categories_screen.dart';

/// Hub for الجرد الشهري — mirrors Angular `listwarhouse`.
class MonthlyInventoryHubScreen extends StatefulWidget {
  const MonthlyInventoryHubScreen({super.key});

  @override
  State<MonthlyInventoryHubScreen> createState() =>
      _MonthlyInventoryHubScreenState();
}

class _MonthlyInventoryHubScreenState extends State<MonthlyInventoryHubScreen> {
  List<WarehouseRow> _rows = [];
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

  void _openRecords(WarehouseRow row) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => MonthlyInventoryScreen(warehouse: row.name),
      ),
    );
  }

  void _openCategories(WarehouseRow row) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => WarehouseCategoriesScreen(warehouse: row.name),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        title: const Text('الجرد الشهري'),
        actions: [
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
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
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                    itemCount: _rows.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, i) {
                      final row = _rows[i];
                      return Material(
                        color: AppColors.surface,
                        borderRadius: BorderRadius.circular(14),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: () => _openCategories(row),
                          child: Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: AppColors.border),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Container(
                                      width: 40,
                                      height: 40,
                                      decoration: BoxDecoration(
                                        color: AppColors.primaryBg,
                                        borderRadius:
                                            BorderRadius.circular(12),
                                      ),
                                      child: const Icon(
                                        Icons.warehouse_outlined,
                                        color: AppColors.primary,
                                        size: 20,
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            row.name,
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w800,
                                              color: AppColors.navy,
                                            ),
                                          ),
                                          const SizedBox(height: 3),
                                          Text(
                                            'الكمية: ${Formatters.moneyPlain(row.quantityTotal)} · القيمة: ${Formatters.moneyPlain(row.balance)}',
                                            style: const TextStyle(
                                              color: AppColors.textSecondary,
                                              fontWeight: FontWeight.w600,
                                              fontSize: 12,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 8,
                                  children: [
                                    _ActionChip(
                                      label: 'أصناف وجرد',
                                      icon: Icons.inventory_2_outlined,
                                      filled: true,
                                      onTap: () => _openCategories(row),
                                    ),
                                    _ActionChip(
                                      label: 'سجلات الجرد',
                                      icon: Icons.history_rounded,
                                      onTap: () => _openRecords(row),
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
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
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
