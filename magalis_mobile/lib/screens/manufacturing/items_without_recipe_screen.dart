import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/manufacturing_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import 'add_recipe_screen.dart';

/// أصناف بدون وصفة — mirrors Angular `ItemsWithoutRecipeReportComponent`.
class ItemsWithoutRecipeScreen extends StatefulWidget {
  const ItemsWithoutRecipeScreen({super.key});

  @override
  State<ItemsWithoutRecipeScreen> createState() =>
      _ItemsWithoutRecipeScreenState();
}

class _ItemsWithoutRecipeScreenState extends State<ItemsWithoutRecipeScreen> {
  final _searchCtrl = TextEditingController();
  String _warehouse = '';
  String _productType = '';
  List<ItemWithoutRecipe> _rows = [];
  int _total = 0;
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
      final res = await ManufacturingApi.instance.itemsWithoutRecipes(
        warehouse: _warehouse,
        productType: _productType,
        search: _searchCtrl.text,
      );
      if (!mounted) return;
      setState(() {
        _rows = res.items;
        _total = res.total;
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
        _error = 'تعذر تحميل التقرير';
        _loading = false;
      });
    }
  }

  void _openAdd(ItemWithoutRecipe row) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => AddRecipeScreen(
          preselectProductId: row.id,
          preselectWarehouse: row.warehouse,
          preselectProductName: row.name,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('أصناف بدون وصفة'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
          actions: [
            IconButton(
              tooltip: 'إضافة وصفة',
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const AddRecipeScreen()),
                );
              },
              icon: const Icon(Icons.add_circle_outline),
            ),
          ],
        ),
        body: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'الأصناف الأساسية (منتج تام / تحت التشغيل) التي لا تملك وصفة تصنيع.',
                    style: TextStyle(color: AppColors.textSecondary, fontSize: 12),
                  ),
                  const SizedBox(height: 8),
                  DropdownButtonFormField<String>(
                    value: _warehouse,
                    decoration: const InputDecoration(hintText: 'المخزن'),
                    items: const [
                      DropdownMenuItem(value: '', child: Text('كل المخازن')),
                      DropdownMenuItem(value: 'مخزن منتج تام', child: Text('مخزن منتج تام')),
                      DropdownMenuItem(
                        value: 'مخزن منتج تحت التشغيل',
                        child: Text('مخزن منتج تحت التشغيل'),
                      ),
                    ],
                    onChanged: (v) {
                      setState(() => _warehouse = v ?? '');
                      _load();
                    },
                  ),
                  const SizedBox(height: 8),
                  DropdownButtonFormField<String>(
                    value: _productType,
                    decoration: const InputDecoration(hintText: 'النوع'),
                    items: const [
                      DropdownMenuItem(value: '', child: Text('كل الأنواع')),
                      DropdownMenuItem(value: 'finished', child: Text('منتج تام')),
                      DropdownMenuItem(value: 'semi_finished', child: Text('تحت التشغيل')),
                    ],
                    onChanged: (v) {
                      setState(() => _productType = v ?? '');
                      _load();
                    },
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _searchCtrl,
                    onSubmitted: (_) => _load(),
                    decoration: InputDecoration(
                      hintText: 'بحث بالاسم أو الكود...',
                      prefixIcon: const Icon(Icons.search),
                      suffixIcon: IconButton(
                        onPressed: _load,
                        icon: const Icon(Icons.refresh),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'أصناف بدون وصفة: $_total',
                    style: const TextStyle(fontWeight: FontWeight.w800),
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
            ElevatedButton(onPressed: _load, child: const Text('إعادة المحاولة')),
          ],
        ),
      );
    }
    if (_rows.isEmpty) {
      return const Center(child: Text('لا توجد أصناف بدون وصفة ضمن الفلتر الحالي.'));
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
        itemCount: _rows.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) {
          final row = _rows[i];
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
                  row.name,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.navy,
                  ),
                ),
                Text(
                  [
                    if (row.itemCode != null) row.itemCode!,
                    if (row.warehouse != null) row.warehouse!,
                    if (row.productTypeLabel != null) row.productTypeLabel!,
                    if (row.color != null) row.color!,
                  ].join(' · '),
                  style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
                ),
                Text(
                  'كمية ${Formatters.moneyPlain(row.quantity)} · سعر ${Formatters.moneyPlain(row.price)}',
                ),
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: TextButton(
                    onPressed: () => _openAdd(row),
                    child: const Text('إضافة وصفة'),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
