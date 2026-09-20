import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../services/treasury_api.dart';
import '../../theme/app_colors.dart';
import '../../widgets/account_picker_field.dart';

/// إدارة البنوك — mirrors Angular `banks`.
class BanksListScreen extends StatefulWidget {
  const BanksListScreen({super.key});

  @override
  State<BanksListScreen> createState() => _BanksListScreenState();
}

class _BanksListScreenState extends State<BanksListScreen> {
  static final _money = NumberFormat('#,##0.00', 'ar');
  final _searchCtrl = TextEditingController();
  List<BankItem> _all = [];
  List<AccountOption> _accounts = [];
  List<DirectoryUser> _users = [];
  bool _loading = true;
  bool _busy = false;
  String? _error;

  List<BankItem> get _filtered =>
      TreasuryApi.filterBanks(_all, _searchCtrl.text);
  double get _total => _all.fold(0, (s, e) => s + e.balance);
  bool get _canUsers => _users.isNotEmpty;

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
      final users = await TreasuryApi.instance.compactDirectory();
      if (mounted) {
        setState(() {
          _accounts = acc;
          _users = users;
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
      final items = await TreasuryApi.instance.fetchBanks();
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
        _error = 'تعذر تحميل البنوك';
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

  Future<void> _confirmDelete(BankItem b) async {
    if (b.balance != 0) {
      _snack('لا يمكن حذف بنك يحتوي على رصيد');
      return;
    }
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('حذف البنك'),
        content: Text('هل أنت متأكد من حذف بنك «${b.name}»؟'),
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
    await _run(() => TreasuryApi.instance.deleteBank(b.id), 'تم حذف البنك بنجاح');
  }

  Future<void> _openAdd() async {
    final nameCtrl = TextEditingController();
    final usageCtrl = TextEditingController();
    final balCtrl = TextEditingController(text: '0');
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
                    'إضافة بنك جديد',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      color: AppColors.navy,
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: nameCtrl,
                    decoration: const InputDecoration(labelText: 'اسم البنك *'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: usageCtrl,
                    decoration: const InputDecoration(labelText: 'الاستخدام'),
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
                  const SizedBox(height: 10),
                  AccountPickerField(
                    label: 'الحساب الأب (شجرة الحسابات)',
                    requiredField: true,
                    options: _accounts,
                    selectedId: parentId,
                    onSelected: (id) => setModal(() => parentId = id),
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
      () => TreasuryApi.instance.createBank({
        'name': nameCtrl.text.trim(),
        'type': 'main',
        'usage': usageCtrl.text.trim(),
        'balance': bal,
        'parent_account_id': parentId,
        if (bal > 0) 'counter_account_id': counterId,
      }),
      'تم إضافة البنك بنجاح',
    );
  }

  Future<void> _openEdit(BankItem b) async {
    final nameCtrl = TextEditingController(text: b.name);
    final usageCtrl = TextEditingController(text: b.usage ?? '');
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
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'تعديل بيانات البنك',
              style: TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 16,
                color: AppColors.navy,
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: nameCtrl,
              decoration: const InputDecoration(labelText: 'اسم البنك *'),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: usageCtrl,
              decoration: const InputDecoration(labelText: 'الاستخدام'),
            ),
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
      ),
    );
    if (saved != true) return;
    await _run(
      () => TreasuryApi.instance.updateBank(b.id, {
        'name': nameCtrl.text.trim(),
        'usage': usageCtrl.text.trim(),
        'type': b.type ?? 'main',
      }),
      'تم تحديث بيانات البنك بنجاح',
    );
  }

  Future<void> _openUsers(BankItem b) async {
    final selected = b.assignedUsers.map((u) => u.id).toSet();
    final saved = await showModalBottomSheet<Set<int>>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setModal) {
            return SafeArea(
              child: SizedBox(
                height: MediaQuery.of(ctx).size.height * 0.7,
                child: Column(
                  children: [
                    const Padding(
                      padding: EdgeInsets.all(16),
                      child: Text(
                        'صلاحيات المستخدمين',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 16,
                          color: AppColors.navy,
                        ),
                      ),
                    ),
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 16),
                      child: Text(
                        'بدون اختيار = البنك متاح للجميع',
                        style: TextStyle(color: AppColors.textMuted),
                      ),
                    ),
                    Expanded(
                      child: ListView(
                        children: _users
                            .map(
                              (u) => CheckboxListTile(
                                value: selected.contains(u.id),
                                title: Text(u.name),
                                subtitle: u.department == null
                                    ? null
                                    : Text(u.department!),
                                onChanged: (v) {
                                  setModal(() {
                                    if (v == true) {
                                      selected.add(u.id);
                                    } else {
                                      selected.remove(u.id);
                                    }
                                  });
                                },
                              ),
                            )
                            .toList(),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: ElevatedButton(
                        onPressed: () => Navigator.pop(ctx, selected),
                        child: const Text('حفظ الصلاحيات'),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
    if (saved == null) return;
    await _run(
      () => TreasuryApi.instance.syncBankUsers(b.id, saved.toList()),
      'تم حفظ صلاحيات البنك',
    );
  }

  Future<void> _openTransfer({BankItem? from}) async {
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
            BankItem? fromBank;
            for (final b in _all) {
              if (b.id == fromId) fromBank = b;
            }
            return SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text(
                    'تحويل بين البنوك',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      color: AppColors.navy,
                    ),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    value: _all.any((b) => b.id == fromId) ? fromId : null,
                    decoration: const InputDecoration(labelText: 'من بنك *'),
                    items: _all
                        .map(
                          (b) => DropdownMenuItem(
                            value: b.id,
                            child: Text('${b.name} (${_money.format(b.balance)})'),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setModal(() => fromId = v),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    value: _all.any((b) => b.id == toId && b.id != fromId)
                        ? toId
                        : null,
                    decoration: const InputDecoration(labelText: 'إلى بنك *'),
                    items: _all
                        .where((b) => b.id != fromId)
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
                      helperText: fromBank == null
                          ? null
                          : 'المتاح: ${_money.format(fromBank.balance)}',
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
    BankItem? fromBank;
    for (final b in _all) {
      if (b.id == fromId) fromBank = b;
    }
    if (fromBank != null && amt > fromBank.balance) {
      _snack('المبلغ المطلوب أكبر من الرصيد المتاح');
      return;
    }
    await _run(
      () => TreasuryApi.instance.bankTransfer({
        'type': 'transfer_bank_to_bank',
        'from_id': fromId,
        'to_id': toId,
        'amount': amt,
        'date': date,
        'notes': notesCtrl.text,
      }),
      'تم التحويل بنجاح',
    );
  }

  void _actions(BankItem b) {
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: Text(b.name, style: const TextStyle(fontWeight: FontWeight.w800)),
              subtitle: Text(_money.format(b.balance)),
            ),
            const Divider(height: 1),
            if (_canUsers)
              ListTile(
                leading: const Icon(Icons.people_outline),
                title: const Text('صلاحيات المستخدمين'),
                onTap: () {
                  Navigator.pop(ctx);
                  _openUsers(b);
                },
              ),
            ListTile(
              leading: const Icon(Icons.edit_outlined),
              title: const Text('تعديل'),
              onTap: () {
                Navigator.pop(ctx);
                _openEdit(b);
              },
            ),
            ListTile(
              leading: const Icon(Icons.swap_horiz),
              title: const Text('تحويل'),
              onTap: () {
                Navigator.pop(ctx);
                _openTransfer(from: b);
              },
            ),
            ListTile(
              leading: const Icon(Icons.delete_outline, color: AppColors.danger),
              title: const Text('حذف', style: TextStyle(color: AppColors.danger)),
              onTap: () {
                Navigator.pop(ctx);
                _confirmDelete(b);
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
        title: const Text('إدارة البنوك'),
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
        label: const Text('إضافة بنك'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 6),
            child: Row(
              children: [
                Expanded(child: _stat('إجمالي الأرصدة', _money.format(_total))),
                const SizedBox(width: 8),
                Expanded(child: _stat('عدد البنوك', '${_all.length}')),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: TextField(
              controller: _searchCtrl,
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                hintText: 'بحث بالاسم أو الاستخدام...',
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

  Widget _stat(String label, String value) {
    return Container(
      padding: const EdgeInsets.all(12),
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
            style: const TextStyle(
              fontWeight: FontWeight.w800,
              color: AppColors.navy,
              fontSize: 16,
            ),
          ),
          Text(
            label,
            style: const TextStyle(
              color: AppColors.textMuted,
              fontWeight: FontWeight.w700,
              fontSize: 12,
            ),
          ),
        ],
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
          _all.isEmpty ? 'لا يوجد بنوك مضافة بعد' : 'لا توجد بنوك تطابق بحثك',
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
          final b = items[i];
          return InkWell(
            onTap: () => _actions(b),
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
                          b.name,
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            color: AppColors.navy,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Wrap(
                          spacing: 8,
                          children: [
                            if (b.usage != null)
                              Text(
                                b.usage!,
                                style: const TextStyle(
                                  color: AppColors.textMuted,
                                  fontSize: 12,
                                ),
                              ),
                            if (b.assetName != null)
                              Text(
                                b.assetName!,
                                style: const TextStyle(
                                  color: AppColors.info,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            Text(
                              b.assignedLabel,
                              style: const TextStyle(
                                color: AppColors.textMuted,
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  Text(
                    _money.format(b.balance),
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      color: b.balance < 0 ? AppColors.danger : AppColors.success,
                    ),
                  ),
                  IconButton(
                    onPressed: () => _actions(b),
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
