import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api_client.dart';
import '../../services/vouchers_api.dart';
import '../../theme/app_colors.dart';

/// إضافة / تعديل سند — Angular `VoucherDialogComponent`.
class VoucherFormDialog extends StatefulWidget {
  const VoucherFormDialog({
    super.key,
    required this.voucherType,
    this.voucher,
  });

  final String voucherType;
  final VoucherItem? voucher;

  static Future<bool> open(
    BuildContext context, {
    required String voucherType,
    VoucherItem? voucher,
  }) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => VoucherFormDialog(
        voucherType: voucherType,
        voucher: voucher,
      ),
    );
    return ok == true;
  }

  @override
  State<VoucherFormDialog> createState() => _VoucherFormDialogState();
}

class _VoucherFormDialogState extends State<VoucherFormDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _amount;
  late final TextEditingController _ref;
  late final TextEditingController _notes;

  List<VoucherParty> _parties = [];
  List<VoucherAccountOption> _allAccounts = [];
  VoucherPaymentSources _sources = const VoucherPaymentSources();

  late String _date;
  String _type = 'receipt';
  String _paymentMethod = 'cash';
  int? _partyId;
  int? _accountId;
  bool _advance = false;
  bool _saving = false;
  bool _loadingLookups = true;
  String? _error;

  bool get _isClient => widget.voucherType == 'client';

  @override
  void initState() {
    super.initState();
    final v = widget.voucher;
    _date = (v?.date.length ?? 0) >= 10
        ? v!.date.substring(0, 10)
        : DateTime.now().toIso8601String().substring(0, 10);
    _type = v?.type ?? 'receipt';
    _partyId = _isClient ? v?.clientId : v?.supplierId;
    _accountId = v?.accountId;
    _amount = TextEditingController(
      text: v == null ? '' : v.amount.toString(),
    );
    _ref = TextEditingController(text: v?.referenceNumber ?? '');
    _notes = TextEditingController(text: v?.notes ?? '');
    _advance = (v?.notes ?? '').contains(' (سلفة)');
    _bootstrap();
  }

  @override
  void dispose() {
    _amount.dispose();
    _ref.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    try {
      final parties = _isClient
          ? await VouchersApi.instance.clients()
          : await VouchersApi.instance.suppliers();
      List<VoucherAccountOption> accounts = const [];
      VoucherPaymentSources sources = const VoucherPaymentSources();
      try {
        accounts = await VouchersApi.instance.treeAccounts();
      } catch (_) {}
      try {
        sources = await VouchersApi.instance.paymentSources();
      } catch (_) {}
      if (!mounted) return;
      setState(() {
        _parties = parties;
        _allAccounts = accounts;
        _sources = sources;
        if (_accountId != null) {
          _paymentMethod = _inferMethod(_accountId!);
        }
        _loadingLookups = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loadingLookups = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'تعذر تحميل بيانات السند';
        _loadingLookups = false;
      });
    }
  }

  String _inferMethod(int accountId) {
    if (_sources.safes.any((s) => s.id == accountId)) return 'cash';
    if (_sources.banks.any((s) => s.id == accountId)) return 'bank';
    if (_sources.serviceAccounts.any((s) => s.id == accountId)) {
      return 'service_account';
    }
    VoucherAccountOption? acc;
    for (final a in _allAccounts) {
      if (a.id == accountId) acc = a;
    }
    final name = (acc?.name ?? '').toLowerCase();
    if (name.contains('بنك') || name.contains('bank') || name.contains('مصرف')) {
      return 'bank';
    }
    if (name.contains('خدم') || name.contains('service')) {
      return 'service_account';
    }
    return 'cash';
  }

  List<VoucherAccountOption> get _filteredAccounts {
    List<VoucherAccountOption> fromSources;
    if (_paymentMethod == 'cash') {
      fromSources = _sources.safes;
    } else if (_paymentMethod == 'bank') {
      fromSources = _sources.banks;
    } else {
      fromSources = _sources.serviceAccounts;
    }
    if (fromSources.isNotEmpty) {
      if (_accountId != null && !fromSources.any((a) => a.id == _accountId)) {
        for (final a in _allAccounts) {
          if (a.id == _accountId) return [...fromSources, a];
        }
      }
      return fromSources;
    }
    bool match(VoucherAccountOption a) {
      final name = a.name.toLowerCase();
      if (_paymentMethod == 'cash') {
        return name.contains('صندوق') ||
            name.contains('cash') ||
            name.contains('خزينة');
      }
      if (_paymentMethod == 'bank') {
        return name.contains('بنك') ||
            name.contains('bank') ||
            name.contains('مصرف');
      }
      return name.contains('خدم');
    }

    final filtered = _allAccounts.where(match).toList();
    return filtered.isEmpty ? _allAccounts : filtered;
  }

  String get _accountLabel {
    if (_paymentMethod == 'cash') return 'الحساب (الخزينة)';
    if (_paymentMethod == 'bank') return 'الحساب (البنك)';
    return 'الحساب (خدمي)';
  }

  void _toggleAdvance(bool? checked) {
    final on = checked == true;
    var notes = _notes.text;
    const tag = ' (سلفة)';
    if (on) {
      if (!notes.contains(tag)) notes = '$notes$tag';
    } else {
      notes = notes.replaceAll(tag, '');
    }
    setState(() {
      _advance = on;
      _notes.text = notes;
    });
  }

  Future<void> _submit() async {
    if (_formKey.currentState?.validate() != true) return;
    final amount = double.tryParse(_amount.text.trim()) ?? 0;
    String? partyName;
    for (final p in _parties) {
      if (p.id == _partyId) partyName = p.name;
    }
    final payload = <String, dynamic>{
      'date': _date,
      'type': _type,
      'voucher_type': widget.voucherType,
      'account_id': _accountId,
      'amount': amount,
      'notes': _notes.text.trim(),
      'reference_number': _ref.text.trim(),
      if (partyName != null) 'client_or_supplier_name': partyName,
      if (_isClient) 'client_id': _partyId,
      if (!_isClient) 'supplier_id': _partyId,
    };
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await VouchersApi.instance.save(id: widget.voucher?.id, payload: payload);
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
        _error = 'حدث خطأ أثناء الحفظ';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final accounts = _filteredAccounts;
    final accountValue =
        accounts.any((a) => a.id == _accountId) ? _accountId : null;
    final partyValue =
        _parties.any((p) => p.id == _partyId) ? _partyId : null;

    return Directionality(
      textDirection: TextDirection.rtl,
      child: AlertDialog(
        title: Text(_isClient ? 'سند عميل جديد' : 'سند مورد جديد'),
        content: SizedBox(
          width: 420,
          child: _loadingLookups
              ? const Padding(
                  padding: EdgeInsets.all(24),
                  child: Center(child: CircularProgressIndicator()),
                )
              : Form(
                  key: _formKey,
                  child: SingleChildScrollView(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (_error != null)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: Text(
                              _error!,
                              style: const TextStyle(color: AppColors.danger),
                            ),
                          ),
                        InputDecorator(
                          decoration: const InputDecoration(
                            labelText: 'التاريخ',
                            border: OutlineInputBorder(),
                          ),
                          child: InkWell(
                            onTap: () async {
                              final picked = await showDatePicker(
                                context: context,
                                initialDate: DateTime.tryParse(_date) ??
                                    DateTime.now(),
                                firstDate: DateTime(2020),
                                lastDate: DateTime(2040),
                              );
                              if (picked == null) return;
                              setState(() {
                                _date = picked.toIso8601String().substring(0, 10);
                              });
                            },
                            child: Text(_date, textDirection: TextDirection.ltr),
                          ),
                        ),
                        const SizedBox(height: 10),
                        DropdownButtonFormField<String>(
                          value: _type,
                          decoration: const InputDecoration(
                            labelText: 'نوع السند',
                            border: OutlineInputBorder(),
                          ),
                          items: const [
                            DropdownMenuItem(
                              value: 'receipt',
                              child: Text('قبض'),
                            ),
                            DropdownMenuItem(
                              value: 'payment',
                              child: Text('صرف'),
                            ),
                          ],
                          onChanged: (v) => setState(() => _type = v ?? 'receipt'),
                        ),
                        const SizedBox(height: 10),
                        DropdownButtonFormField<int>(
                          value: partyValue,
                          decoration: InputDecoration(
                            labelText: _isClient ? 'العميل' : 'المورد',
                            border: const OutlineInputBorder(),
                          ),
                          items: [
                            for (final p in _parties)
                              DropdownMenuItem(
                                value: p.id,
                                child: Text(p.name),
                              ),
                          ],
                          onChanged: (v) => setState(() => _partyId = v),
                          validator: (v) => v == null ? 'مطلوب' : null,
                        ),
                        const SizedBox(height: 10),
                        DropdownButtonFormField<String>(
                          value: _paymentMethod,
                          decoration: const InputDecoration(
                            labelText: 'طريقة الدفع',
                            border: OutlineInputBorder(),
                          ),
                          items: const [
                            DropdownMenuItem(
                              value: 'cash',
                              child: Text('نقدي (خزينة)'),
                            ),
                            DropdownMenuItem(
                              value: 'bank',
                              child: Text('بنكي'),
                            ),
                            DropdownMenuItem(
                              value: 'service_account',
                              child: Text('حساب خدمي'),
                            ),
                          ],
                          onChanged: (v) {
                            setState(() {
                              _paymentMethod = v ?? 'cash';
                              _accountId = null;
                            });
                          },
                        ),
                        const SizedBox(height: 10),
                        DropdownButtonFormField<int>(
                          value: accountValue,
                          decoration: InputDecoration(
                            labelText: _accountLabel,
                            border: const OutlineInputBorder(),
                          ),
                          items: [
                            for (final a in accounts)
                              DropdownMenuItem(
                                value: a.id,
                                child: Text(a.name),
                              ),
                          ],
                          onChanged: (v) => setState(() => _accountId = v),
                          validator: (v) => v == null ? 'مطلوب' : null,
                        ),
                        const SizedBox(height: 10),
                        TextFormField(
                          controller: _amount,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                          ],
                          decoration: const InputDecoration(
                            labelText: 'المبلغ',
                            border: OutlineInputBorder(),
                          ),
                          validator: (v) {
                            final n = double.tryParse((v ?? '').trim());
                            if (n == null || n < 0.01) return 'مطلوب';
                            return null;
                          },
                        ),
                        const SizedBox(height: 10),
                        TextFormField(
                          controller: _ref,
                          decoration: const InputDecoration(
                            labelText: 'رقم مرجعي (اختياري)',
                            border: OutlineInputBorder(),
                          ),
                        ),
                        const SizedBox(height: 10),
                        TextFormField(
                          controller: _notes,
                          maxLines: 3,
                          decoration: const InputDecoration(
                            labelText: 'ملاحظات',
                            hintText: 'أدخل تفاصيل العملية...',
                            border: OutlineInputBorder(),
                          ),
                        ),
                        CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          value: _advance,
                          onChanged: _toggleAdvance,
                          title: const Text(
                            'هل هذه سلفة؟ (سيتم إضافة "سلفة" للملاحظات)',
                            style: TextStyle(fontSize: 13),
                          ),
                          controlAffinity: ListTileControlAffinity.leading,
                        ),
                      ],
                    ),
                  ),
                ),
        ),
        actions: [
          TextButton(
            onPressed: _saving ? null : () => Navigator.pop(context, false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: _saving || _loadingLookups ? null : _submit,
            child: Text(_saving ? 'جاري الحفظ...' : 'حفظ'),
          ),
        ],
      ),
    );
  }
}
