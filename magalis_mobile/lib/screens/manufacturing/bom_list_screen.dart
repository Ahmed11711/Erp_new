import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/manufacturing_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import 'product_search_sheet.dart';

const _kWip = 'مخزن منتج تحت التشغيل';
const _kFinished = 'مخزن منتج تام';

/// قائمة المواد (BOM) — mirrors Angular `ManufacturingBomListComponent`.
class BomListScreen extends StatefulWidget {
  const BomListScreen({super.key});

  @override
  State<BomListScreen> createState() => _BomListScreenState();
}

class _BomListScreenState extends State<BomListScreen> {
  final _searchCtrl = TextEditingController();
  List<BomRow> _all = [];
  List<BomRow> _rows = [];
  String? _warehouse;
  String? _productId;
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

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final rows = await ManufacturingApi.instance.bomList();
      if (!mounted) return;
      setState(() {
        _all = rows;
        _loading = false;
      });
      _apply();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'تعذر تحميل قائمة المواد';
        _loading = false;
      });
    }
  }

  void _apply() {
    var rows = [..._all];
    if (_warehouse != null) {
      rows = rows.where((e) => e.warehouse == _warehouse).toList();
    }
    if (_productId != null) {
      rows = rows.where((e) => e.productId == _productId).toList();
    }
    final q = _searchCtrl.text.trim().toLowerCase();
    if (q.isNotEmpty) {
      rows = rows.where((e) {
        final hay = [
          e.productName,
          e.itemCode ?? '',
          e.color ?? '',
          e.warehouse ?? '',
          e.balance ?? '',
          e.minQty ?? '',
          '${e.total}',
        ].join(' ').toLowerCase();
        return hay.contains(q);
      }).toList();
    }
    setState(() => _rows = rows);
  }

  List<ManufactureProductOption> get _products {
    final seen = <String>{};
    final out = <ManufactureProductOption>[];
    final source = _warehouse == null
        ? _all
        : _all.where((e) => e.warehouse == _warehouse);
    for (final row in source) {
      if (seen.add(row.productId)) {
        out.add(ManufactureProductOption(id: row.productId, name: row.productName));
      }
    }
    return out;
  }

  String get _selectedProductName {
    for (final e in _all) {
      if (e.productId == _productId) return e.productName;
    }
    return 'اسم المنتج';
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('قائمة المواد (BOM)'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        body: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
              child: Column(
                children: [
                  DropdownButtonFormField<String>(
                    value: _warehouse,
                    decoration: const InputDecoration(hintText: 'نوع المنتج'),
                    items: const [
                      DropdownMenuItem(value: _kWip, child: Text('تحت التشغيل')),
                      DropdownMenuItem(value: _kFinished, child: Text('منتج تام')),
                    ],
                    onChanged: (v) {
                      setState(() {
                        _warehouse = v;
                        _productId = null;
                      });
                      _apply();
                    },
                  ),
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: () async {
                      final picked = await showProductSearchSheet(
                        context: context,
                        title: 'اسم المنتج',
                        items: _products,
                      );
                      if (picked == null) return;
                      setState(() => _productId = picked.id);
                      _apply();
                    },
                    icon: const Icon(Icons.search),
                    label: Text(
                      _productId == null ? 'اسم المنتج' : _selectedProductName,
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _searchCtrl,
                    onChanged: (_) => _apply(),
                    decoration: InputDecoration(
                      hintText: 'منتج، كود، لون، نوع، رصيد…',
                      prefixIcon: const Icon(Icons.search),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Align(
                alignment: AlignmentDirectional.centerStart,
                child: Text(
                  'قائمة المواد (${_rows.length})',
                  style: const TextStyle(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
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
            ElevatedButton(onPressed: _load, child: const Text('إعادة المحاولة')),
          ],
        ),
      );
    }
    if (_rows.isEmpty) {
      return const Center(child: Text('لا توجد بيانات.'));
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        itemCount: _rows.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) {
          final r = _rows[i];
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
                  r.productName,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.navy,
                  ),
                ),
                Text(
                  [
                    if (r.itemCode != null) r.itemCode!,
                    if (r.warehouse != null) r.warehouse!,
                    if (r.color != null) r.color!,
                  ].join(' · '),
                  style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
                ),
                const SizedBox(height: 4),
                Text(
                  [
                    if (r.balance != null) 'الرصيد ${r.balance}',
                    if (r.minQty != null) 'الكمية المطلوبة ${r.minQty}',
                    Formatters.moneyPlain(r.total),
                  ].join(' · '),
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
