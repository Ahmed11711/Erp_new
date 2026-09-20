import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/purchases_api.dart';
import '../../services/suppliers_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import 'add_purchase_invoice_screen.dart';
import 'purchase_details_screen.dart';

/// قائمة فواتير المشتريات — mirrors Angular `ListInvoiceComponent`.
class PurchasesListScreen extends StatefulWidget {
  const PurchasesListScreen({super.key, this.title = 'المشتريات'});

  final String title;

  @override
  State<PurchasesListScreen> createState() => _PurchasesListScreenState();
}

class _PurchasesListScreenState extends State<PurchasesListScreen> {
  final _searchCtrl = TextEditingController();
  Timer? _debounce;

  List<PurchaseInvoiceSummary> _items = [];
  List<SupplierTypeOption> _suppliers = [];
  String _supplierId = '';
  String _invoiceType = '';
  int _total = 0;
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
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    try {
      final names = await SuppliersApi.instance.supplierNames();
      if (mounted) setState(() => _suppliers = names);
    } catch (_) {/* optional */}
    await _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await PurchasesApi.instance.search(
        q: _searchCtrl.text,
        supplierId: _supplierId.isEmpty ? null : _supplierId,
        invoiceType: _invoiceType.isEmpty ? null : _invoiceType,
      );
      if (!mounted) return;
      setState(() {
        _items = page.items;
        _total = page.total;
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
        _error = 'تعذر تحميل فواتير المشتريات';
        _loading = false;
      });
    }
  }

  void _onSearch(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), _load);
  }

  void _openAdd() {
    Navigator.of(context)
        .push(
          MaterialPageRoute(builder: (_) => const AddPurchaseInvoiceScreen()),
        )
        .then((_) => _load());
  }

  void _openDetails(PurchaseInvoiceSummary inv) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => PurchaseDetailsScreen(purchaseId: inv.id),
      ),
    );
  }

  void _openEdit(PurchaseInvoiceSummary inv) {
    Navigator.of(context)
        .push(
          MaterialPageRoute(
            builder: (_) => AddPurchaseInvoiceScreen(invoiceId: inv.id),
          ),
        )
        .then((_) => _load());
  }

  Future<void> _delete(PurchaseInvoiceSummary inv) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('حذف الفاتورة؟'),
        content: Text('سيتم حذف «${inv.invoiceNumber}».'),
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
      await PurchasesApi.instance.delete(inv.id);
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

  void _showActions(PurchaseInvoiceSummary inv) {
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: Text(
                inv.invoiceNumber,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(inv.supplierName),
            ),
            const Divider(height: 1),
            ListTile(
              leading: const Icon(Icons.visibility_outlined),
              title: const Text('التفاصيل'),
              onTap: () {
                Navigator.pop(ctx);
                _openDetails(inv);
              },
            ),
            if (!inv.isDeleted)
              ListTile(
                leading: const Icon(Icons.edit_outlined),
                title: const Text('تعديل'),
                onTap: () {
                  Navigator.pop(ctx);
                  _openEdit(inv);
                },
              ),
            if (!inv.isDeleted)
              ListTile(
                leading:
                    const Icon(Icons.delete_outline, color: AppColors.danger),
                title: const Text(
                  'حذف',
                  style: TextStyle(color: AppColors.danger),
                ),
                onTap: () {
                  Navigator.pop(ctx);
                  _delete(inv);
                },
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: Text(widget.title),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
          actions: [
            IconButton(
              tooltip: 'إضافة فاتورة',
              onPressed: _openAdd,
              icon: const Icon(Icons.add_circle_outline),
            ),
          ],
        ),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: _openAdd,
          backgroundColor: AppColors.primary,
          icon: const Icon(Icons.add, color: Colors.white),
          label: const Text(
            'فاتورة جديدة',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
          ),
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
                    controller: _searchCtrl,
                    onChanged: _onSearch,
                    decoration: InputDecoration(
                      hintText: 'بحث برقم الفاتورة / المورد...',
                      prefixIcon: const Icon(Icons.search, size: 20),
                      isDense: true,
                      filled: true,
                      fillColor: AppColors.bg,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppColors.border),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          value: _supplierId,
                          isDense: true,
                          decoration: _dd('المورد'),
                          items: [
                            const DropdownMenuItem(
                              value: '',
                              child: Text('كل الموردين'),
                            ),
                            for (final s in _suppliers)
                              DropdownMenuItem(
                                value: s.id,
                                child: Text(
                                  s.name,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                          ],
                          onChanged: (v) {
                            setState(() => _supplierId = v ?? '');
                            _load();
                          },
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          value: _invoiceType,
                          isDense: true,
                          decoration: _dd('النوع'),
                          items: [
                            const DropdownMenuItem(
                              value: '',
                              child: Text('كل الأنواع'),
                            ),
                            for (final t in kPurchaseInvoiceTypes)
                              DropdownMenuItem(value: t, child: Text(t)),
                          ],
                          onChanged: (v) {
                            setState(() => _invoiceType = v ?? '');
                            _load();
                          },
                        ),
                      ),
                    ],
                  ),
                  if (_total > 0) ...[
                    const SizedBox(height: 6),
                    Align(
                      alignment: Alignment.centerRight,
                      child: Text(
                        '$_total فاتورة',
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
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

  InputDecoration _dd(String label) {
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
            Icon(Icons.receipt_long_outlined, size: 48, color: AppColors.textMuted),
            SizedBox(height: 12),
            Center(
              child: Text(
                'لا توجد فواتير',
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
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 96),
        itemCount: _items.length,
        separatorBuilder: (context, index) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final inv = _items[index];
          return Material(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () => _showActions(inv),
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: inv.isDeleted
                        ? AppColors.danger.withValues(alpha: 0.35)
                        : AppColors.border,
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            inv.invoiceNumber,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 15,
                              color: AppColors.navy,
                            ),
                          ),
                        ),
                        IconButton(
                          onPressed: () => _showActions(inv),
                          icon: const Icon(Icons.more_vert),
                        ),
                      ],
                    ),
                    Text(
                      inv.supplierName,
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      [
                        inv.invoiceType,
                        if (inv.receiptDate != null) inv.receiptDate!,
                      ].join(' · '),
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Text(
                          Formatters.money(inv.totalPrice),
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            color: AppColors.primary,
                          ),
                        ),
                        const Spacer(),
                        Text(
                          'مدفوع ${Formatters.moneyPlain(inv.paidAmount)} · متبقي ${Formatters.moneyPlain(inv.dueAmount)}',
                          style: const TextStyle(
                            fontSize: 11,
                            color: AppColors.textSecondary,
                          ),
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
