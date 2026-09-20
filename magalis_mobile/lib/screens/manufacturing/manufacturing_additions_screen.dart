import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api_client.dart';
import '../../services/manufacturing_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';

/// الإضافات الخاصة للتصنيع — mirrors Angular `ManufacturingAdditionsComponent`.
class ManufacturingAdditionsScreen extends StatefulWidget {
  const ManufacturingAdditionsScreen({super.key});

  @override
  State<ManufacturingAdditionsScreen> createState() =>
      _ManufacturingAdditionsScreenState();
}

class _ManufacturingAdditionsScreenState
    extends State<ManufacturingAdditionsScreen> {
  final _searchCtrl = TextEditingController();
  List<ManufactureAdditionItem> _items = [];
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

  List<ManufactureAdditionItem> get _visible {
    final q = _searchCtrl.text.trim().toLowerCase();
    if (q.isEmpty) return _items;
    return _items.where((e) {
      return e.name.toLowerCase().contains(q) ||
          (e.unit ?? '').toLowerCase().contains(q);
    }).toList();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final rows = await ManufacturingApi.instance.listAdditions();
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
        _error = 'تعذر تحميل الإضافات';
        _loading = false;
      });
    }
  }

  Future<void> _openForm({ManufactureAdditionItem? item}) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => _AdditionFormDialog(item: item),
    );
    if (saved == true && mounted) _load();
  }

  Future<void> _delete(ManufactureAdditionItem item) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('حذف الإضافة؟'),
        content: Text('سيتم حذف «${item.name}».'),
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
      await ManufacturingApi.instance.deleteAddition(item.id);
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

  @override
  Widget build(BuildContext context) {
    final items = _visible;
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('الإضافات الخاصة للتصنيع'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
          actions: [
            IconButton(
              tooltip: 'إضافة',
              onPressed: () => _openForm(),
              icon: const Icon(Icons.add_circle_outline),
            ),
          ],
        ),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: () => _openForm(),
          backgroundColor: AppColors.primary,
          icon: const Icon(Icons.add, color: Colors.white),
          label: const Text('إضافة'),
        ),
        body: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
              child: TextField(
                controller: _searchCtrl,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  hintText: 'بحث في اسم الإضافة أو الوحدة…',
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
            Expanded(child: _body(items)),
          ],
        ),
      ),
    );
  }

  Widget _body(List<ManufactureAdditionItem> items) {
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
          'لا توجد إضافات مسجّلة.',
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
        itemBuilder: (context, i) {
          final item = items[i];
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
                  item.name,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.navy,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  [
                    'التكلفة ${Formatters.moneyPlain(item.cost)}',
                    if (item.unit != null) item.unit!,
                  ].join(' · '),
                  style: const TextStyle(color: AppColors.textSecondary),
                ),
                Row(
                  children: [
                    TextButton(
                      onPressed: () => _openForm(item: item),
                      child: const Text('تعديل'),
                    ),
                    TextButton(
                      onPressed: () => _delete(item),
                      style: TextButton.styleFrom(foregroundColor: AppColors.danger),
                      child: const Text('حذف'),
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

class _AdditionFormDialog extends StatefulWidget {
  const _AdditionFormDialog({this.item});

  final ManufactureAdditionItem? item;

  @override
  State<_AdditionFormDialog> createState() => _AdditionFormDialogState();
}

class _AdditionFormDialogState extends State<_AdditionFormDialog> {
  late final TextEditingController _nameCtrl;
  late final TextEditingController _costCtrl;
  late final TextEditingController _unitCtrl;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final item = widget.item;
    _nameCtrl = TextEditingController(text: item?.name ?? '');
    _costCtrl = TextEditingController(
      text: item == null ? '' : _numText(item.cost),
    );
    _unitCtrl = TextEditingController(text: item?.unit ?? '');
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _costCtrl.dispose();
    _unitCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final name = _nameCtrl.text.trim();
    final cost = double.tryParse(_costCtrl.text.trim());
    if (name.isEmpty || cost == null || cost < 0) {
      setState(() => _error = 'أدخل اسم الإضافة والتكلفة');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      if (widget.item == null) {
        await ManufacturingApi.instance.createAddition(
          name: name,
          cost: cost,
          unit: _unitCtrl.text,
        );
      } else {
        await ManufacturingApi.instance.updateAddition(
          id: widget.item!.id,
          name: name,
          cost: cost,
          unit: _unitCtrl.text,
        );
      }
      if (!mounted) return;
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = 'تعذر حفظ الإضافة';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(widget.item == null ? 'إضافة جديدة' : 'تعديل إضافة'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(
                _error!,
                style: const TextStyle(
                  color: AppColors.danger,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          TextField(
            controller: _nameCtrl,
            autofocus: true,
            decoration: const InputDecoration(hintText: 'اسم الاضافة'),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _costCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
            ],
            decoration: const InputDecoration(hintText: 'التكلفة'),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _unitCtrl,
            decoration: const InputDecoration(hintText: 'الوحدة (اختياري)'),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.pop(context),
          child: const Text('إلغاء'),
        ),
        FilledButton(
          onPressed: _saving ? null : _save,
          child: Text(_saving ? 'جاري الحفظ…' : 'حفظ'),
        ),
      ],
    );
  }
}

String _numText(double v) {
  if (v == v.roundToDouble()) return v.toStringAsFixed(0);
  return v.toString();
}
