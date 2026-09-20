import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/categories_api.dart';
import '../../theme/app_colors.dart';

/// Same warehouse choices as Angular units / classifications / production dialogs.
const kLookupWarehouses = <String>[
  'مخزن مواد خام',
  'مخزن منتج تحت التشغيل',
  'مخزن منتج تام',
];

enum CategoryLookupKind {
  units,
  classifications,
  productions,
}

/// CRUD list for وحدة القياس / تصنيفات الأصناف / خطوط الإنتاج.
class CategoryLookupAdminScreen extends StatefulWidget {
  const CategoryLookupAdminScreen({super.key, required this.kind});

  final CategoryLookupKind kind;

  @override
  State<CategoryLookupAdminScreen> createState() =>
      _CategoryLookupAdminScreenState();
}

class _CategoryLookupAdminScreenState extends State<CategoryLookupAdminScreen> {
  List<LookupOption> _items = [];
  bool _loading = true;
  String? _error;

  String get _title {
    switch (widget.kind) {
      case CategoryLookupKind.units:
        return 'وحدات القياس';
      case CategoryLookupKind.classifications:
        return 'تصنيفات الأصناف';
      case CategoryLookupKind.productions:
        return 'خطوط الإنتاج';
    }
  }

  String get _nameColumn {
    switch (widget.kind) {
      case CategoryLookupKind.units:
        return 'وحدة القياس';
      case CategoryLookupKind.classifications:
        return 'التصنيف';
      case CategoryLookupKind.productions:
        return 'خط الإنتاج';
    }
  }

  String get _nameHint {
    switch (widget.kind) {
      case CategoryLookupKind.units:
        return 'وحدة القياس';
      case CategoryLookupKind.classifications:
        return 'اسم التصنيف';
      case CategoryLookupKind.productions:
        return 'خط الإنتاج';
    }
  }

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
      final List<LookupOption> rows;
      switch (widget.kind) {
        case CategoryLookupKind.units:
          rows = await CategoriesApi.instance.measurements();
          break;
        case CategoryLookupKind.classifications:
          rows = await CategoriesApi.instance.classifications();
          break;
        case CategoryLookupKind.productions:
          rows = await CategoriesApi.instance.productions();
          break;
      }
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
        _error = 'تعذر تحميل البيانات';
        _loading = false;
      });
    }
  }

  Future<void> _create(String warehouse, String name) async {
    switch (widget.kind) {
      case CategoryLookupKind.units:
        await CategoriesApi.instance.createMeasurement(
          warehouse: warehouse,
          unit: name,
        );
        break;
      case CategoryLookupKind.classifications:
        await CategoriesApi.instance.createClassification(
          warehouse: warehouse,
          classificationName: name,
        );
        break;
      case CategoryLookupKind.productions:
        await CategoriesApi.instance.createProduction(
          warehouse: warehouse,
          productionLine: name,
        );
        break;
    }
  }

  Future<void> _delete(String id) async {
    switch (widget.kind) {
      case CategoryLookupKind.units:
        await CategoriesApi.instance.deleteMeasurement(id);
        break;
      case CategoryLookupKind.classifications:
        await CategoriesApi.instance.deleteClassification(id);
        break;
      case CategoryLookupKind.productions:
        await CategoriesApi.instance.deleteProduction(id);
        break;
    }
  }

  Future<void> _showAddDialog() async {
    final result = await showDialog<_AddLookupResult>(
      context: context,
      builder: (ctx) => _AddLookupDialog(
        title: widget.kind == CategoryLookupKind.classifications
            ? 'إضافة تصنيف جديد'
            : 'إضافة $_nameColumn',
        nameHint: _nameHint,
      ),
    );
    if (result == null || !mounted) return;

    try {
      await _create(result.warehouse, result.name);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تمت الإضافة'),
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
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تعذر الإضافة'),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  Future<void> _confirmDelete(LookupOption item) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('حذف؟'),
        content: Text('سيتم حذف «${item.label}».'),
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
      await _delete(item.id);
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
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تعذر الحذف'),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

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
            IconButton(
              tooltip: 'إضافة',
              onPressed: _showAddDialog,
              icon: const Icon(Icons.add_circle_outline),
            ),
          ],
        ),
        floatingActionButton: FloatingActionButton(
          onPressed: _showAddDialog,
          backgroundColor: AppColors.primary,
          child: const Icon(Icons.add, color: Colors.white),
        ),
        body: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
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
                style: const TextStyle(color: AppColors.danger),
              ),
              const SizedBox(height: 12),
              FilledButton(onPressed: _load, child: const Text('إعادة المحاولة')),
            ],
          ),
        ),
      );
    }
    if (_items.isEmpty) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          primary: true,
          physics: const AlwaysScrollableScrollPhysics(),
          children: const [
            SizedBox(height: 120),
            Icon(Icons.inbox_outlined, size: 48, color: AppColors.textMuted),
            SizedBox(height: 12),
            Center(
              child: Text(
                'لا توجد بيانات بعد',
                style: TextStyle(color: AppColors.textSecondary),
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        primary: true,
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 88),
        itemCount: _items.length + 1,
        separatorBuilder: (context, index) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          if (index == 0) {
            return Padding(
              padding: const EdgeInsets.only(bottom: 4),
              child: Row(
                children: [
                  Expanded(
                    flex: 2,
                    child: Text(
                      _nameColumn,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: AppColors.textSecondary,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  const Expanded(
                    child: Text(
                      'المخزن',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        color: AppColors.textSecondary,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  const SizedBox(width: 40),
                ],
              ),
            );
          }
          final item = _items[index - 1];
          return Material(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(12),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppColors.border),
              ),
              child: Row(
                children: [
                  Expanded(
                    flex: 2,
                    child: Text(
                      item.label,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        color: AppColors.navy,
                      ),
                    ),
                  ),
                  Expanded(
                    child: Text(
                      item.warehouse ?? '—',
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'حذف',
                    onPressed: () => _confirmDelete(item),
                    icon: const Icon(Icons.delete_outline, color: AppColors.danger),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _AddLookupResult {
  const _AddLookupResult({required this.warehouse, required this.name});

  final String warehouse;
  final String name;
}

/// Owns its Text controller so dispose happens with the dialog route, not after.
class _AddLookupDialog extends StatefulWidget {
  const _AddLookupDialog({
    required this.title,
    required this.nameHint,
  });

  final String title;
  final String nameHint;

  @override
  State<_AddLookupDialog> createState() => _AddLookupDialogState();
}

class _AddLookupDialogState extends State<_AddLookupDialog> {
  final _formKey = GlobalKey<FormState>();
  final _nameCtrl = TextEditingController();
  String? _warehouse;

  @override
  void dispose() {
    _nameCtrl.dispose();
    super.dispose();
  }

  void _submit() {
    if (_formKey.currentState?.validate() != true) return;
    final warehouse = _warehouse;
    final name = _nameCtrl.text.trim();
    if (warehouse == null || warehouse.isEmpty || name.isEmpty) return;
    Navigator.pop(
      context,
      _AddLookupResult(warehouse: warehouse, name: name),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.title),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                value: _warehouse,
                decoration: const InputDecoration(
                  labelText: 'المخزن',
                  border: OutlineInputBorder(),
                ),
                items: [
                  for (final w in kLookupWarehouses)
                    DropdownMenuItem(value: w, child: Text(w)),
                ],
                onChanged: (v) => setState(() => _warehouse = v),
                validator: (v) =>
                    (v == null || v.isEmpty) ? 'اختر المخزن' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _nameCtrl,
                autofocus: true,
                decoration: InputDecoration(
                  labelText: widget.nameHint,
                  border: const OutlineInputBorder(),
                ),
                validator: (v) =>
                    (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
                onFieldSubmitted: (_) => _submit(),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('إلغاء'),
        ),
        FilledButton(
          onPressed: _submit,
          child: const Text('حفظ'),
        ),
      ],
    );
  }
}
