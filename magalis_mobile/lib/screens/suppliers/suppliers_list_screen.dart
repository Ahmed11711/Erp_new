import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api_client.dart';
import '../../services/suppliers_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import 'supplier_details_screen.dart';

/// قائمة الموردين — mirrors Angular `ListSuppliersComponent` + row actions.
class SuppliersListScreen extends StatefulWidget {
  const SuppliersListScreen({super.key});

  @override
  State<SuppliersListScreen> createState() => _SuppliersListScreenState();
}

class _SuppliersListScreenState extends State<SuppliersListScreen> {
  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  Timer? _debounce;

  List<SupplierItem> _items = [];
  List<SupplierTypeOption> _types = [];
  String _typeId = '';
  String _status = '';
  int _total = 0;
  double _sumBalance = 0;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    try {
      final types = await SuppliersApi.instance.types();
      if (mounted) setState(() => _types = types);
    } catch (_) {/* optional */}
    await _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await SuppliersApi.instance.search(
        name: _nameCtrl.text,
        phone: _phoneCtrl.text,
        typeId: _typeId.isEmpty ? null : _typeId,
        status: _status.isEmpty ? null : _status,
      );
      if (!mounted) return;
      setState(() {
        _items = page.items;
        _total = page.total;
        _sumBalance = page.sumOfBalance;
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
        _error = 'تعذر تحميل الموردين';
        _loading = false;
      });
    }
  }

  void _onSearchChanged(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), _load);
  }

  void _showActions(SupplierItem s) {
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: Text(
                s.name,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(Formatters.money(s.balance)),
            ),
            const Divider(height: 1),
            ListTile(
              leading: const Icon(Icons.receipt_long_outlined),
              title: const Text('تفاصيل حساب المورد'),
              onTap: () {
                Navigator.pop(ctx);
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => SupplierDetailsScreen(supplierId: s.id),
                  ),
                );
              },
            ),
            ListTile(
              leading: const Icon(Icons.edit_outlined),
              title: const Text('تعديل بيانات المورد'),
              onTap: () {
                Navigator.pop(ctx);
                _editSupplier(s);
              },
            ),
            ListTile(
              leading: const Icon(Icons.payments_outlined),
              title: const Text('سداد حساب المورد'),
              onTap: () {
                Navigator.pop(ctx);
                _paySupplier(s);
              },
            ),
            ListTile(
              leading: const Icon(Icons.delete_outline, color: AppColors.danger),
              title: const Text(
                'حذف المورد',
                style: TextStyle(color: AppColors.danger),
              ),
              onTap: () {
                Navigator.pop(ctx);
                _deleteSupplier(s);
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _editSupplier(SupplierItem s) async {
    SupplierItem current = s;
    try {
      current = await SuppliersApi.instance.getSupplier(s.id);
    } catch (_) {/* use list row */}

    final nameCtrl = TextEditingController(text: current.name);
    final phoneCtrl = TextEditingController(text: current.phone ?? '');
    final addressCtrl = TextEditingController(text: current.address ?? '');
    var typeId = current.typeId ?? '';
    // Ensure selected type exists in dropdown items.
    if (typeId.isNotEmpty && !_types.any((t) => t.id == typeId)) {
      typeId = '';
    }
    final formKey = GlobalKey<FormState>();

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setLocal) => AlertDialog(
            title: const Text('تعديل بيانات المورد'),
            content: Form(
              key: formKey,
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextFormField(
                      controller: nameCtrl,
                      decoration: const InputDecoration(
                        labelText: 'اسم المورد',
                        border: OutlineInputBorder(),
                      ),
                      validator: (v) =>
                          (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
                    ),
                    const SizedBox(height: 10),
                    TextFormField(
                      controller: phoneCtrl,
                      decoration: const InputDecoration(
                        labelText: 'الهاتف',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextFormField(
                      controller: addressCtrl,
                      decoration: const InputDecoration(
                        labelText: 'العنوان',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      value: typeId,
                      decoration: const InputDecoration(
                        labelText: 'الفئة',
                        border: OutlineInputBorder(),
                      ),
                      items: [
                        const DropdownMenuItem(
                          value: '',
                          child: Text('— بدون —'),
                        ),
                        for (final t in _types)
                          DropdownMenuItem(value: t.id, child: Text(t.name)),
                      ],
                      onChanged: (v) => setLocal(() => typeId = v ?? ''),
                    ),
                  ],
                ),
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('إلغاء'),
              ),
              FilledButton(
                onPressed: () {
                  if (formKey.currentState?.validate() != true) return;
                  Navigator.pop(ctx, true);
                },
                child: const Text('حفظ'),
              ),
            ],
          ),
        );
      },
    );

    final name = nameCtrl.text;
    final phone = phoneCtrl.text;
    final address = addressCtrl.text;
    nameCtrl.dispose();
    phoneCtrl.dispose();
    addressCtrl.dispose();
    if (ok != true) return;

    try {
      await SuppliersApi.instance.updateSupplier(
        id: s.id,
        name: name,
        phone: phone,
        address: address,
        typeId: typeId.isEmpty ? null : typeId,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تم تحديث المورد'),
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

  Future<void> _paySupplier(SupplierItem s) async {
    PaymentSources? sources;
    try {
      sources = await SuppliersApi.instance.paymentSources();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.message),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
      return;
    }

    final amountCtrl = TextEditingController(
      text: s.balance > 0 ? s.balance.toStringAsFixed(2) : '',
    );
    String paymentType = 'bank';
    String? sourceId;
    final formKey = GlobalKey<FormState>();

    List<PaymentSourceItem> sourcesFor(String type) {
      switch (type) {
        case 'safe':
          return sources!.safes;
        case 'service_account':
          return sources!.serviceAccounts;
        default:
          return sources!.banks;
      }
    }

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setLocal) {
            final list = sourcesFor(paymentType);
            return AlertDialog(
              title: Text('سداد — ${s.name}'),
              content: Form(
                key: formKey,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        'الذمة الحالية: ${Formatters.money(s.balance)}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          color: AppColors.navy,
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: amountCtrl,
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
                          if (n == null || n <= 0) return 'أدخل مبلغاً صحيحاً';
                          if (s.balance > 0 && n > s.balance + 0.000001) {
                            return 'المبلغ أكبر من ذمة المورد';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String>(
                        value: paymentType,
                        decoration: const InputDecoration(
                          labelText: 'نوع الصرف',
                          border: OutlineInputBorder(),
                        ),
                        items: const [
                          DropdownMenuItem(value: 'bank', child: Text('بنك')),
                          DropdownMenuItem(value: 'safe', child: Text('خزينة')),
                          DropdownMenuItem(
                            value: 'service_account',
                            child: Text('حساب خدمي'),
                          ),
                        ],
                        onChanged: (v) {
                          setLocal(() {
                            paymentType = v ?? 'bank';
                            sourceId = null;
                          });
                        },
                      ),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String>(
                        value: sourceId,
                        decoration: const InputDecoration(
                          labelText: 'مصدر الصرف',
                          border: OutlineInputBorder(),
                        ),
                        items: [
                          for (final src in list)
                            DropdownMenuItem(
                              value: src.id,
                              child: Text(
                                '${src.name} (${Formatters.moneyPlain(src.balance)})',
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                        ],
                        onChanged: (v) => setLocal(() => sourceId = v),
                        validator: (v) =>
                            (v == null || v.isEmpty) ? 'اختر المصدر' : null,
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx, false),
                  child: const Text('إلغاء'),
                ),
                FilledButton(
                  onPressed: () {
                    if (formKey.currentState?.validate() != true) return;
                    Navigator.pop(ctx, true);
                  },
                  child: const Text('سداد'),
                ),
              ],
            );
          },
        );
      },
    );

    final amount = double.tryParse(amountCtrl.text.trim());
    final selectedSource = sourceId;
    final selectedType = paymentType;
    amountCtrl.dispose();
    if (ok != true || amount == null || selectedSource == null) return;

    try {
      await SuppliersApi.instance.paySupplier(
        id: s.id,
        amount: amount,
        paymentType: selectedType,
        bankId: selectedType == 'bank' ? selectedSource : null,
        safeId: selectedType == 'safe' ? selectedSource : null,
        serviceAccountId:
            selectedType == 'service_account' ? selectedSource : null,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تم تسجيل السداد'),
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

  Future<void> _deleteSupplier(SupplierItem s) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('حذف المورد؟'),
        content: Text('سيتم حذف «${s.name}» إن أمكن.'),
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
      await SuppliersApi.instance.deleteSupplier(s.id);
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
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('الموردين'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        body: Column(
          children: [
            Container(
              width: double.infinity,
              color: AppColors.surface,
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              child: Column(
                children: [
                  TextField(
                    controller: _nameCtrl,
                    onChanged: _onSearchChanged,
                    decoration: _searchDecoration(
                      'بحث باسم المورد...',
                      Icons.person_search_outlined,
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _phoneCtrl,
                    onChanged: _onSearchChanged,
                    keyboardType: TextInputType.phone,
                    decoration: _searchDecoration(
                      'بحث بالهاتف...',
                      Icons.phone_outlined,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          value: _typeId,
                          isDense: true,
                          decoration: _dropdownDecoration('الفئة'),
                          items: [
                            const DropdownMenuItem(
                              value: '',
                              child: Text('كل الفئات'),
                            ),
                            for (final t in _types)
                              DropdownMenuItem(
                                value: t.id,
                                child: Text(t.name),
                              ),
                          ],
                          onChanged: (v) {
                            setState(() => _typeId = v ?? '');
                            _load();
                          },
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          value: _status,
                          isDense: true,
                          decoration: _dropdownDecoration('الحالة'),
                          items: const [
                            DropdownMenuItem(value: '', child: Text('الكل')),
                            DropdownMenuItem(
                              value: 'want',
                              child: Text('له ذمة (علينا)'),
                            ),
                            DropdownMenuItem(
                              value: 'own',
                              child: Text('عليه ذمة'),
                            ),
                          ],
                          onChanged: (v) {
                            setState(() => _status = v ?? '');
                            _load();
                          },
                        ),
                      ),
                    ],
                  ),
                  if (_total > 0) ...[
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Text(
                          '$_total مورد',
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppColors.textSecondary,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const Spacer(),
                        if (_status.isNotEmpty)
                          Text(
                            'المجموع: ${Formatters.money(_sumBalance)}',
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: AppColors.primary,
                            ),
                          ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
            Expanded(child: _buildBody()),
          ],
        ),
      ),
    );
  }

  InputDecoration _searchDecoration(String hint, IconData icon) {
    return InputDecoration(
      hintText: hint,
      prefixIcon: Icon(icon, size: 20),
      isDense: true,
      filled: true,
      fillColor: AppColors.bg,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
    );
  }

  InputDecoration _dropdownDecoration(String label) {
    return InputDecoration(
      labelText: label,
      isDense: true,
      filled: true,
      fillColor: AppColors.bg,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
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
                style: const TextStyle(color: AppColors.danger),
              ),
              const SizedBox(height: 12),
              FilledButton(onPressed: _load, child: const Text('إعادة المحاولة')),
            ],
          ),
        ),
      );
    }
    if (_items.isEmpty) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          primary: true,
          physics: const AlwaysScrollableScrollPhysics(),
          children: const [
            SizedBox(height: 120),
            Icon(Icons.people_outline, size: 48, color: AppColors.textMuted),
            SizedBox(height: 12),
            Center(
              child: Text(
                'لا يوجد موردون مطابقون',
                style: TextStyle(color: AppColors.textSecondary),
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        primary: true,
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
        itemCount: _items.length,
        separatorBuilder: (context, index) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final s = _items[index];
          return Material(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () => _showActions(s),
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(14),
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
                              fontSize: 15,
                              color: AppColors.navy,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            [
                              if (s.typeName != null) s.typeName!,
                              if (s.phone != null) s.phone!,
                            ].join(' · '),
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.textSecondary,
                            ),
                          ),
                          if (s.address != null) ...[
                            const SizedBox(height: 2),
                            Text(
                              s.address!,
                              style: const TextStyle(
                                fontSize: 11,
                                color: AppColors.textMuted,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          Formatters.money(s.balance),
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            color: s.balance > 0
                                ? AppColors.danger
                                : s.balance < 0
                                    ? AppColors.success
                                    : AppColors.textSecondary,
                          ),
                        ),
                        IconButton(
                          tooltip: 'إجراءات',
                          onPressed: () => _showActions(s),
                          icon: const Icon(Icons.more_vert),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
