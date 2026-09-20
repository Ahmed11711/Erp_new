import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/vouchers_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/page_bar.dart';
import 'voucher_form_dialog.dart';

/// سندات العملاء / الموردين — Angular `VouchersComponent`.
class VouchersListScreen extends StatefulWidget {
  const VouchersListScreen({super.key, required this.voucherType});

  final String voucherType;

  @override
  State<VouchersListScreen> createState() => _VouchersListScreenState();
}

class _VouchersListScreenState extends State<VouchersListScreen> {
  final _searchCtrl = TextEditingController();
  List<VoucherItem> _all = [];
  int _total = 0;
  int _page = 1;
  int _pageSize = 25;
  bool _loading = true;
  String? _error;

  bool get _isClient => widget.voucherType == 'client';
  String get _title => _isClient ? 'سندات العملاء' : 'سندات الموردين';

  List<VoucherItem> get _filtered {
    final q = _searchCtrl.text.trim().toLowerCase();
    if (q.isEmpty) return _all;
    return _all.where((v) {
      final hay = [
        '${v.id}',
        v.referenceNumber,
        v.partyName,
        v.notes,
      ].map((s) => (s ?? '').toLowerCase()).join(' ');
      return hay.contains(q);
    }).toList();
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await VouchersApi.instance.list(
        voucherType: widget.voucherType,
        page: _page,
        perPage: _pageSize,
      );
      if (!mounted) return;
      setState(() {
        _all = page.items;
        _total = page.total;
        _pageSize = page.perPage;
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
        _error = 'تعذر تحميل السندات';
        _loading = false;
      });
    }
  }

  Future<void> _openForm({VoucherItem? voucher}) async {
    final ok = await VoucherFormDialog.open(
      context,
      voucherType: widget.voucherType,
      voucher: voucher,
    );
    if (ok) await _load();
  }

  void _printStub() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('جاري الطباعة... (تحتاج لصفحة طباعة)'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  void _showActions(VoucherItem v) {
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: Text(
                v.partyName ?? 'سند #${v.id}',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(
                '${v.typeLabel} · ${Formatters.money(v.amount)}',
              ),
            ),
            const Divider(height: 1),
            ListTile(
              leading: const Icon(Icons.visibility_outlined),
              title: const Text('عرض التفاصيل'),
              onTap: () {
                Navigator.pop(ctx);
                _openForm(voucher: v);
              },
            ),
            ListTile(
              leading: const Icon(Icons.edit_outlined),
              title: const Text('تعديل'),
              onTap: () {
                Navigator.pop(ctx);
                _openForm(voucher: v);
              },
            ),
            ListTile(
              leading: const Icon(Icons.print_outlined),
              title: const Text('طباعة'),
              onTap: () {
                Navigator.pop(ctx);
                _printStub();
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
          title: Text(_title),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
          actions: [
            IconButton(
              tooltip: 'تحديث',
              onPressed: _loading ? null : _load,
              icon: const Icon(Icons.refresh),
            ),
          ],
        ),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: () => _openForm(),
          icon: const Icon(Icons.add),
          label: const Text('إضافة سند'),
        ),
        body: Column(
          children: [
            Container(
              width: double.infinity,
              color: AppColors.surface,
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              child: TextField(
                controller: _searchCtrl,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  hintText: 'بحث برقم السند، الاسم أو الملاحظات...',
                  prefixIcon: const Icon(Icons.search, size: 20),
                  isDense: true,
                  filled: true,
                  fillColor: AppColors.bg,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ),
            Expanded(child: _buildBody()),
            if (!_loading && _error == null)
              PageBar(
                page: _page,
                pageSize: _pageSize,
                total: _total,
                pageSizeOptions: const [25, 50, 100],
                label: '$_total سند',
                onPageChanged: (p) {
                  setState(() => _page = p);
                  _load();
                },
                onPageSizeChanged: (s) {
                  setState(() {
                    _pageSize = s;
                    _page = 1;
                  });
                  _load();
                },
              ),
          ],
        ),
      ),
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
    final items = _filtered;
    if (items.isEmpty) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            const SizedBox(height: 120),
            const Icon(
              Icons.receipt_long_outlined,
              size: 48,
              color: AppColors.textMuted,
            ),
            const SizedBox(height: 12),
            Center(
              child: Text(
                _all.isEmpty ? 'لا توجد سندات' : 'لا توجد سندات تطابق بحثك',
                style: const TextStyle(color: AppColors.textSecondary),
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 88),
        itemCount: items.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final v = items[index];
          return Material(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () => _showActions(v),
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            v.partyName ?? (_isClient ? 'عميل' : 'مورد'),
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 15,
                              color: AppColors.navy,
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: v.isReceipt
                                ? AppColors.success.withValues(alpha: 0.12)
                                : AppColors.danger.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            v.typeLabel,
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              color: v.isReceipt
                                  ? AppColors.success
                                  : AppColors.danger,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      [
                        '#${v.id}',
                        if (v.referenceNumber != null) v.referenceNumber!,
                        v.dateLabel,
                      ].join(' · '),
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                    if (v.notes != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        v.notes!,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.textMuted,
                        ),
                      ),
                    ],
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Text(
                          Formatters.money(v.amount),
                          textDirection: TextDirection.ltr,
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        const Spacer(),
                        Text(
                          v.userName ?? 'غير معروف',
                          style: const TextStyle(
                            fontSize: 12,
                            color: AppColors.textSecondary,
                          ),
                        ),
                        IconButton(
                          onPressed: () => _showActions(v),
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
