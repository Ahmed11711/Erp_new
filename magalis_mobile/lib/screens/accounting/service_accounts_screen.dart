import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../config/api_config.dart';
import '../../core/api_client.dart';
import '../../services/treasury_api.dart';
import '../../theme/app_colors.dart';
import '../../widgets/account_picker_field.dart';

/// حسابات الخدمات — mirrors Angular `service-accounts-list`.
class ServiceAccountsScreen extends StatefulWidget {
  const ServiceAccountsScreen({super.key});

  @override
  State<ServiceAccountsScreen> createState() => _ServiceAccountsScreenState();
}

class _ServiceAccountsScreenState extends State<ServiceAccountsScreen> {
  static final _money = NumberFormat('#,##0.00', 'ar');
  List<ServiceAccountItem> _items = [];
  List<AccountOption> _accounts = [];
  bool _loading = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    try {
      final acc = await TreasuryApi.instance.fetchAccountOptions();
      if (mounted) setState(() => _accounts = acc);
    } catch (_) {}
    await _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await TreasuryApi.instance.fetchServiceAccounts();
      if (!mounted) return;
      setState(() {
        _items = items;
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
        _error = 'تعذر تحميل الحسابات الخدمية';
        _loading = false;
      });
    }
  }

  void _snack(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  Future<void> _openForm({ServiceAccountItem? edit}) async {
    final isEdit = edit != null;
    final nameCtrl = TextEditingController(text: edit?.name ?? '');
    final numCtrl = TextEditingController(text: edit?.accountNumber ?? '');
    final descCtrl = TextEditingController(text: edit?.description ?? '');
    final balCtrl = TextEditingController(
      text: edit != null ? edit.balance.toString() : '0',
    );
    int? accountId = edit?.accountId;
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
                  Text(
                    isEdit ? 'تعديل الحساب الخدمي' : 'إضافة حساب خدمي',
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      color: AppColors.navy,
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: nameCtrl,
                    decoration: const InputDecoration(labelText: 'اسم الحساب *'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: numCtrl,
                    decoration: const InputDecoration(labelText: 'رقم الحساب'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: descCtrl,
                    decoration: const InputDecoration(labelText: 'الوصف'),
                  ),
                  if (!isEdit) ...[
                    const SizedBox(height: 10),
                    TextField(
                      controller: balCtrl,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      onChanged: (_) => setModal(() {}),
                      decoration: const InputDecoration(labelText: 'الرصيد'),
                    ),
                  ],
                  const SizedBox(height: 10),
                  AccountPickerField(
                    label: 'الحساب المرتبط',
                    requiredField: true,
                    options: _accounts,
                    selectedId: accountId,
                    onSelected: (id) => setModal(() => accountId = id),
                  ),
                  if (!isEdit && bal > 0) ...[
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
                      if (nameCtrl.text.trim().isEmpty || accountId == null) {
                        return;
                      }
                      if (!isEdit && bal > 0 && counterId == null) return;
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
    setState(() => _busy = true);
    try {
      if (isEdit) {
        await TreasuryApi.instance.updateServiceAccount(edit.id, {
          'name': nameCtrl.text.trim(),
          'account_number': numCtrl.text.trim(),
          'description': descCtrl.text.trim(),
          'account_id': accountId,
        });
        _snack('تم التعديل بنجاح');
      } else {
        final bal = double.tryParse(balCtrl.text) ?? 0;
        await TreasuryApi.instance.createServiceAccount({
          'name': nameCtrl.text.trim(),
          'account_number': numCtrl.text.trim(),
          'description': descCtrl.text.trim(),
          'account_id': accountId,
          'balance': bal,
          if (bal > 0) 'counter_account_id': counterId,
        });
        _snack('تم الحفظ بنجاح');
      }
      await _load();
    } on ApiException catch (e) {
      _snack(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openTransfer() async {
    int? fromId;
    int? toId;
    final amountCtrl = TextEditingController();
    final notesCtrl = TextEditingController();
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
                    'تحويل رصيد',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      color: AppColors.navy,
                    ),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    value: _items.any((a) => a.id == fromId) ? fromId : null,
                    decoration: const InputDecoration(labelText: 'من حساب *'),
                    items: _items
                        .map(
                          (a) => DropdownMenuItem(
                            value: a.id,
                            child: Text('${a.name} (${_money.format(a.balance)})'),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setModal(() => fromId = v),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    value: _items.any((a) => a.id == toId && a.id != fromId)
                        ? toId
                        : null,
                    decoration: const InputDecoration(labelText: 'إلى حساب *'),
                    items: _items
                        .where((a) => a.id != fromId)
                        .map(
                          (a) => DropdownMenuItem(
                            value: a.id,
                            child: Text(a.name),
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
                    decoration: const InputDecoration(labelText: 'المبلغ *'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: notesCtrl,
                    decoration: const InputDecoration(labelText: 'ملاحظات'),
                  ),
                  const SizedBox(height: 14),
                  ElevatedButton(
                    onPressed: () {
                      final amt = double.tryParse(amountCtrl.text) ?? 0;
                      if (fromId == null || toId == null || amt <= 0) return;
                      if (fromId == toId) return;
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
    if (fromId == null || toId == null || amt <= 0 || fromId == toId) {
      _snack('لا يمكن التحويل لنفس الحساب');
      return;
    }
    setState(() => _busy = true);
    try {
      await TreasuryApi.instance.transferServiceAccount(
        fromId: fromId!,
        toId: toId!,
        amount: amt,
        notes: notesCtrl.text,
      );
      _snack('تم التحويل بنجاح');
      await _load();
    } on ApiException catch (e) {
      _snack(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _delete(ServiceAccountItem a) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('حذف الحساب الخدمي'),
        content: Text('حذف الحساب الخدمي «${a.name}»؟'),
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
      await TreasuryApi.instance.deleteServiceAccount(a.id);
      _snack('تم حذف الحساب بنجاح');
      await _load();
    } on ApiException catch (e) {
      _snack(e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String? _imgUrl(String? img) {
    if (img == null || img.isEmpty) return null;
    if (img.startsWith('http')) return img;
    return '${ApiConfig.imgUrl}$img';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        title: const Text('الحسابات الخدمية'),
        actions: [
          IconButton(
            onPressed: _busy ? null : _openTransfer,
            icon: const Icon(Icons.swap_horiz),
            tooltip: 'تحويل رصيد',
          ),
          IconButton(
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _busy ? null : () => _openForm(),
        icon: const Icon(Icons.add),
        label: const Text('حساب جديد'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!, style: const TextStyle(color: AppColors.danger)),
                      ElevatedButton(onPressed: _load, child: const Text('إعادة المحاولة')),
                    ],
                  ),
                )
              : _items.isEmpty
                  ? const Center(
                      child: Text(
                        'لا توجد حسابات خدمية',
                        style: TextStyle(
                          color: AppColors.textMuted,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.fromLTRB(12, 8, 12, 88),
                        itemCount: _items.length,
                        itemBuilder: (context, i) {
                          final a = _items[i];
                          final img = _imgUrl(a.img);
                          return Container(
                            margin: const EdgeInsets.symmetric(vertical: 4),
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: AppColors.surface,
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: AppColors.border),
                            ),
                            child: Row(
                              children: [
                                CircleAvatar(
                                  backgroundColor: AppColors.primaryBg,
                                  backgroundImage:
                                      img == null ? null : NetworkImage(img),
                                  child: img == null
                                      ? const Icon(Icons.account_balance_wallet,
                                          color: AppColors.primary)
                                      : null,
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        a.name,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w800,
                                          color: AppColors.navy,
                                        ),
                                      ),
                                      if (a.accountNumber != null)
                                        Text(
                                          a.accountNumber!,
                                          style: const TextStyle(
                                            color: AppColors.textMuted,
                                            fontSize: 12,
                                          ),
                                        ),
                                      if (a.description != null)
                                        Text(
                                          a.description!,
                                          style: const TextStyle(fontSize: 12),
                                        ),
                                    ],
                                  ),
                                ),
                                Text(
                                  _money.format(a.balance),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.success,
                                  ),
                                ),
                                IconButton(
                                  onPressed: () => _openForm(edit: a),
                                  icon: const Icon(Icons.edit_outlined),
                                ),
                                IconButton(
                                  onPressed: () => _delete(a),
                                  icon: const Icon(
                                    Icons.delete_outline,
                                    color: AppColors.danger,
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
