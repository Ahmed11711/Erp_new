import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../services/treasury_api.dart';
import '../../theme/app_colors.dart';

enum BankSafeTransferMode { bankToSafe, safeToBank }

/// تحويل بنك ↔ خزينة — mirrors Angular `BankSafeTransferComponent`.
class BankSafeTransferScreen extends StatefulWidget {
  const BankSafeTransferScreen({super.key, required this.mode});

  final BankSafeTransferMode mode;

  @override
  State<BankSafeTransferScreen> createState() => _BankSafeTransferScreenState();
}

class _BankSafeTransferScreenState extends State<BankSafeTransferScreen> {
  static final _money = NumberFormat('#,##0.00', 'ar');
  List<BankItem> _banks = [];
  List<SafeItem> _safes = [];
  int? _fromId;
  int? _toId;
  final _amountCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  String _date = DateTime.now().toIso8601String().substring(0, 10);
  bool _loading = true;
  bool _busy = false;
  String? _error;

  bool get _bankToSafe => widget.mode == BankSafeTransferMode.bankToSafe;

  String get _title =>
      _bankToSafe ? 'تحويل من بنك إلى خزينة' : 'تحويل من خزينة إلى بنك';

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    _notesCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final banks = await TreasuryApi.instance.fetchBanks();
      final safes = await TreasuryApi.instance.fetchSafes();
      if (!mounted) return;
      setState(() {
        _banks = banks;
        _safes = safes;
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

  Future<void> _submit() async {
    final amount = double.tryParse(_amountCtrl.text.trim()) ?? 0;
    if (_fromId == null || _toId == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('يرجى تعبئة جميع الحقول بشكل صحيح')),
      );
      return;
    }
    setState(() => _busy = true);
    try {
      await TreasuryApi.instance.bankTransfer({
        'type': _bankToSafe
            ? 'transfer_bank_to_safe'
            : 'transfer_safe_to_bank',
        'from_id': _fromId,
        'to_id': _toId,
        'amount': amount,
        'date': _date,
        'notes': _notesCtrl.text,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم عملية التحويل بنجاح')),
      );
      _amountCtrl.clear();
      _notesCtrl.clear();
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
      appBar: AppBar(title: Text(_title)),
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
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if (_bankToSafe) ...[
                      DropdownButtonFormField<int>(
                        isExpanded: true,
                        value: _banks.any((b) => b.id == _fromId) ? _fromId : null,
                        decoration: const InputDecoration(labelText: 'من بنك'),
                        items: _banks
                            .map(
                              (b) => DropdownMenuItem(
                                value: b.id,
                                child: Text('${b.name} (${_money.format(b.balance)})'),
                              ),
                            )
                            .toList(),
                        onChanged: (v) => setState(() => _fromId = v),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<int>(
                        isExpanded: true,
                        value: _safes.any((s) => s.id == _toId) ? _toId : null,
                        decoration: const InputDecoration(labelText: 'إلى خزينة'),
                        items: _safes
                            .map(
                              (s) => DropdownMenuItem(
                                value: s.id,
                                child: Text('${s.name} (${_money.format(s.balance)})'),
                              ),
                            )
                            .toList(),
                        onChanged: (v) => setState(() => _toId = v),
                      ),
                    ] else ...[
                      DropdownButtonFormField<int>(
                        isExpanded: true,
                        value: _safes.any((s) => s.id == _fromId) ? _fromId : null,
                        decoration: const InputDecoration(labelText: 'من خزينة'),
                        items: _safes
                            .map(
                              (s) => DropdownMenuItem(
                                value: s.id,
                                child: Text('${s.name} (${_money.format(s.balance)})'),
                              ),
                            )
                            .toList(),
                        onChanged: (v) => setState(() => _fromId = v),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<int>(
                        isExpanded: true,
                        value: _banks.any((b) => b.id == _toId) ? _toId : null,
                        decoration: const InputDecoration(labelText: 'إلى بنك'),
                        items: _banks
                            .map(
                              (b) => DropdownMenuItem(
                                value: b.id,
                                child: Text('${b.name} (${_money.format(b.balance)})'),
                              ),
                            )
                            .toList(),
                        onChanged: (v) => setState(() => _toId = v),
                      ),
                    ],
                    const SizedBox(height: 12),
                    TextField(
                      controller: _amountCtrl,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      decoration: const InputDecoration(labelText: 'المبلغ'),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton(
                      onPressed: () async {
                        final d = await showDatePicker(
                          context: context,
                          initialDate: DateTime.tryParse(_date) ?? DateTime.now(),
                          firstDate: DateTime(2000),
                          lastDate: DateTime(2100),
                        );
                        if (d == null) return;
                        setState(() {
                          _date =
                              '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
                        });
                      },
                      child: Text('التاريخ: $_date'),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _notesCtrl,
                      maxLines: 3,
                      decoration: const InputDecoration(labelText: 'ملاحظات'),
                    ),
                    const SizedBox(height: 20),
                    ElevatedButton(
                      onPressed: _busy ? null : _submit,
                      child: Text(_busy ? 'جاري التحويل...' : 'تحويل'),
                    ),
                  ],
                ),
    );
  }
}
