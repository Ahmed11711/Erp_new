import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/recipes_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/excel_file_pick.dart';

/// استيراد وصفات من Excel — mirrors Angular add-recipe import panel.
class RecipeImportScreen extends StatefulWidget {
  const RecipeImportScreen({super.key});

  @override
  State<RecipeImportScreen> createState() => _RecipeImportScreenState();
}

class _RecipeImportScreenState extends State<RecipeImportScreen> {
  bool _uploading = false;
  bool _confirming = false;
  RecipeImportPreview? _preview;
  String? _error;
  String? _success;
  bool _allowCreateMissing = false;
  final _actions = <String, String>{};
  String? _bulkAction;

  Future<void> _pickAndPreview() async {
    setState(() {
      _error = null;
      _success = null;
      _preview = null;
      _actions.clear();
      _bulkAction = null;
      _allowCreateMissing = false;
    });
    final file = await pickExcelFile();
    if (file == null) {
      if (!mounted) return;
      setState(() => _error = 'اختر ملف Excel (.xlsx / .xls / .csv)');
      return;
    }
    setState(() => _uploading = true);
    try {
      final preview = await RecipesApi.instance.previewImport(
        filename: file.name,
        bytes: file.bytes,
      );
      if (!mounted) return;
      setState(() {
        _preview = preview;
        _uploading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _uploading = false;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _uploading = false;
        _error = 'تعذّر رفع الملف.';
      });
    }
  }

  bool get _canConfirm {
    final p = _preview;
    if (p == null) return false;
    if (p.missingItems.isNotEmpty && !_allowCreateMissing) return false;
    for (final r in p.recipes) {
      if (r.exists && !_actions.containsKey(r.normalizedName)) return false;
    }
    return true;
  }

  void _setAll(String action) {
    final p = _preview;
    if (p == null) return;
    setState(() {
      _bulkAction = action;
      for (final r in p.recipes) {
        if (r.exists) _actions[r.normalizedName] = action;
      }
    });
  }

  Future<void> _confirm() async {
    final p = _preview;
    if (p == null || !_canConfirm) return;
    setState(() {
      _confirming = true;
      _error = null;
    });
    try {
      final result = await RecipesApi.instance.confirmImport(
        importToken: p.importToken,
        createMissingItems: _allowCreateMissing,
        recipeActions: _actions,
      );
      if (!mounted) return;
      final parts = <String>[
        if (result.recipesCreated > 0) 'تم إنشاء ${result.recipesCreated} وصفة',
        if (result.recipesUpdated > 0) 'تم تحديث ${result.recipesUpdated} وصفة',
        if (result.recipesSkipped > 0) 'تم تخطّي ${result.recipesSkipped} وصفة',
        if (result.itemsCreated > 0) 'أُنشئ ${result.itemsCreated} صنف',
      ];
      setState(() {
        _confirming = false;
        _success = parts.isEmpty ? result.message : parts.join(' · ');
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _confirming = false;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _confirming = false;
        _error = 'تعذر حفظ الوصفات';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('استيراد وصفات من Excel'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        body: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
          children: [
            if (_uploading || _confirming)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 24),
                child: Column(
                  children: [
                    CircularProgressIndicator(),
                    SizedBox(height: 12),
                    Text('جاري المعالجة…'),
                  ],
                ),
              ),
            if (_error != null)
              Container(
                padding: const EdgeInsets.all(12),
                margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(
                  color: AppColors.danger.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  _error!,
                  style: const TextStyle(
                    color: AppColors.danger,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            if (_success != null) ...[
              Container(
                padding: const EdgeInsets.all(12),
                margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(
                  color: AppColors.success.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  _success!,
                  style: const TextStyle(
                    color: AppColors.success,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
                child: const Text('عرض الوصفات'),
              ),
            ],
            if (!_uploading && !_confirming && _success == null) ...[
              const Text(
                'ارفع ملف .xlsx بنفس تنسيق ملف الأصناف المعتاد: أعمدة اسم الصنف والخامات والكمية (بالإضافة إلى الوحدة والسعر اختياريًا).',
                style: TextStyle(color: AppColors.textSecondary),
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: _pickAndPreview,
                style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
                icon: const Icon(Icons.upload_file),
                label: const Text('اختيار ملف Excel'),
              ),
            ],
            if (_preview != null && !_uploading && !_confirming && _success == null)
              ..._previewBody(_preview!),
          ],
        ),
      ),
    );
  }

  List<Widget> _previewBody(RecipeImportPreview p) {
    return [
      const SizedBox(height: 16),
      Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          _chip('${p.recipesTotal} وصفة'),
          if ((p.sheetsParsed ?? 0) > 1) _chip('${p.sheetsParsed} تبويب'),
          _chip('${p.missingItemsTotal} صنف ناقص'),
          _chip('${p.existingRecipesTotal} وصفة مكررة'),
        ],
      ),
      if (p.message.isNotEmpty) ...[
        const SizedBox(height: 8),
        Text(p.message),
      ],
      if (p.missingItems.isNotEmpty) ...[
        const SizedBox(height: 16),
        const Text(
          'الأصناف التالية غير موجودة وستُنشأ كأصناف جديدة:',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        for (final name in p.missingItems) Text('• $name'),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          value: _allowCreateMissing,
          onChanged: (v) => setState(() => _allowCreateMissing = v ?? false),
          title: const Text(
            'إنشاء الأصناف واستكمال الاستيراد',
          ),
        ),
      ],
      if (p.existingRecipes.isNotEmpty) ...[
        const SizedBox(height: 12),
        const Text(
          'الوصفة موجودة بالفعل — اختر الإجراء:',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          children: [
            _actionChip('استبدال الجميع', _bulkAction == 'replace', () => _setAll('replace')),
            _actionChip('نسخة جديدة للجميع', _bulkAction == 'create_new', () => _setAll('create_new')),
            _actionChip('تخطي الجميع', _bulkAction == 'skip', () => _setAll('skip')),
          ],
        ),
        const SizedBox(height: 8),
        for (final r in p.recipes.where((e) => e.exists))
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${r.recipeName} · ${r.ingredientsCount} مكوِّن',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                Wrap(
                  spacing: 6,
                  children: [
                    _actionChip('استبدال', _actions[r.normalizedName] == 'replace', () {
                      setState(() {
                        _actions[r.normalizedName] = 'replace';
                        _bulkAction = null;
                      });
                    }),
                    _actionChip('نسخة جديدة', _actions[r.normalizedName] == 'create_new', () {
                      setState(() {
                        _actions[r.normalizedName] = 'create_new';
                        _bulkAction = null;
                      });
                    }),
                    _actionChip('تخطي', _actions[r.normalizedName] == 'skip', () {
                      setState(() {
                        _actions[r.normalizedName] = 'skip';
                        _bulkAction = null;
                      });
                    }),
                  ],
                ),
              ],
            ),
          ),
      ],
      const SizedBox(height: 12),
      ExpansionTile(
        title: Text('معاينة الوصفات المستوردة (${p.recipes.length})'),
        children: [
          for (final r in p.recipes)
            ListTile(
              title: Text(r.recipeName),
              subtitle: Text(
                [
                  r.exists ? 'موجودة' : 'جديدة',
                  '${r.ingredientsCount} مكوِّن',
                ].join(' · '),
              ),
            ),
        ],
      ),
      const SizedBox(height: 16),
      FilledButton(
        onPressed: _canConfirm ? _confirm : null,
        style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
        child: const Text('تأكيد الاستيراد'),
      ),
    ];
  }

  Widget _chip(String label) {
    return Chip(
      label: Text(label),
      backgroundColor: AppColors.primaryBg,
    );
  }

  Widget _actionChip(
    String label,
    bool active,
    VoidCallback onTap,
  ) {
    return ChoiceChip(
      label: Text(label),
      selected: active,
      onSelected: (_) => onTap(),
    );
  }
}
