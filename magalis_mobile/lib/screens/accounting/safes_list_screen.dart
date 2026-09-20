import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../services/treasury_api.dart';
import '../../theme/app_colors.dart';
import '../../widgets/account_picker_field.dart';

/// إدارة الخزن — mirrors Angular `safes`.
class SafesListScreen extends StatefulWidget {
  const SafesListScreen({super.key});

  @override
  State<SafesListScreen> createState() => _SafesListScreenState();
}

class _SafesListScreenState extends State<SafesListScreen> {
  static final _money = NumberFormat('#,##0.00', 'ar');
  final _searchCtrl = TextEditingController();
  List<SafeItem> _all = [];
  List<BankItem> _banks = [];
  List<AccountOption> _accounts = [];
  bool _loading = true;
  bool _busy = false;
  String? _error;

  List<SafeItem> get _filtered =>
      TreasuryApi.filterSafes(_all, _searchCtrl.text);

  double get _total => _all.fold(0, (s, e) => s + e.balance);
  int get _mainCount => _all.where((s) => s.isMain).length;
  int get _branchCount => _all.length - _mainCount;

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
      final acc = await TreasuryApi.instance.fetchAccountOptions();
      final banks = await TreasuryApi.instance.fetchBanks();
      if (mounted) {
        setState(() {
          _accounts = acc;
          _banks = banks;
        });
      }
    } catch (_) {/* optional */}
    await _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await TreasuryApi.instance.fetchSafes();
      if (!mounted) return;
      setState(() {
        _all = items;
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
        _error = 'تعذر تحميل الخزن';
        _loading = false;
      });
    }
  }

  void _snack(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  Future<void> _run(Future<void> Function() fn, String ok) async {
    setState(() => _busy = true);
    try {
      await fn();
      _snack(ok);
      await _load();
    } on ApiException catch (e) {
      _snack(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _confirmDelete(SafeItem s) async {
    if (s.balance != 0) {
      _snack('لا يمكن حذف خزنة تحتوي على رصيد');
      return;
    }
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('حذف الخزنة'),
        content: Text('هل أنت متأكد من حذف «${s.name}»؟'),
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
    await _run(() => TreasuryApi.instance.deleteSafe(s.id), 'تم حذف الخزنة بنجاح');
  }

  Future<void> _openAdd() async {
    final nameCtrl = TextEditingController();
    final branchCtrl = TextEditingController();
    final balCtrl = TextEditingController(text: '0');
    var type = 'main';
    var inside = false;
    int? parentId;
    int? counterId;

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(ctx).viewInsets.bottom + 16,
        ),
        child: StatefulBuilder(
          builder: (ctx, setModal) {
            final bal = double.tryParse(balCtrl.text) ?? 0;
            return SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    'إضافة خزنة جديدة',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      color: AppColors.navy,
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: nameCtrl,
                    decoration: const InputDecoration(
                      labelText: 'اسم الخزنة *',
                    ),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    isExpanded: true,
                    value: type,
                    decoration: const InputDecoration(labelText: 'النوع *'),
                    items: const [
                      DropdownMenuItem(value: 'main', child: Text('رئيسية')),
                      DropdownMenuItem(value: 'branch', child: Text('فرعية')),
                    ],
                    onChanged: (v) => setModal(() => type = v ?? 'main'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: balCtrl,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    onChanged: (_) => setModal(() {}),
                    decoration: const InputDecoration(
                      labelText: 'الرصيد الافتتاحي',
                    ),
                  ),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('داخل فرع'),
                    value: inside,
                    onChanged: (v) => setModal(() => inside = v),
                  ),
                  if (inside)
                    TextField(
                      controller: branchCtrl,
                      decoration: const InputDecoration(labelText: 'اسم الفرع'),
                    ),
                  const SizedBox(height: 10),
                  AccountPickerField(
                    label: 'الحساب الأب (شجرة الحسابات)',
                    requiredField: true,
                    options: _accounts,
                    selectedId: parentId,
                    onSelected: (id) => setModal(() => parentId = id),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'سيتم إنشاء حساب فرعي تلقائياً تحت هذا الحساب',
                    style: TextStyle(
                      color: AppColors.textMuted,
                      fontSize: 12,
                    ),
                  ),
                  if (bal > 0) ...[
                    const SizedBox(height: 10),
                    AccountPickerField(
                      label: 'الحساب المقابل للرصيد الافتتاحي',
                      requiredField: true,
                      options: _accounts,
                      selectedId: counterId,
                      onSelected: (id) => setModal(() => counterId = id),
                    ),
                  ],
                  const SizedBox(height: 14),
                  ElevatedButton(
                    onPressed: () {
                      if (nameCtrl.text.trim().isEmpty || parentId == null) {
                        return;
                      }
                      if (bal > 0 && counterId == null) return;
                      Navigator.pop(ctx, true);
                    },
                    child: const Text('حفظ'),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
    if (saved != true) return;
    final bal = double.tryParse(balCtrl.text) ?? 0;
    await _run(
      () => TreasuryApi.instance.createSafe({
        'name': nameCtrl.text.trim(),
        'type': type,
        'balance': bal,
        'is_inside_branch': inside,
        if (inside) 'branch_name': branchCtrl.text.trim(),
        'parent_account_id': parentId,
        if (bal > 0) 'counter_account_id': counterId,
      }),
      'تم إضافة الخزنة بنجاح',
    );
  }

  Future<void> _openEdit(SafeItem s) async {
    final nameCtrl = TextEditingController(text: s.name);
    final branchCtrl = TextEditingController(text: s.branchName ?? '');
    var inside = s.isInsideBranch;
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
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
                  const Text(
                    'تعديل بيانات الخزنة',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      color: AppColors.navy,
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: nameCtrl,
                    decoration: const InputDecoration(labelText: 'اسم الخزنة *'),
                  ),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('داخل فرع'),
                    value: inside,
                    onChanged: (v) => setModal(() => inside = v),
                  ),
                  if (inside)
                    TextField(
                      controller: branchCtrl,
                      decoration: const InputDecoration(labelText: 'اسم الفرع'),
                    ),
                  if (s.accountName != null) ...[
                    const SizedBox(height: 8),
                    Text(
                      'الحساب المرتبط: ${s.accountName}${s.accountCode != null ? ' — ${s.accountCode}' : ''}',
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                  const SizedBox(height: 14),
                  ElevatedButton(
                    onPressed: () {
                      if (nameCtrl.text.trim().isEmpty) return;
                      Navigator.pop(ctx, true);
                    },
                    child: const Text('تحديث'),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
    if (saved != true) return;
    await _run(
      () => TreasuryApi.instance.updateSafe(s.id, {
        'name': nameCtrl.text.trim(),
        'is_inside_branch': inside,
        'branch_name': inside ? branchCtrl.text.trim() : null,
      }),
      'تم تحديث بيانات الخزنة بنجاح',
    );
  }

  Future<void> _openTransfer({SafeItem? from}) async {
    var type = 'safe_to_safe';
    int? fromId = from?.id;
    int? toId;
    final amountCtrl = TextEditingController();
    final notesCtrl = TextEditingController();
    var date = DateTime.now().toIso8601String().substring(0, 10);

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(ctx).viewInsets.bottom + 16,
        ),
        child: StatefulBuilder(
          builder: (ctx, setModal) {
            SafeItem? fromSafe;
            for (final s in _all) {
              if (s.id == fromId) fromSafe = s;
            }
            return SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    'تحويل مالي',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      color: AppColors.navy,
                    ),
                  ),
                  const SizedBox(height: 12),
                  SegmentedButton<String>(
                    segments: const [
                      ButtonSegment(
                        value: 'safe_to_safe',
                        label: Text('خزينة ← خزينة'),
                      ),
                      ButtonSegment(
                        value: 'safe_to_bank',
                        label: Text('خزينة ← بنك'),
                      ),
                    ],
                    selected: {type},
                    onSelectionChanged: (s) => setModal(() {
                      type = s.first;
                      toId = null;
                    }),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    value: _all.any((s) => s.id == fromId) ? fromId : null,
                    decoration: const InputDecoration(labelText: 'من خزينة *'),
                    items: _all
                        .map(
                          (s) => DropdownMenuItem(
                            value: s.id,
                            child: Text('${s.name} (${_money.format(s.balance)})'),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setModal(() => fromId = v),
                  ),
                  const SizedBox(height: 10),
                  if (type == 'safe_to_safe')
                    DropdownButtonFormField<int>(
                      isExpanded: true,
                      value: _all.any((s) => s.id == toId && s.id != fromId)
                          ? toId
                          : null,
                      decoration: const InputDecoration(labelText: 'إلى خزينة *'),
                      items: _all
                          .where((s) => s.id != fromId)
                          .map(
                            (s) => DropdownMenuItem(
                              value: s.id,
                              child: Text(s.name),
                            ),
                          )
                          .toList(),
                      onChanged: (v) => setModal(() => toId = v),
                    )
                  else
                    DropdownButtonFormField<int>(
                      isExpanded: true,
                      value: _banks.any((b) => b.id == toId) ? toId : null,
                      decoration: const InputDecoration(labelText: 'إلى بنك *'),
                      items: _banks
                          .map(
                            (b) => DropdownMenuItem(
                              value: b.id,
                              child: Text(b.name),
                            ),
                          )
                          .toList(),
                      onChanged: (v) => setModal(() => toId = v),
                    ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: amountCtrl,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: InputDecoration(
                      labelText: 'المبلغ *',
                      helperText: fromSafe == null
                          ? null
                          : 'المتاح: ${_money.format(fromSafe.balance)}',
                    ),
                  ),
                  const SizedBox(height: 10),
                  OutlinedButton(
                    onPressed: () async {
                      final d = await showDatePicker(
                        context: ctx,
                        initialDate: DateTime.tryParse(date) ?? DateTime.now(),
                        firstDate: DateTime(2000),
                        lastDate: DateTime(2100),
                      );
                      if (d == null) return;
                      setModal(() {
                        date =
                            '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
                      });
                    },
                    child: Text(date),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: notesCtrl,
                    maxLines: 2,
                    decoration: const InputDecoration(labelText: 'ملاحظات'),
                  ),
                  const SizedBox(height: 14),
                  ElevatedButton(
                    onPressed: () {
                      final amt = double.tryParse(amountCtrl.text) ?? 0;
                      if (fromId == null || toId == null || amt <= 0) return;
                      Navigator.pop(ctx, true);
                    },
                    child: const Text('تحويل'),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
    if (saved != true) return;
    final amt = double.tryParse(amountCtrl.text) ?? 0;
    if (fromId == null || toId == null || amt <= 0) return;
    SafeItem? fromSafe;
    for (final s in _all) {
      if (s.id == fromId) fromSafe = s;
    }
    if (fromSafe != null && amt > fromSafe.balance) {
      _snack('المبلغ المطلوب أكبر من رصيد الخزنة المحولة منها');
      return;
    }
    if (type == 'safe_to_safe') {
      await _run(
        () => TreasuryApi.instance.safeTransfer(
          fromSafeId: fromId!,
          toSafeId: toId!,
          amount: amt,
          notes: notesCtrl.text,
        ),
        'تم التحويل بنجاح',
      );
    } else {
      await _run(
        () => TreasuryApi.instance.bankTransfer({
          'type': 'transfer_safe_to_bank',
          'from_id': fromId,
          'to_id': toId,
          'amount': amt,
          'date': date,
          'notes': notesCtrl.text,
        }),
        'تم التحويل للبنك بنجاح',
      );
    }
  }

  void _actions(SafeItem s) {
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: Text(s.name, style: const TextStyle(fontWeight: FontWeight.w800)),
              subtitle: Text(_money.format(s.balance)),
            ),
            const Divider(height: 1),
            ListTile(
              leading: const Icon(Icons.edit_outlined),
              title: const Text('تعديل'),
              onTap: () {
                Navigator.pop(ctx);
                _openEdit(s);
              },
            ),
            ListTile(
              leading: const Icon(Icons.swap_horiz),
              title: const Text('تحويل'),
              onTap: () {
                Navigator.pop(ctx);
                _openTransfer(from: s);
              },
            ),
            ListTile(
              leading: const Icon(Icons.delete_outline, color: AppColors.danger),
              title: const Text('حذف', style: TextStyle(color: AppColors.danger)),
              onTap: () {
                Navigator.pop(ctx);
                _confirmDelete(s);
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
        title: const Text('إدارة الخزن'),
        actions: [
          IconButton(
            onPressed: _busy ? null : () => _openTransfer(),
            icon: const Icon(Icons.swap_horiz),
            tooltip: 'تحويل مالي',
          ),
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _busy ? null : _openAdd,
        icon: const Icon(Icons.add),
        label: const Text('إضافة خزنة'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 6),
            child: Row(
              children: [
                _stat('إجمالي الرصيد', _money.format(_total), AppColors.primary),
                const SizedBox(width: 8),
                _stat('رئيسية', '$_mainCount', AppColors.navy),
                const SizedBox(width: 8),
                _stat('فرعية', '$_branchCount', AppColors.info),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: TextField(
              controller: _searchCtrl,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                hintText: 'بحث بالاسم أو الفرع...',
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
          Expanded(child: _body()),
        ],
      ),
    );
  }

  Widget _stat(String label, String value, Color color) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontWeight: FontWeight.w800,
                color: color,
                fontSize: 14,
              ),
            ),
            Text(
              label,
              style: const TextStyle(
                color: AppColors.textMuted,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
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
            const SizedBox(height: 8),
            ElevatedButton(onPressed: _load, child: const Text('إعادة المحاولة')),
          ],
        ),
      );
    }
    final items = _filtered;
    if (items.isEmpty) {
      return Center(
        child: Text(
          _all.isEmpty ? 'لا يوجد خزن مضافة بعد' : 'لا توجد خزن تطابق بحثك',
          style: const TextStyle(
            color: AppColors.textMuted,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(12, 4, 12, 88),
        itemCount: items.length,
        itemBuilder: (context, i) {
          final s = items[i];
          return InkWell(
            onTap: () => _actions(s),
            child: Container(
              margin: const EdgeInsets.symmetric(vertical: 3),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: AppColors.border),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          s.name,
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            color: AppColors.navy,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Wrap(
                          spacing: 8,
                          children: [
                            Text(
                              s.isMain ? 'رئيسية' : 'فرعية',
                              style: const TextStyle(
                                color: AppColors.textMuted,
                                fontWeight: FontWeight.w700,
                                fontSize: 12,
                              ),
                            ),
                            if (s.isInsideBranch && s.branchName != null)
                              Text(
                                s.branchName!,
                                style: const TextStyle(
                                  color: AppColors.textMuted,
                                  fontSize: 12,
                                ),
                              ),
                            if (s.accountName != null)
                              Text(
                                s.accountName!,
                                style: const TextStyle(
                                  color: AppColors.info,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  Text(
                    _money.format(s.balance),
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      color: s.balance < 0 ? AppColors.danger : AppColors.success,
                    ),
                  ),
                  IconButton(
                    onPressed: () => _actions(s),
                    icon: const Icon(Icons.more_vert),
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
