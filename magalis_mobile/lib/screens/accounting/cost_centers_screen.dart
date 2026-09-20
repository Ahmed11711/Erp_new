import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../services/cost_centers_api.dart';
import '../../theme/app_colors.dart';

/// مراكز التكلفة — mirrors Angular `cost-centers`.
class CostCentersScreen extends StatefulWidget {
  const CostCentersScreen({super.key});

  @override
  State<CostCentersScreen> createState() => _CostCentersScreenState();
}

class _CostCentersScreenState extends State<CostCentersScreen> {
  static final _money = NumberFormat('#,##0.00', 'ar');

  final _searchCtrl = TextEditingController();
  List<CostCenterNode> _roots = [];
  List<CostCenterNode> _flat = [];
  List<CostCenterEmployee> _employees = [];
  final Set<int> _expanded = {};
  bool _treeMode = true;
  bool _loading = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    try {
      final emps = await CostCentersApi.instance.fetchEmployees();
      if (mounted) setState(() => _employees = emps);
    } catch (_) {/* optional */}
    await _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final tree = await CostCentersApi.instance.fetchTree();
      if (!mounted) return;
      setState(() {
        _roots = tree;
        _flat = CostCentersApi.flatten(tree);
        for (final r in _roots) {
          _expanded.add(r.id);
        }
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
        _error = 'تعذر تحميل مراكز التكلفة';
        _loading = false;
      });
    }
  }

  List<CostCenterNode> get _displayTree {
    return CostCentersApi.filterTree(_roots, _searchCtrl.text);
  }

  List<CostCenterNode> get _displayList {
    return CostCentersApi.filterFlat(items: _flat, query: _searchCtrl.text);
  }

  void _expandAll(List<CostCenterNode> nodes) {
    for (final n in nodes) {
      _expanded.add(n.id);
      if (n.hasChildren) _expandAll(n.children);
    }
  }

  void _onSearch(String _) {
    setState(() {
      if (_searchCtrl.text.trim().isNotEmpty) {
        _expandAll(CostCentersApi.filterTree(_roots, _searchCtrl.text));
      }
    });
  }

  void _toggle(int id) {
    setState(() {
      if (_expanded.contains(id)) {
        _expanded.remove(id);
      } else {
        _expanded.add(id);
      }
    });
  }

  String _employeeName(CostCenterNode node) {
    if (node.responsibleName != null && node.responsibleName!.isNotEmpty) {
      return node.responsibleName!;
    }
    final id = node.responsiblePersonId;
    if (id == null) return '';
    for (final e in _employees) {
      if (e.id == id) return e.name;
    }
    return '';
  }

  Future<void> _snack(String msg) async {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  Future<void> _confirmDelete(CostCenterNode node) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('حذف مركز التكلفة؟'),
        content: Text('هل أنت متأكد من حذف «${node.name}»؟'),
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
    setState(() => _busy = true);
    try {
      await CostCentersApi.instance.delete(node.id);
      await _snack('تم حذف مركز التكلفة');
      await _load();
    } on ApiException catch (e) {
      await _snack(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _showActions(CostCenterNode node) {
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: Text(
                '${node.code} · ${node.name}',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(
                '${node.typeLabel} · ${_money.format(node.value)}',
              ),
            ),
            const Divider(height: 1),
            ListTile(
              leading: const Icon(Icons.add_circle_outline),
              title: const Text('إضافة مركز فرعي'),
              onTap: () {
                Navigator.pop(ctx);
                _openForm(parent: node);
              },
            ),
            ListTile(
              leading: const Icon(Icons.edit_outlined),
              title: const Text('تعديل'),
              onTap: () {
                Navigator.pop(ctx);
                _openForm(edit: node);
              },
            ),
            ListTile(
              leading: const Icon(Icons.delete_outline, color: AppColors.danger),
              title: const Text(
                'حذف',
                style: TextStyle(color: AppColors.danger),
              ),
              onTap: () {
                Navigator.pop(ctx);
                _confirmDelete(node);
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openForm({CostCenterNode? edit, CostCenterNode? parent}) async {
    final isEdit = edit != null;
    final nameCtrl = TextEditingController(text: edit?.name ?? '');
    final nameEnCtrl = TextEditingController(text: edit?.nameEn ?? '');
    final locationCtrl = TextEditingController(text: edit?.location ?? '');
    final phoneCtrl = TextEditingController(text: edit?.phone ?? '');
    final emailCtrl = TextEditingController(text: edit?.email ?? '');
    final durationCtrl = TextEditingController(text: edit?.duration ?? '');
    final valueCtrl = TextEditingController(
      text: edit != null ? edit.value.toString() : '0',
    );
    var type = edit?.type ?? (parent != null ? 'sub' : 'main');
    var parentId = edit?.parentId ?? parent?.id;
    var responsibleId = edit?.responsiblePersonId;
    var startDate = edit?.startDate;
    var endDate = edit?.endDate;

    final mains = _flat.where((c) => c.isMain && c.id != edit?.id).toList();

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        return Padding(
          padding: EdgeInsets.only(
            left: 16,
            right: 16,
            top: 16,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 16,
          ),
          child: StatefulBuilder(
            builder: (ctx, setModal) {
              Future<void> pickDate({required bool start}) async {
                final current = DateTime.tryParse(start ? (startDate ?? '') : (endDate ?? ''));
                final picked = await showDatePicker(
                  context: ctx,
                  initialDate: current ?? DateTime.now(),
                  firstDate: DateTime(2000),
                  lastDate: DateTime(2100),
                );
                if (picked == null) return;
                final ymd =
                    '${picked.year.toString().padLeft(4, '0')}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
                setModal(() {
                  if (start) {
                    startDate = ymd;
                  } else {
                    endDate = ymd;
                  }
                });
              }

              return SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      isEdit
                          ? 'تعديل مركز التكلفة'
                          : parent == null
                              ? 'إضافة مركز تكلفة رئيسي'
                              : 'إضافة مركز فرعي تحت «${parent.name}»',
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                        color: AppColors.navy,
                      ),
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: nameCtrl,
                      decoration: const InputDecoration(
                        labelText: 'اسم مركز التكلفة *',
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: nameEnCtrl,
                      decoration: const InputDecoration(
                        labelText: 'الاسم بالإنجليزية',
                      ),
                    ),
                    if (!isEdit) ...[
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String>(
                        isExpanded: true,
                        value: type,
                        decoration: const InputDecoration(labelText: 'النوع *'),
                        items: kCostCenterTypeLabels.entries
                            .map(
                              (e) => DropdownMenuItem(
                                value: e.key,
                                child: Text(e.value),
                              ),
                            )
                            .toList(),
                        onChanged: parent != null
                            ? null
                            : (v) {
                                if (v == null) return;
                                setModal(() {
                                  type = v;
                                  if (type == 'main') parentId = null;
                                });
                              },
                      ),
                      if (type == 'sub') ...[
                        const SizedBox(height: 10),
                        DropdownButtonFormField<int>(
                          isExpanded: true,
                          value: mains.any((m) => m.id == parentId)
                              ? parentId
                              : null,
                          decoration: const InputDecoration(
                            labelText: 'المركز الرئيسي *',
                          ),
                          items: mains
                              .map(
                                (m) => DropdownMenuItem(
                                  value: m.id,
                                  child: Text('${m.code}  ${m.name}'),
                                ),
                              )
                              .toList(),
                          onChanged: parent != null
                              ? null
                              : (v) => setModal(() => parentId = v),
                        ),
                      ],
                    ],
                    const SizedBox(height: 10),
                    if (_employees.isNotEmpty)
                      DropdownButtonFormField<int?>(
                        isExpanded: true,
                        value: _employees.any((e) => e.id == responsibleId)
                            ? responsibleId
                            : null,
                        decoration: const InputDecoration(labelText: 'المسؤول'),
                        items: [
                          const DropdownMenuItem<int?>(
                            value: null,
                            child: Text('بدون مسؤول'),
                          ),
                          ..._employees.map(
                            (e) => DropdownMenuItem<int?>(
                              value: e.id,
                              child: Text(e.name),
                            ),
                          ),
                        ],
                        onChanged: (v) => setModal(() => responsibleId = v),
                      ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: locationCtrl,
                      decoration: const InputDecoration(labelText: 'الموقع'),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: phoneCtrl,
                      keyboardType: TextInputType.phone,
                      decoration: const InputDecoration(labelText: 'الهاتف'),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: emailCtrl,
                      keyboardType: TextInputType.emailAddress,
                      decoration: const InputDecoration(
                        labelText: 'البريد الإلكتروني',
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => pickDate(start: true),
                            child: Text(
                              startDate == null || startDate!.isEmpty
                                  ? 'تاريخ البدء'
                                  : startDate!,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () => pickDate(start: false),
                            child: Text(
                              endDate == null || endDate!.isEmpty
                                  ? 'تاريخ الانتهاء'
                                  : endDate!,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: durationCtrl,
                      decoration: const InputDecoration(labelText: 'المدة'),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: valueCtrl,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: const InputDecoration(labelText: 'القيمة'),
                    ),
                    const SizedBox(height: 12),
                    ElevatedButton(
                      onPressed: () {
                        if (nameCtrl.text.trim().isEmpty) return;
                        if (!isEdit && type == 'sub' && parentId == null) {
                          return;
                        }
                        Navigator.pop(ctx, true);
                      },
                      child: Text(isEdit ? 'حفظ التغييرات' : 'حفظ'),
                    ),
                  ],
                ),
              );
            },
          ),
        );
      },
    );

    if (saved != true) return;
    final name = nameCtrl.text.trim();
    if (name.isEmpty) return;
    if (!isEdit && type == 'sub' && parentId == null) {
      await _snack('الرجاء اختيار المركز الرئيسي');
      return;
    }

    setState(() => _busy = true);
    try {
      if (isEdit) {
        await CostCentersApi.instance.update(
          id: edit.id,
          name: name,
          nameEn: nameEnCtrl.text,
          responsiblePersonId: responsibleId,
          location: locationCtrl.text,
          phone: phoneCtrl.text,
          email: emailCtrl.text,
          startDate: startDate,
          endDate: endDate,
          duration: durationCtrl.text,
          value: double.tryParse(valueCtrl.text.trim()) ?? 0,
        );
        await _snack('تم تحديث مركز التكلفة');
      } else {
        await CostCentersApi.instance.create(
          name: name,
          type: type,
          nameEn: nameEnCtrl.text,
          parentId: type == 'sub' ? parentId : null,
          responsiblePersonId: responsibleId,
          location: locationCtrl.text,
          phone: phoneCtrl.text,
          email: emailCtrl.text,
          startDate: startDate,
          endDate: endDate,
          duration: durationCtrl.text,
          value: double.tryParse(valueCtrl.text.trim()) ?? 0,
        );
        await _snack('تم إنشاء مركز التكلفة');
      }
      await _load();
    } on ApiException catch (e) {
      await _snack(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        title: const Text('مراكز التكلفة'),
        actions: [
          if (_busy)
            const Padding(
              padding: EdgeInsets.all(14),
              child: SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            )
          else
            IconButton(
              onPressed: _loading ? null : _load,
              icon: const Icon(Icons.refresh_rounded),
            ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _busy ? null : () => _openForm(),
        icon: const Icon(Icons.add),
        label: const Text('مركز رئيسي'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
            child: TextField(
              controller: _searchCtrl,
              onChanged: _onSearch,
              decoration: InputDecoration(
                hintText: 'بحث بالاسم أو الكود أو الموقع...',
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
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Row(
              children: [
                Expanded(
                  child: _modeChip(true, Icons.account_tree, 'عرض الشجرة'),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _modeChip(false, Icons.list, 'عرض القائمة'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 6),
          Expanded(child: _buildBody()),
        ],
      ),
    );
  }

  Widget _modeChip(bool tree, IconData icon, String label) {
    final selected = _treeMode == tree;
    return FilterChip(
      avatar: Icon(
        icon,
        size: 16,
        color: selected ? AppColors.primary : AppColors.textSecondary,
      ),
      label: Text(label),
      selected: selected,
      onSelected: (_) => setState(() => _treeMode = tree),
      selectedColor: AppColors.primaryBg,
      checkmarkColor: AppColors.primary,
      labelStyle: TextStyle(
        fontWeight: FontWeight.w700,
        color: selected ? AppColors.primary : AppColors.textSecondary,
        fontSize: 12,
      ),
      side: BorderSide(color: selected ? AppColors.primary : AppColors.border),
      backgroundColor: AppColors.surface,
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
      );
    }

    if (_treeMode) {
      return _buildTree();
    }
    return _buildList();
  }

  Widget _empty(String text) {
    return Center(
      child: Text(
        text,
        style: const TextStyle(
          color: AppColors.textMuted,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _buildTree() {
    final display = _displayTree;
    if (display.isEmpty) {
      return _empty(
        _searchCtrl.text.trim().isEmpty
            ? 'لا توجد مراكز تكلفة. ابدأ بإضافة مركز رئيسي.'
            : 'لا توجد نتائج',
      );
    }

    final rows = <_FlatRow>[];
    void walk(List<CostCenterNode> nodes, int depth) {
      for (final n in nodes) {
        final open = _expanded.contains(n.id);
        rows.add(_FlatRow(node: n, depth: depth, expanded: open));
        if (open && n.hasChildren) walk(n.children, depth + 1);
      }
    }

    walk(display, 0);

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(8, 4, 8, 88),
        itemCount: rows.length,
        itemBuilder: (context, i) => _nodeCard(rows[i]),
      ),
    );
  }

  Widget _buildList() {
    final items = _displayList;
    if (items.isEmpty) {
      return _empty(
        _searchCtrl.text.trim().isEmpty
            ? 'لا توجد مراكز تكلفة.'
            : 'لا توجد نتائج',
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(8, 4, 8, 88),
        itemCount: items.length,
        itemBuilder: (context, i) => _nodeCard(
          _FlatRow(node: items[i], depth: 0, expanded: false),
          showExpand: false,
        ),
      ),
    );
  }

  Widget _nodeCard(_FlatRow row, {bool showExpand = true}) {
    final node = row.node;
    final person = _employeeName(node);
    return InkWell(
      onTap: showExpand && node.hasChildren ? () => _toggle(node.id) : null,
      onLongPress: () => _showActions(node),
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 3),
        padding: EdgeInsetsDirectional.only(
          start: 8.0 + row.depth * 14.0,
          end: 4,
          top: 8,
          bottom: 8,
        ),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppColors.border),
        ),
        child: Row(
          children: [
            SizedBox(
              width: 28,
              child: showExpand && node.hasChildren
                  ? Icon(
                      row.expanded ? Icons.expand_more : Icons.chevron_left,
                      color: AppColors.primary,
                      size: 22,
                    )
                  : const SizedBox.shrink(),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: AppColors.bg,
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(
                node.code.isEmpty ? '—' : node.code,
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 12,
                  color: AppColors.navy,
                ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    node.name,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: AppColors.navy,
                      fontSize: 13,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Wrap(
                    spacing: 6,
                    runSpacing: 2,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      _typeChip(node),
                      if (person.isNotEmpty)
                        Text(
                          person,
                          style: const TextStyle(
                            color: AppColors.textMuted,
                            fontWeight: FontWeight.w600,
                            fontSize: 11,
                          ),
                        ),
                      if (node.location != null)
                        Text(
                          node.location!,
                          style: const TextStyle(
                            color: AppColors.textMuted,
                            fontWeight: FontWeight.w600,
                            fontSize: 11,
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            Text(
              _money.format(node.value),
              style: const TextStyle(
                fontWeight: FontWeight.w800,
                color: AppColors.primary,
                fontSize: 12,
              ),
            ),
            IconButton(
              visualDensity: VisualDensity.compact,
              onPressed: () => _showActions(node),
              icon: const Icon(Icons.more_vert, size: 20),
            ),
          ],
        ),
      ),
    );
  }

  Widget _typeChip(CostCenterNode node) {
    final color = node.isMain ? AppColors.navy : AppColors.info;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        node.typeLabel,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _FlatRow {
  const _FlatRow({
    required this.node,
    required this.depth,
    required this.expanded,
  });

  final CostCenterNode node;
  final int depth;
  final bool expanded;
}
