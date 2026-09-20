import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/accounting_tree_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';

/// Chart of accounts — mirrors Angular `accounting-tree`.
class AccountingTreeScreen extends StatefulWidget {
  const AccountingTreeScreen({super.key});

  @override
  State<AccountingTreeScreen> createState() => _AccountingTreeScreenState();
}

class _AccountingTreeScreenState extends State<AccountingTreeScreen> {
  final _searchCtrl = TextEditingController();
  List<TreeAccountNode> _roots = [];
  List<TreeAccountNode> _display = [];
  final Set<int> _expanded = {};
  bool _loading = true;
  bool _busy = false;
  String? _error;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final tree = await AccountingTreeApi.instance.fetchTree();
      if (!mounted) return;
      setState(() {
        _roots = tree;
        _applyFilter();
        // Expand root accounts by default for usability.
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
        _error = 'تعذر تحميل شجرة الحسابات';
        _loading = false;
      });
    }
  }

  void _applyFilter() {
    _display = AccountingTreeApi.filterTree(_roots, _searchCtrl.text);
    if (_searchCtrl.text.trim().isNotEmpty) {
      _expandAll(_display);
    }
  }

  void _expandAll(List<TreeAccountNode> nodes) {
    for (final n in nodes) {
      _expanded.add(n.id);
      if (n.hasChildren) _expandAll(n.children);
    }
  }

  void _onSearch(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () {
      if (!mounted) return;
      setState(_applyFilter);
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

  Future<void> _confirmDelete(TreeAccountNode node) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('نقل إلى سلة المحذوفات؟'),
        content: Text(
          'سيتم نقل الحساب «${node.name}» وجميع فروعه إلى سلة المحذوفات.',
        ),
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
      await AccountingTreeApi.instance.softDelete(node.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم نقل الحساب إلى سلة المحذوفات')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openForm({TreeAccountNode? edit, TreeAccountNode? parent}) async {
    final nameCtrl = TextEditingController(text: edit?.name ?? '');
    final nameEnCtrl = TextEditingController(text: edit?.nameEn ?? '');
    var type = edit?.type ?? parent?.type ?? 'asset';
    var trading = edit?.isTradingAccount ?? false;
    final isEdit = edit != null;

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
              return SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      isEdit
                          ? 'تعديل الحساب'
                          : parent == null
                              ? 'إضافة حساب رئيسي'
                              : 'إضافة فرع تحت «${parent.name}»',
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
                        labelText: 'اسم الحساب *',
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      controller: nameEnCtrl,
                      decoration: const InputDecoration(
                        labelText: 'الاسم بالإنجليزية',
                      ),
                    ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      value: type,
                      decoration: const InputDecoration(labelText: 'النوع'),
                      items: kAccountTypeLabels.entries
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
                              setModal(() => type = v);
                            },
                    ),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('حساب متاجرة'),
                      value: trading,
                      onChanged: (v) => setModal(() => trading = v),
                    ),
                    const SizedBox(height: 8),
                    ElevatedButton(
                      onPressed: () {
                        if (nameCtrl.text.trim().isEmpty) return;
                        Navigator.pop(ctx, true);
                      },
                      child: Text(isEdit ? 'حفظ' : 'إضافة'),
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

    setState(() => _busy = true);
    try {
      if (isEdit) {
        await AccountingTreeApi.instance.update(
          id: edit.id,
          name: name,
          type: type,
          nameEn: nameEnCtrl.text,
          isTradingAccount: trading,
        );
      } else {
        await AccountingTreeApi.instance.create(
          name: name,
          type: type,
          nameEn: nameEnCtrl.text,
          parentId: parent?.id,
          isTradingAccount: trading,
        );
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(isEdit ? 'تم حفظ الحساب' : 'تم إضافة الحساب')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _showActions(TreeAccountNode node) {
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
                '${node.typeLabel} · ${Formatters.moneyPlain(node.displayBalance)}',
              ),
            ),
            const Divider(height: 1),
            ListTile(
              leading: const Icon(Icons.add_circle_outline),
              title: const Text('إضافة فرع'),
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        title: const Text('شجرة الحسابات'),
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
        label: const Text('حساب رئيسي'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
            child: TextField(
              controller: _searchCtrl,
              onChanged: _onSearch,
              decoration: InputDecoration(
                hintText: 'بحث بالاسم أو الكود...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchCtrl.text.isEmpty
                    ? null
                    : IconButton(
                        onPressed: () {
                          _searchCtrl.clear();
                          setState(_applyFilter);
                        },
                        icon: const Icon(Icons.clear),
                      ),
              ),
            ),
          ),
          Expanded(child: _buildBody()),
        ],
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
    if (_display.isEmpty) {
      return const Center(
        child: Text(
          'لا توجد حسابات',
          style: TextStyle(
            color: AppColors.textMuted,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }

    final rows = <_FlatRow>[];
    void walk(List<TreeAccountNode> nodes, int depth) {
      for (final n in nodes) {
        final open = _expanded.contains(n.id);
        rows.add(_FlatRow(node: n, depth: depth, expanded: open));
        if (open && n.hasChildren) walk(n.children, depth + 1);
      }
    }

    walk(_display, 0);

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(8, 4, 8, 88),
        itemCount: rows.length,
        itemBuilder: (context, i) {
          final row = rows[i];
          final node = row.node;
          return InkWell(
            onTap: node.hasChildren ? () => _toggle(node.id) : null,
            onLongPress: () => _showActions(node),
            child: Container(
              margin: const EdgeInsets.symmetric(vertical: 2),
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
                    child: node.hasChildren
                        ? Icon(
                            row.expanded
                                ? Icons.expand_more
                                : Icons.chevron_left,
                            color: AppColors.primary,
                            size: 22,
                          )
                        : const SizedBox.shrink(),
                  ),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          node.code.isEmpty
                              ? node.name
                              : '${node.code}  ${node.name}',
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            color: AppColors.navy,
                            fontSize: row.depth == 0 ? 14 : 13,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          node.typeLabel,
                          style: const TextStyle(
                            color: AppColors.textMuted,
                            fontWeight: FontWeight.w600,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    Formatters.moneyPlain(node.displayBalance),
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
        },
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

  final TreeAccountNode node;
  final int depth;
  final bool expanded;
}
