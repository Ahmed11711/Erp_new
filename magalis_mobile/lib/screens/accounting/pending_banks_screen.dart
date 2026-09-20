import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../services/treasury_api.dart';
import '../../theme/app_colors.dart';

/// قيد الانتظار — mirrors Angular `pending` banks approvals.
class PendingBanksScreen extends StatefulWidget {
  const PendingBanksScreen({super.key});

  @override
  State<PendingBanksScreen> createState() => _PendingBanksScreenState();
}

class _PendingBanksScreenState extends State<PendingBanksScreen> {
  static final _money = NumberFormat('#,##0.00', 'ar');
  List<PendingBankItem> _items = [];
  List<BankItem> _banks = [];
  String _status = 'pending';
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
      final banks = await TreasuryApi.instance.fetchBankSelect();
      if (mounted) setState(() => _banks = banks);
    } catch (_) {}
    await _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await TreasuryApi.instance.fetchPending(
        perPage: 50,
        status: _status,
      );
      if (!mounted) return;
      setState(() {
        _items = page.items;
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
        _error = 'تعذر تحميل قيد الانتظار';
        _loading = false;
      });
    }
  }

  Future<void> _approve(PendingBankItem item) async {
    int? bankId = item.bankId;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setLocal) {
            return AlertDialog(
              title: Text('موافقة على ${item.details ?? ''}'),
              content: DropdownButtonFormField<int>(
                isExpanded: true,
                value: _banks.any((b) => b.id == bankId) ? bankId : null,
                decoration: const InputDecoration(labelText: 'اختر الخزينة / البنك'),
                items: _banks
                    .map(
                      (b) => DropdownMenuItem(value: b.id, child: Text(b.name)),
                    )
                    .toList(),
                onChanged: (v) => setLocal(() => bankId = v),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx, false),
                  child: const Text('إلغاء'),
                ),
                ElevatedButton(
                  onPressed: () => Navigator.pop(ctx, true),
                  child: const Text('تأكيد'),
                ),
              ],
            );
          },
        );
      },
    );
    if (ok != true || bankId == null) return;
    setState(() => _busy = true);
    try {
      await TreasuryApi.instance.setPendingStatus(
        id: item.id,
        status: 'approved',
        bankId: bankId,
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _reject(PendingBankItem item) async {
    setState(() => _busy = true);
    try {
      await TreasuryApi.instance.setPendingStatus(
        id: item.id,
        status: 'rejected',
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        title: const Text('قيد الانتظار'),
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
      body: Column(
        children: [
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 6),
            child: Row(
              children: [
                _chip('', 'الكل'),
                _chip('pending', 'قيد الانتظار'),
                _chip('approved', 'تم الموافقة'),
                _chip('rejected', 'مرفوضه'),
              ],
            ),
          ),
          Expanded(child: _body()),
        ],
      ),
    );
  }

  Widget _chip(String value, String label) {
    final selected = _status == value;
    return Padding(
      padding: const EdgeInsetsDirectional.only(end: 8),
      child: FilterChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) {
          setState(() => _status = value);
          _load();
        },
        selectedColor: AppColors.primaryBg,
        checkmarkColor: AppColors.primary,
        labelStyle: TextStyle(
          fontWeight: FontWeight.w700,
          color: selected ? AppColors.primary : AppColors.textSecondary,
          fontSize: 12,
        ),
        side: BorderSide(color: selected ? AppColors.primary : AppColors.border),
        backgroundColor: AppColors.surface,
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
    if (_items.isEmpty) {
      return const Center(
        child: Text(
          'لا توجد عمليات',
          style: TextStyle(
            color: AppColors.textMuted,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(12, 4, 12, 24),
        itemCount: _items.length,
        itemBuilder: (context, i) {
          final item = _items[i];
          return Container(
            margin: const EdgeInsets.symmetric(vertical: 4),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.surface,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.type.isEmpty ? (item.details ?? 'عملية') : item.type,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          color: AppColors.navy,
                        ),
                      ),
                    ),
                    Text(
                      item.statusLabel,
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 12,
                        color: item.isPending
                            ? AppColors.warning
                            : item.status == 'approved'
                                ? AppColors.success
                                : AppColors.danger,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  _money.format(item.amount),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                if (item.ref != null)
                  Text('مرجع: ${item.ref}', style: const TextStyle(fontSize: 12)),
                if (item.bankName != null)
                  Text(item.bankName!, style: const TextStyle(fontSize: 12)),
                if (item.details != null)
                  Text(item.details!, style: const TextStyle(fontSize: 12)),
                Text(
                  [
                    if (item.userName != null) item.userName,
                    if (item.createdAt != null) item.createdAt,
                  ].whereType<String>().join(' · '),
                  style: const TextStyle(
                    color: AppColors.textMuted,
                    fontSize: 11,
                  ),
                ),
                if (item.isPending)
                  Row(
                    children: [
                      TextButton(
                        onPressed: () => _approve(item),
                        child: const Text('موافقة'),
                      ),
                      TextButton(
                        onPressed: () => _reject(item),
                        child: const Text(
                          'رفض',
                          style: TextStyle(color: AppColors.danger),
                        ),
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
