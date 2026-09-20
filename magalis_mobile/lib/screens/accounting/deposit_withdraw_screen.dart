import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../services/treasury_api.dart';
import '../../theme/app_colors.dart';
import '../../widgets/account_picker_field.dart';

/// سحب وإيداع نقدي (بنك) — mirrors Angular `bank-deposit-withdraw`.
class DepositWithdrawScreen extends StatefulWidget {
  const DepositWithdrawScreen({super.key});

  @override
  State<DepositWithdrawScreen> createState() => _DepositWithdrawScreenState();
}

class _DepositWithdrawScreenState extends State<DepositWithdrawScreen> {
  static final _money = NumberFormat('#,##0.00', 'ar');
  List<BankItem> _banks = [];
  List<AccountOption> _accounts = [];
  List<DirectTxnItem> _history = [];
  int? _bankId;
  int? _filterBankId;
  int? _counterId;
  int? _editingId;
  String _type = 'receipt';
  final _amountCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  String _date = DateTime.now().toIso8601String().substring(0, 10);
  bool _loading = true;
  bool _busy = false;
  bool _histLoading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    _notesCtrl.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    try {
      final acc = await TreasuryApi.instance.fetchAccountOptions();
      if (mounted) setState(() => _accounts = acc);
    } catch (_) {}
    await _loadBanks();
  }

  Future<void> _loadBanks() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final banks = await TreasuryApi.instance.fetchBanks();
      if (!mounted) return;
      setState(() {
        _banks = banks;
        _loading = false;
      });
      await _loadHistory();
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

  Future<void> _loadHistory() async {
    setState(() => _histLoading = true);
    try {
      final rows = await TreasuryApi.instance.bankDirectHistory(
        bankId: _filterBankId,
      );
      if (!mounted) return;
      setState(() {
        _history = rows;
        _histLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _history = [];
        _histLoading = false;
      });
    }
  }

  void _resetForm() {
    _editingId = null;
    _amountCtrl.clear();
    _notesCtrl.clear();
    _type = 'receipt';
    _counterId = null;
    _date = DateTime.now().toIso8601String().substring(0, 10);
  }

  void _startEdit(DirectTxnItem row) {
    if (!row.editable) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('هذه العملية قديمة ولا يمكن تعديلها تلقائياً'),
        ),
      );
      return;
    }
    setState(() {
      _editingId = row.id;
      _bankId = row.bankId;
      _type = row.type;
      _amountCtrl.text = row.amount.toString();
      _date = row.date.length >= 10 ? row.date.substring(0, 10) : row.date;
      _notesCtrl.text = row.notes ?? '';
      _counterId = row.counterAccountId;
    });
  }

  Future<void> _submit() async {
    final amount = double.tryParse(_amountCtrl.text.trim()) ?? 0;
    if (_bankId == null || _counterId == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('يرجى تعبئة جميع الحقول واختيار حساب مقابل صحيح'),
        ),
      );
      return;
    }
    BankItem? bank;
    for (final b in _banks) {
      if (b.id == _bankId) bank = b;
    }
    if (bank != null && bank.assetId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('البنك المحدد غير مرتبط بحساب شجري')),
      );
      return;
    }
    setState(() => _busy = true);
    try {
      await TreasuryApi.instance.bankDirectTxn(
        {
          'bank_id': _bankId,
          'type': _type,
          'counter_account_id': _counterId,
          'amount': amount,
          'date': _date,
          'notes': _notesCtrl.text,
        },
        editId: _editingId,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_editingId == null ? 'تمت العملية بنجاح' : 'تم تعديل العملية بنجاح'),
        ),
      );
      setState(_resetForm);
      await _loadBanks();
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
        title: const Text('سحب وإيداع نقدي (بنك)'),
        actions: [
          if (_editingId != null)
            TextButton(
              onPressed: () => setState(_resetForm),
              child: const Text('عملية جديدة'),
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!, style: const TextStyle(color: AppColors.danger)),
                      ElevatedButton(
                        onPressed: _loadBanks,
                        child: const Text('إعادة المحاولة'),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadBanks,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                    children: [
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.info.withValues(alpha: 0.08),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Text(
                          'تُسجّل قيوداً على حساب البنك والحساب المقابل. يمكن تعديل العمليات من السجل أدناه.',
                          style: TextStyle(
                            color: AppColors.navy,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      const SizedBox(height: 14),
                      Text(
                        _editingId == null
                            ? 'عملية جديدة'
                            : 'تعديل عملية #$_editingId',
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          color: AppColors.navy,
                        ),
                      ),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<int>(
                        isExpanded: true,
                        value: _banks.any((b) => b.id == _bankId) ? _bankId : null,
                        decoration: const InputDecoration(labelText: 'البنك'),
                        items: _banks
                            .map(
                              (b) => DropdownMenuItem(
                                value: b.id,
                                child: Text('${b.name} (${_money.format(b.balance)})'),
                              ),
                            )
                            .toList(),
                        onChanged: (v) => setState(() => _bankId = v),
                      ),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String>(
                        isExpanded: true,
                        value: _type,
                        decoration: const InputDecoration(labelText: 'العملية'),
                        items: const [
                          DropdownMenuItem(value: 'receipt', child: Text('إيداع (قبض)')),
                          DropdownMenuItem(value: 'payment', child: Text('سحب (صرف)')),
                        ],
                        onChanged: (v) => setState(() => _type = v ?? 'receipt'),
                      ),
                      const SizedBox(height: 10),
                      AccountPickerField(
                        label: 'الحساب المقابل (مصدر/وجهة الأموال)',
                        requiredField: true,
                        options: _accounts,
                        selectedId: _counterId,
                        onSelected: (id) => setState(() => _counterId = id),
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _amountCtrl,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: const InputDecoration(labelText: 'المبلغ'),
                      ),
                      const SizedBox(height: 10),
                      OutlinedButton(
                        onPressed: () async {
                          final d = await showDatePicker(
                            context: context,
                            initialDate:
                                DateTime.tryParse(_date) ?? DateTime.now(),
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
                      const SizedBox(height: 10),
                      TextField(
                        controller: _notesCtrl,
                        maxLines: 2,
                        decoration: const InputDecoration(labelText: 'ملاحظات'),
                      ),
                      const SizedBox(height: 12),
                      ElevatedButton(
                        onPressed: _busy ? null : _submit,
                        child: Text(
                          _busy
                              ? 'جاري التنفيذ...'
                              : (_editingId == null ? 'حفظ' : 'حفظ التعديل'),
                        ),
                      ),
                      const SizedBox(height: 24),
                      Row(
                        children: [
                          const Expanded(
                            child: Text(
                              'سجل العمليات',
                              style: TextStyle(
                                fontWeight: FontWeight.w800,
                                color: AppColors.navy,
                              ),
                            ),
                          ),
                          DropdownButton<int?>(
                            value: _filterBankId,
                            hint: const Text('كل البنوك'),
                            items: [
                              const DropdownMenuItem<int?>(
                                value: null,
                                child: Text('كل البنوك'),
                              ),
                              ..._banks.map(
                                (b) => DropdownMenuItem<int?>(
                                  value: b.id,
                                  child: Text(b.name),
                                ),
                              ),
                            ],
                            onChanged: (v) {
                              setState(() => _filterBankId = v);
                              _loadHistory();
                            },
                          ),
                        ],
                      ),
                      if (_histLoading)
                        const Padding(
                          padding: EdgeInsets.all(24),
                          child: Center(child: CircularProgressIndicator()),
                        )
                      else if (_history.isEmpty)
                        const Padding(
                          padding: EdgeInsets.all(16),
                          child: Text(
                            'لا توجد عمليات مسجّلة قابلة للتعديل',
                            style: TextStyle(color: AppColors.textMuted),
                          ),
                        )
                      else
                        ..._history.map(_histCard),
                    ],
                  ),
                ),
    );
  }

  Widget _histCard(DirectTxnItem row) {
    return Container(
      margin: const EdgeInsets.only(top: 8),
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
                  '${row.bankName ?? ''} · ${row.date}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.navy,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  row.counterAccountName == null
                      ? ''
                      : (row.counterAccountCode == null
                          ? row.counterAccountName!
                          : '${row.counterAccountName} — ${row.counterAccountCode}'),
                  style: const TextStyle(
                    color: AppColors.textMuted,
                    fontSize: 12,
                  ),
                ),
                if (row.notes != null)
                  Text(
                    row.notes!,
                    style: const TextStyle(fontSize: 12),
                  ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: (row.isReceipt ? AppColors.success : AppColors.warning)
                      .withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  row.isReceipt ? 'إيداع' : 'سحب',
                  style: TextStyle(
                    color: row.isReceipt ? AppColors.success : AppColors.warning,
                    fontWeight: FontWeight.w800,
                    fontSize: 11,
                  ),
                ),
              ),
              Text(
                _money.format(row.amount),
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              if (row.editable)
                TextButton(
                  onPressed: () => _startEdit(row),
                  child: const Text('تعديل'),
                ),
            ],
          ),
        ],
      ),
    );
  }
}
