import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/recipes_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import 'add_recipe_screen.dart';

/// وصفات التصنيع — mirrors Angular `ManufacturingRecipesComponent`.
class RecipesListScreen extends StatefulWidget {
  const RecipesListScreen({super.key});

  @override
  State<RecipesListScreen> createState() => _RecipesListScreenState();
}

class _RecipesListScreenState extends State<RecipesListScreen> {
  final _searchCtrl = TextEditingController();
  List<RecipeListItem> _items = [];
  bool _loading = true;
  String? _error;
  String? _expandedId;
  RecipeDetail? _detail;
  bool _loadingDetail = false;

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

  List<RecipeListItem> get _visible {
    final q = _searchCtrl.text.trim().toLowerCase();
    if (q.isEmpty) return _items;
    return _items.where((e) {
      return e.name.toLowerCase().contains(q) ||
          (e.description ?? '').toLowerCase().contains(q) ||
          (e.outputItemName ?? '').toLowerCase().contains(q);
    }).toList();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final rows = await RecipesApi.instance.list();
      if (!mounted) return;
      setState(() {
        _items = rows;
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
        _error = 'تعذر تحميل الوصفات';
        _loading = false;
      });
    }
  }

  Future<void> _toggleDetail(String id) async {
    if (_expandedId == id) {
      setState(() {
        _expandedId = null;
        _detail = null;
      });
      return;
    }
    setState(() {
      _expandedId = id;
      _loadingDetail = true;
      _detail = null;
    });
    try {
      final detail = await RecipesApi.instance.show(id);
      if (!mounted) return;
      setState(() {
        _detail = detail;
        _loadingDetail = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _loadingDetail = false);
    }
  }

  Future<void> _openAdd({String? recipeId, String? duplicateFromId}) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => AddRecipeScreen(
          recipeId: recipeId,
          duplicateFromId: duplicateFromId,
        ),
      ),
    );
    if (mounted) _load();
  }

  Future<void> _confirmDelete(RecipeListItem item) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('حذف الوصفة؟'),
        content: Text('سيتم حذف «${item.name}» وجميع بياناتها المرتبطة.'),
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
      await RecipesApi.instance.delete(item.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تم حذف الوصفة'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      if (_expandedId == item.id) {
        _expandedId = null;
        _detail = null;
      }
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

  @override
  Widget build(BuildContext context) {
    final items = _visible;
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('وصفات التصنيع'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
          actions: [
            IconButton(
              tooltip: 'إضافة وصفة',
              onPressed: () => _openAdd(),
              icon: const Icon(Icons.add_circle_outline),
            ),
          ],
        ),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: () => _openAdd(),
          backgroundColor: AppColors.primary,
          icon: const Icon(Icons.add, color: Colors.white),
          label: const Text('إضافة وصفة'),
        ),
        body: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
              child: TextField(
                controller: _searchCtrl,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  hintText: 'اسم الوصفة، الوصف، أو المنتج النهائي…',
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
                  'جميع الوصفات (${items.length})',
                  style: const TextStyle(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 6),
            Expanded(child: _buildBody(items)),
          ],
        ),
      ),
    );
  }

  Widget _buildBody(List<RecipeListItem> items) {
    if (_loading) return const Center(child: CircularProgressIndicator());
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
              ElevatedButton(onPressed: _load, child: const Text('إعادة المحاولة')),
            ],
          ),
        ),
      );
    }
    if (items.isEmpty) {
      return const Center(
        child: Text(
          'لا توجد وصفات مسجلة.',
          style: TextStyle(
            color: AppColors.textMuted,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 88),
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) => _recipeCard(items[i]),
      ),
    );
  }

  Widget _recipeCard(RecipeListItem item) {
    final expanded = _expandedId == item.id;
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: expanded ? AppColors.primary : AppColors.border,
        ),
      ),
      child: Column(
        children: [
          ListTile(
            onTap: () => _toggleDetail(item.id),
            title: Text(
              item.name,
              style: const TextStyle(
                fontWeight: FontWeight.w800,
                color: AppColors.navy,
              ),
            ),
            subtitle: Text(
              [
                if (item.description != null) item.description!,
                if (item.outputItemName != null) item.outputItemName!,
                '${item.ingredientsCount} مكون',
                if (item.extraCostsCount > 0) '${item.extraCostsCount} تكلفة إضافية',
              ].join(' · '),
            ),
            trailing: Icon(expanded ? Icons.expand_less : Icons.expand_more),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(8, 0, 8, 8),
            child: Row(
              children: [
                TextButton(
                  onPressed: () => _toggleDetail(item.id),
                  child: Text(expanded ? 'إخفاء' : 'تفاصيل'),
                ),
                TextButton(
                  onPressed: () => _openAdd(recipeId: item.id),
                  child: const Text('تعديل'),
                ),
                TextButton(
                  onPressed: () => _openAdd(duplicateFromId: item.id),
                  child: const Text('نسخ لمنتج جديد'),
                ),
                TextButton(
                  onPressed: () => _confirmDelete(item),
                  style: TextButton.styleFrom(foregroundColor: AppColors.danger),
                  child: const Text('حذف'),
                ),
              ],
            ),
          ),
          if (expanded) _detailBody(),
        ],
      ),
    );
  }

  Widget _detailBody() {
    if (_loadingDetail) {
      return const Padding(
        padding: EdgeInsets.all(16),
        child: Center(child: CircularProgressIndicator()),
      );
    }
    final d = _detail;
    if (d == null) {
      return const Padding(
        padding: EdgeInsets.all(16),
        child: Text('تعذر تحميل التفاصيل', style: TextStyle(color: AppColors.danger)),
      );
    }
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (d.outputName != null) ...[
            const Text(
              'المنتج النهائي',
              style: TextStyle(fontWeight: FontWeight.w800, color: AppColors.navy),
            ),
            Text(
              [
                d.outputName!,
                if (d.outputCode != null) d.outputCode!,
              ].join(' · '),
            ),
            const SizedBox(height: 10),
          ],
          Text(
            'المواد الخام (${d.ingredients.length})',
            style: const TextStyle(fontWeight: FontWeight.w800, color: AppColors.navy),
          ),
          const SizedBox(height: 6),
          for (final ing in d.ingredients)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                children: [
                  Expanded(child: Text(ing.name)),
                  Text('${ing.quantity}'),
                  const SizedBox(width: 8),
                  Text(
                    Formatters.moneyPlain(ing.total),
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            ),
          if (d.extraCosts.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              'التكاليف الإضافية (${d.extraCosts.length})',
              style: const TextStyle(fontWeight: FontWeight.w800, color: AppColors.navy),
            ),
            for (final ec in d.extraCosts)
              Text(
                '${ec.name} — ${ec.type == 'fixed' ? 'ثابت' : 'نسبة'} ${Formatters.moneyPlain(ec.value)}${ec.type == 'percentage' ? '%' : ''}',
              ),
          ],
          if (d.breakdown != null) ...[
            const SizedBox(height: 8),
            Text(
              'التكلفة النهائية: ${Formatters.moneyPlain(d.breakdown!.finalCost)}',
              style: const TextStyle(
                fontWeight: FontWeight.w900,
                color: AppColors.primary,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
