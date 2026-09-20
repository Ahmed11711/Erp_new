import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api_client.dart';
import '../../services/purchases_api.dart';
import '../../services/suppliers_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';

/// إضافة / تعديل فاتورة مشتريات — mirrors Angular `AddInvoiceComponent`.
class AddPurchaseInvoiceScreen extends StatefulWidget {
  const AddPurchaseInvoiceScreen({super.key, this.invoiceId});

  final String? invoiceId;

  @override
  State<AddPurchaseInvoiceScreen> createState() =>
      _AddPurchaseInvoiceScreenState();
}

class _AddPurchaseInvoiceScreenState extends State<AddPurchaseInvoiceScreen> {
  final _externalNoCtrl = TextEditingController();
  final _customNoCtrl = TextEditingController();
  final _qtyCtrl = TextEditingController(text: '1');
  final _priceCtrl = TextEditingController();
  final _paidCtrl = TextEditingController(text: '0');
  final _transportCtrl = TextEditingController(text: '0');

  List<SupplierTypeOption> _suppliers = [];
  List<PurchaseCategoryOption> _categories = [];
  List<PurchaseLineDraft> _lines = [];
  PaymentSources _paymentSources = const PaymentSources();

  String _invoiceType = 'تم الاستلام';
  String? _supplierId;
  String? _categoryId;
  String? _receiptDate;
  String _paymentType = 'bank';
  String? _paymentSourceId;
  bool _loading = true;
  bool _saving = false;
  String? _error;
  bool _invoicePriceEdited = false;

  bool get _isEdit =>
      widget.invoiceId != null && widget.invoiceId!.isNotEmpty;

  double get _productsTotal =>
      _lines.fold<double>(0, (s, e) => s + e.total);

  double get _transport => double.tryParse(_transportCtrl.text.trim()) ?? 0;

  double get _paid => (double.tryParse(_paidCtrl.text.trim()) ?? 0).abs();

  double get _grandTotal => _productsTotal + _transport;

  double get _due => (_grandTotal - _paid).clamp(0, double.infinity);

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _receiptDate =
        '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
    _bootstrap();
  }

  @override
  void dispose() {
    _externalNoCtrl.dispose();
    _customNoCtrl.dispose();
    _qtyCtrl.dispose();
    _priceCtrl.dispose();
    _paidCtrl.dispose();
    _transportCtrl.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        SuppliersApi.instance.supplierNames(),
        PurchasesApi.instance.rawCategories(),
        SuppliersApi.instance.paymentSources(),
      ]);
      if (!mounted) return;
      setState(() {
        _suppliers = results[0] as List<SupplierTypeOption>;
        _categories = results[1] as List<PurchaseCategoryOption>;
        _paymentSources = results[2] as PaymentSources;
        _loading = false;
      });
      if (_isEdit) await _loadForEdit();
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'تعذر تحميل بيانات النموذج';
        _loading = false;
      });
    }
  }

  Future<void> _loadForEdit() async {
    try {
      final detail =
          await PurchasesApi.instance.show(widget.invoiceId!, forEdit: true);
      if (!mounted) return;
      setState(() {
        _invoiceType = detail.invoiceType;
        _receiptDate = detail.receiptDate ?? _receiptDate;
        _externalNoCtrl.text = detail.externalInvoiceNo ?? '';
        _customNoCtrl.text = detail.customInvoiceNo ?? '';
        _paidCtrl.text = detail.paidAmount.toString();
        _transportCtrl.text = detail.transportCost.toString();
        if (detail.supplierId != null &&
            _suppliers.any((s) => s.id == detail.supplierId)) {
          _supplierId = detail.supplierId;
        } else {
          final match =
              _suppliers.where((s) => s.name == detail.supplierName);
          if (match.isNotEmpty) _supplierId = match.first.id;
        }
        _lines = [
          for (final l in detail.lines)
            PurchaseLineDraft(
              categoryId: null,
              productName: l.productName,
              productUnit: l.productUnit,
              productQuantity: l.quantity,
              productPrice: l.price,
            ),
        ];
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    }
  }

  PurchaseCategoryOption? get _selectedCategory {
    if (_categoryId == null) return null;
    for (final c in _categories) {
      if (c.id == _categoryId) return c;
    }
    return null;
  }

  void _onCategoryChanged(String? id) {
    setState(() {
      _categoryId = id;
      final cat = _selectedCategory;
      if (cat != null) {
        final price = cat.averageUnitCost > 0
            ? cat.averageUnitCost
            : cat.unitPrice;
        _priceCtrl.text =
            price > 0 ? price.toStringAsFixed(4) : '0';
      }
    });
  }

  void _addLine() {
    final cat = _selectedCategory;
    if (cat == null) {
      setState(() => _error = 'اختر صنفاً من القائمة');
      return;
    }
    final qty = double.tryParse(_qtyCtrl.text.trim());
    final price = double.tryParse(_priceCtrl.text.trim());
    if (qty == null || qty <= 0 || price == null || price < 0) {
      setState(() => _error = 'أدخل كمية وسعراً صحيحين');
      return;
    }
    final original = cat.averageUnitCost > 0 ? cat.averageUnitCost : cat.unitPrice;
    final edited = (original - price).abs() > 0.0001;
    setState(() {
      _error = null;
      if (edited) _invoicePriceEdited = true;
      _lines.add(
        PurchaseLineDraft(
          categoryId: cat.id,
          productName: cat.name,
          productUnit: cat.unit,
          productQuantity: qty,
          productPrice: price,
          priceEdited: edited,
        ),
      );
      _categoryId = null;
      _qtyCtrl.text = '1';
      _priceCtrl.clear();
    });
  }

  List<PaymentSourceItem> get _sourcesForType {
    switch (_paymentType) {
      case 'safe':
        return _paymentSources.safes;
      case 'service_account':
        return _paymentSources.serviceAccounts;
      default:
        return _paymentSources.banks;
    }
  }

  Future<void> _pickDate() async {
    final initial = DateTime.tryParse(_receiptDate ?? '') ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2018),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      locale: const Locale('ar'),
    );
    if (picked == null) return;
    setState(() {
      _receiptDate =
          '${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
    });
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (_supplierId == null || _supplierId!.isEmpty) {
      setState(() => _error = 'يجب اختيار المورد من القائمة');
      return;
    }
    if (_receiptDate == null || _receiptDate!.isEmpty) {
      setState(() => _error = 'اختر تاريخ الاستلام');
      return;
    }
    if (_lines.isEmpty) {
      setState(() => _error = 'أضف بنداً واحداً على الأقل');
      return;
    }
    if (_paid > 0 && (_paymentSourceId == null || _paymentSourceId!.isEmpty)) {
      setState(() => _error = 'اختر مصدر الدفع للمبلغ المدفوع');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await PurchasesApi.instance.create(
        supplierId: _supplierId!,
        invoiceType: _invoiceType,
        receiptDate: _receiptDate!,
        products: _lines,
        totalPrice: _grandTotal,
        paidAmount: _paid,
        dueAmount: _due,
        transportCost: _transport,
        priceEdited: _invoicePriceEdited ? '1' : '0',
        paymentType: _paid > 0 ? _paymentType : null,
        bankId: _paid > 0 && _paymentType == 'bank' ? _paymentSourceId : null,
        safeId: _paid > 0 && _paymentType == 'safe' ? _paymentSourceId : null,
        serviceAccountId: _paid > 0 && _paymentType == 'service_account'
            ? _paymentSourceId
            : null,
        externalInvoiceNo: _externalNoCtrl.text,
        customInvoiceNo: _customNoCtrl.text,
        invoiceId: widget.invoiceId,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_isEdit ? 'تم تحديث الفاتورة' : 'تم حفظ الفاتورة'),
          behavior: SnackBarBehavior.floating,
        ),
      );
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = 'تعذر حفظ الفاتورة');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: Text(_isEdit ? 'تعديل فاتورة مشتريات' : 'إضافة فاتورة مشتريات'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : ListView(
                primary: true,
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                children: [
                  if (_error != null) ...[
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.danger.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(
                          color: AppColors.danger.withValues(alpha: 0.35),
                        ),
                      ),
                      child: Text(
                        _error!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: AppColors.danger,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                  _label('حالة الشراء'),
                  DropdownButtonFormField<String>(
                    value: _invoiceType,
                    decoration: _dec(),
                    items: [
                      for (final t in kPurchaseInvoiceTypes)
                        DropdownMenuItem(value: t, child: Text(t)),
                    ],
                    onChanged: (v) =>
                        setState(() => _invoiceType = v ?? _invoiceType),
                  ),
                  const SizedBox(height: 12),
                  _label('المورد *'),
                  DropdownButtonFormField<String>(
                    value: _supplierId,
                    decoration: _dec(hint: 'اختر المورد'),
                    items: [
                      for (final s in _suppliers)
                        DropdownMenuItem(
                          value: s.id,
                          child: Text(s.name, overflow: TextOverflow.ellipsis),
                        ),
                    ],
                    onChanged: (v) => setState(() => _supplierId = v),
                  ),
                  const SizedBox(height: 12),
                  _label('تاريخ الاستلام *'),
                  InkWell(
                    onTap: _pickDate,
                    borderRadius: BorderRadius.circular(10),
                    child: InputDecorator(
                      decoration: _dec(),
                      child: Text(_receiptDate ?? 'اختر التاريخ'),
                    ),
                  ),
                  const SizedBox(height: 12),
                  _label('رقم فاتورة المورد'),
                  TextField(
                    controller: _externalNoCtrl,
                    decoration: _dec(hint: 'اختياري'),
                  ),
                  const SizedBox(height: 12),
                  _label('رقم داخلي مخصص'),
                  TextField(
                    controller: _customNoCtrl,
                    decoration: _dec(hint: 'اختياري — تلقائي إن تُرك فارغاً'),
                  ),
                  const SizedBox(height: 18),
                  const Text(
                    'إضافة بند',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                      color: AppColors.navy,
                    ),
                  ),
                  const SizedBox(height: 8),
                  DropdownButtonFormField<String>(
                    value: _categoryId,
                    decoration: _dec(hint: 'اختر الصنف (مواد خام)'),
                    items: [
                      for (final c in _categories)
                        DropdownMenuItem(
                          value: c.id,
                          child: Text(
                            c.name,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                    ],
                    onChanged: _onCategoryChanged,
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _qtyCtrl,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                          ],
                          decoration: _dec(hint: 'الكمية'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: TextField(
                          controller: _priceCtrl,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          inputFormatters: [
                            FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                          ],
                          decoration: _dec(hint: 'السعر'),
                        ),
                      ),
                    ],
                  ),
                  if (_selectedCategory != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      'الوحدة: ${_selectedCategory!.unit}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: _addLine,
                    icon: const Icon(Icons.add),
                    label: const Text('إضافة للفاتورة'),
                  ),
                  const SizedBox(height: 12),
                  if (_lines.isEmpty)
                    const Text(
                      'لا توجد بنود بعد',
                      style: TextStyle(color: AppColors.textMuted),
                    )
                  else
                    for (var i = 0; i < _lines.length; i++) ...[
                      Container(
                        margin: const EdgeInsets.only(bottom: 8),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.surface,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.border),
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _lines[i].productName,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                  Text(
                                    '${Formatters.moneyPlain(_lines[i].productQuantity)} ${_lines[i].productUnit} × ${Formatters.moneyPlain(_lines[i].productPrice)} = ${Formatters.money(_lines[i].total)}',
                                    style: const TextStyle(
                                      fontSize: 12,
                                      color: AppColors.textSecondary,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            IconButton(
                              onPressed: () =>
                                  setState(() => _lines.removeAt(i)),
                              icon: const Icon(
                                Icons.delete_outline,
                                color: AppColors.danger,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  const SizedBox(height: 12),
                  _label('تكلفة النقل'),
                  TextField(
                    controller: _transportCtrl,
                    onChanged: (_) => setState(() {}),
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                    ],
                    decoration: _dec(hint: '0'),
                  ),
                  const SizedBox(height: 12),
                  _label('المبلغ المدفوع'),
                  TextField(
                    controller: _paidCtrl,
                    onChanged: (_) => setState(() {}),
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    inputFormatters: [
                      FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
                    ],
                    decoration: _dec(hint: '0'),
                  ),
                  if (_paid > 0) ...[
                    const SizedBox(height: 12),
                    _label('نوع الصرف'),
                    DropdownButtonFormField<String>(
                      value: _paymentType,
                      decoration: _dec(),
                      items: const [
                        DropdownMenuItem(value: 'bank', child: Text('بنك')),
                        DropdownMenuItem(value: 'safe', child: Text('خزينة')),
                        DropdownMenuItem(
                          value: 'service_account',
                          child: Text('حساب خدمي'),
                        ),
                      ],
                      onChanged: (v) => setState(() {
                        _paymentType = v ?? 'bank';
                        _paymentSourceId = null;
                      }),
                    ),
                    const SizedBox(height: 12),
                    _label('مصدر الدفع *'),
                    DropdownButtonFormField<String>(
                      value: _paymentSourceId,
                      decoration: _dec(hint: 'اختر المصدر'),
                      items: [
                        for (final s in _sourcesForType)
                          DropdownMenuItem(
                            value: s.id,
                            child: Text(
                              '${s.name} (${Formatters.moneyPlain(s.balance)})',
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                      ],
                      onChanged: (v) =>
                          setState(() => _paymentSourceId = v),
                    ),
                  ],
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.surface,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.border),
                    ),
                    child: Column(
                      children: [
                        _sumRow('إجمالي الأصناف', Formatters.money(_productsTotal)),
                        _sumRow('النقل', Formatters.money(_transport)),
                        _sumRow('الإجمالي', Formatters.money(_grandTotal)),
                        _sumRow('المدفوع', Formatters.money(_paid)),
                        _sumRow('المتبقي', Formatters.money(_due)),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  SizedBox(
                    height: 48,
                    child: FilledButton(
                      onPressed: _saving ? null : _submit,
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.primary,
                      ),
                      child: _saving
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : Text(
                              _isEdit ? 'حفظ التعديل' : 'حفظ الفاتورة',
                              style: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                    ),
                  ),
                ],
              ),
      ),
    );
  }

  Widget _label(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Text(
        text,
        style: const TextStyle(
          fontWeight: FontWeight.w700,
          color: AppColors.navy,
          fontSize: 13,
        ),
      ),
    );
  }

  Widget _sumRow(String k, String v) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(child: Text(k)),
          Text(v, style: const TextStyle(fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }

  InputDecoration _dec({String? hint}) {
    return InputDecoration(
      hintText: hint,
      filled: true,
      fillColor: AppColors.surface,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
    );
  }
}
