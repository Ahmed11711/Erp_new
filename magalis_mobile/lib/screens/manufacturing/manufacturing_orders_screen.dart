import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/manufacturing_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import 'manufacturing_confirmation_screen.dart';
import 'product_search_sheet.dart';

/// أوامر التصنيع — mirrors Angular `ManufacturingOrdersComponent`.
class ManufacturingOrdersScreen extends StatefulWidget {
  const ManufacturingOrdersScreen({super.key});

  @override
  State<ManufacturingOrdersScreen> createState() =>
      _ManufacturingOrdersScreenState();
}

class _ManufacturingOrdersScreenState extends State<ManufacturingOrdersScreen> {
  List<ManufactureOrder> _all = [];
  List<ManufactureOrder> _visible = [];
  List<ManufactureProductOption> _products = [];
  String? _productName;
  String? _status;
  DateTime? _date;
  bool _loading = true;
  String? _error;
  String? _deletingId;

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
      final rows = await ManufacturingApi.instance.confirmed();
      if (!mounted) return;
      final seen = <String>{};
      final products = <ManufactureProductOption>[];
      for (final row in rows) {
        if (seen.add(row.productName)) {
          products.add(
            ManufactureProductOption(id: row.productName, name: row.productName),
          );
        }
      }
      setState(() {
        _all = rows;
        _products = products;
        _loading = false;
      });
      _applyFilter();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'تعذر تحميل أوامر التصنيع';
        _loading = false;
      });
    }
  }

  void _applyFilter() {
    var rows = [..._all];
    final name = _productName;
    if (name != null && name.isNotEmpty) {
      rows = rows.where((e) => e.productName == name).toList();
    }
    if (_status != null) {
      rows = rows.where((e) => e.status == _status).toList();
    }
    if (_date != null) {
      final d =
          '${_date!.year}-${_date!.month.toString().padLeft(2, '0')}-${_date!.day.toString().padLeft(2, '0')}';
      final dAlt = '${_date!.year}-${_date!.month}-${_date!.day}';
      rows = rows.where((e) => e.date == d || e.date == dAlt || e.date.startsWith(d)).toList();
    }
    setState(() => _visible = rows);
  }

  Future<void> _finish(ManufactureOrder order) async {
    try {
      await ManufacturingApi.instance.markDone(order.id);
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: AppColors.danger),
      );
    }
  }

  Future<void> _delete(ManufactureOrder order) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('حذف أمر التصنيع؟'),
        content: Text('سيتم حذف أمر «${order.productName}» وعكس أثره.'),
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
    setState(() => _deletingId = order.id);
    try {
      await ManufacturingApi.instance.deleteOrder(order.id);
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: AppColors.danger),
      );
    } finally {
      if (mounted) setState(() => _deletingId = null);
    }
  }

  Future<void> _showDeleted() async {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _DeletedOrdersSheet(),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('أوامر التصنيع'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
          actions: [
            IconButton(
              tooltip: 'سجل المحذوف',
              onPressed: _showDeleted,
              icon: const Icon(Icons.history),
            ),
            IconButton(
              tooltip: 'تأكيد أمر جديد',
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => const ManufacturingConfirmationScreen(),
                  ),
                );
              },
              icon: const Icon(Icons.add_circle_outline),
            ),
          ],
        ),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: () {
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => const ManufacturingConfirmationScreen(),
              ),
            );
          },
          backgroundColor: AppColors.primary,
          icon: const Icon(Icons.add, color: Colors.white),
          label: const Text('تأكيد أمر'),
        ),
        body: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
              child: Column(
                children: [
                  OutlinedButton.icon(
                    onPressed: () async {
                      final picked = await showProductSearchSheet(
                        context: context,
                        title: 'البحث بالمنتج',
                        items: _products,
                      );
                      if (picked == null) return;
                      setState(() => _productName = picked.name);
                      _applyFilter();
                    },
                    icon: const Icon(Icons.search),
                    label: Text(_productName ?? 'البحث'),
                  ),
                  if (_productName != null)
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: TextButton(
                        onPressed: () {
                          setState(() => _productName = null);
                          _applyFilter();
                        },
                        child: const Text('إلغاء فلتر المنتج'),
                      ),
                    ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          value: _status,
                          decoration: const InputDecoration(hintText: 'حالة التصنيع'),
                          items: const [
                            DropdownMenuItem(value: 'تم الانتهاء', child: Text('تم الانتهاء')),
                            DropdownMenuItem(value: 'في التصنيع', child: Text('في التصنيع')),
                          ],
                          onChanged: (v) {
                            setState(() => _status = v);
                            _applyFilter();
                          },
                        ),
                      ),
                      IconButton(
                        tooltip: 'التاريخ',
                        onPressed: () async {
                          final picked = await showDatePicker(
                            context: context,
                            initialDate: _date ?? DateTime.now(),
                            firstDate: DateTime(2018),
                            lastDate: DateTime.now().add(const Duration(days: 365)),
                            locale: const Locale('ar'),
                          );
                          if (picked == null) return;
                          setState(() => _date = picked);
                          _applyFilter();
                        },
                        icon: Icon(
                          Icons.event,
                          color: _date == null ? AppColors.textMuted : AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            Expanded(child: _body()),
          ],
        ),
      ),
    );
  }

  Widget _body() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!, style: const TextStyle(color: AppColors.danger)),
            const SizedBox(height: 12),
            ElevatedButton(onPressed: _load, child: const Text('إعادة المحاولة')),
          ],
        ),
      );
    }
    if (_visible.isEmpty) {
      return const Center(child: Text('لا توجد أوامر تصنيع مسجّلة.'));
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 88),
        itemCount: _visible.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) {
          final o = _visible[i];
          return Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  o.productName,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.navy,
                  ),
                ),
                Text(
                  [
                    o.status,
                    o.date,
                    'كمية ${o.quantity}',
                    if (o.userName != null) o.userName!,
                  ].join(' · '),
                  style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
                ),
                Text(
                  Formatters.moneyPlain(o.total),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                Row(
                  children: [
                    if (o.status == 'في التصنيع')
                      TextButton(
                        onPressed: () => _finish(o),
                        child: const Text('تم الانتهاء'),
                      ),
                    TextButton(
                      onPressed: _deletingId == o.id ? null : () => _delete(o),
                      style: TextButton.styleFrom(foregroundColor: AppColors.danger),
                      child: Text(_deletingId == o.id ? 'جاري الحذف…' : 'حذف'),
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

class _DeletedOrdersSheet extends StatefulWidget {
  const _DeletedOrdersSheet();

  @override
  State<_DeletedOrdersSheet> createState() => _DeletedOrdersSheetState();
}

class _DeletedOrdersSheetState extends State<_DeletedOrdersSheet> {
  List<ManufactureOrder> _rows = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final rows = await ManufacturingApi.instance.confirmedDeleted();
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
        _error = 'تعذر تحميل سجل الأوامر المحذوفة.';
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
                padding: EdgeInsets.all(16),
                child: Text(
                  'سجل الأوامر المحذوفة',
                  style: TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                    color: AppColors.navy,
                  ),
                ),
              ),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator())
                    : _error != null
                        ? Center(child: Text(_error!, style: const TextStyle(color: AppColors.danger)))
                        : _rows.isEmpty
                            ? const Center(child: Text('لا توجد أوامر محذوفة.'))
                            : ListView.separated(
                                padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                                itemCount: _rows.length,
                                separatorBuilder: (_, _) => const Divider(),
                                itemBuilder: (context, i) {
                                  final o = _rows[i];
                                  return ListTile(
                                    title: Text(o.productName),
                                    subtitle: Text(
                                      [
                                        'أمر #${o.id}',
                                        o.status,
                                        if (o.deletedAt != null) o.deletedAt!,
                                        if (o.deletedByName != null) o.deletedByName!,
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
