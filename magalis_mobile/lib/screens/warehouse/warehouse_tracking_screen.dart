import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../services/inventory_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';

/// تتبع حركة المخزن — mirrors Angular `warehousedetails`.
class WarehouseTrackingScreen extends StatefulWidget {
  const WarehouseTrackingScreen({super.key, required this.warehouse});

  final String warehouse;

  @override
  State<WarehouseTrackingScreen> createState() =>
      _WarehouseTrackingScreenState();
}

class _WarehouseTrackingScreenState extends State<WarehouseTrackingScreen> {
  List<WarehouseMovement> _items = [];
  int _total = 0;
  int _page = 1;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;
  String? _dateFilter;
  bool _isMaintenance = false;

  @override
  void initState() {
    super.initState();
    _load(reset: true);
  }

  Future<void> _load({bool reset = false}) async {
    if (reset) {
      setState(() {
        _loading = true;
        _error = null;
        _page = 1;
      });
    } else {
      setState(() => _loadingMore = true);
    }

    try {
      final page = await InventoryApi.instance.warehouseMovements(
        warehouse: widget.warehouse,
        page: reset ? 1 : _page,
        date: _dateFilter,
      );
      if (!mounted) return;
      final maint = page.items.any((e) => e.isMaintenance) ||
          widget.warehouse == 'مخزن صيانة';
      setState(() {
        if (reset) {
          _items = page.items;
        } else {
          _items = [..._items, ...page.items];
        }
        _total = page.total;
        _isMaintenance = maint;
        _loading = false;
        _loadingMore = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
        _loadingMore = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'تعذر تحميل حركة المخزن';
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _dateFilter != null
          ? DateTime.tryParse(_dateFilter!) ?? now
          : now,
      firstDate: DateTime(2020),
      lastDate: now.add(const Duration(days: 1)),
      locale: const Locale('ar'),
    );
    if (picked == null) return;
    setState(() {
      _dateFilter = DateFormat('yyyy-MM-dd').format(picked);
    });
    await _load(reset: true);
  }

  String _fmtDate(DateTime? d) {
    if (d == null) return '—';
    return DateFormat('dd/MM/yyyy').format(d.toLocal());
  }

  @override
  Widget build(BuildContext context) {
    final canLoadMore = _items.length < _total;

    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        title: Text('تتبع (${widget.warehouse})'),
        actions: [
          if (_dateFilter != null)
            IconButton(
              tooltip: 'مسح التاريخ',
              onPressed: () {
                setState(() => _dateFilter = null);
                _load(reset: true);
              },
              icon: const Icon(Icons.clear),
            ),
          IconButton(
            tooltip: 'تصفية بالتاريخ',
            onPressed: _pickDate,
            icon: const Icon(Icons.calendar_month_outlined),
          ),
          IconButton(
            onPressed: _loading ? null : () => _load(reset: true),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          _error!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: AppColors.danger,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 12),
                        ElevatedButton(
                          onPressed: () => _load(reset: true),
                          child: const Text('إعادة المحاولة'),
                        ),
                      ],
                    ),
                  ),
                )
              : Column(
                  children: [
                    if (_dateFilter != null)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                        child: Align(
                          alignment: AlignmentDirectional.centerStart,
                          child: Text(
                            'التاريخ: $_dateFilter · العدد: $_total',
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                              fontWeight: FontWeight.w700,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      )
                    else
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                        child: Align(
                          alignment: AlignmentDirectional.centerStart,
                          child: Text(
                            'العدد: $_total',
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                              fontWeight: FontWeight.w700,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      ),
                    const SizedBox(height: 6),
                    Expanded(
                      child: _items.isEmpty
                          ? const Center(
                              child: Text(
                                'لا توجد حركات',
                                style: TextStyle(
                                  color: AppColors.textMuted,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            )
                          : RefreshIndicator(
                              onRefresh: () => _load(reset: true),
                              child: ListView.separated(
                                padding:
                                    const EdgeInsets.fromLTRB(16, 8, 16, 24),
                                itemCount:
                                    _items.length + (canLoadMore ? 1 : 0),
                                separatorBuilder: (_, __) =>
                                    const SizedBox(height: 10),
                                itemBuilder: (context, i) {
                                  if (canLoadMore && i == _items.length) {
                                    return TextButton(
                                      onPressed: _loadingMore
                                          ? null
                                          : () {
                                              _page += 1;
                                              _load();
                                            },
                                      child: Text(
                                        _loadingMore
                                            ? 'جاري التحميل…'
                                            : 'تحميل المزيد',
                                      ),
                                    );
                                  }
                                  final m = _items[i];
                                  return _MovementCard(
                                    movement: m,
                                    isMaintenance: _isMaintenance,
                                    dateLabel: _fmtDate(m.movementDate),
                                  );
                                },
                              ),
                            ),
                    ),
                  ],
                ),
    );
  }
}

class _MovementCard extends StatelessWidget {
  const _MovementCard({
    required this.movement,
    required this.isMaintenance,
    required this.dateLabel,
  });

  final WarehouseMovement movement;
  final bool isMaintenance;
  final String dateLabel;

  @override
  Widget build(BuildContext context) {
    final m = movement;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            m.categoryName,
            style: const TextStyle(
              fontWeight: FontWeight.w800,
              color: AppColors.navy,
            ),
          ),
          const SizedBox(height: 6),
          Wrap(
            spacing: 10,
            runSpacing: 4,
            children: [
              if (isMaintenance)
                _chip('طلب: ${m.ref ?? '—'}')
              else if (m.type.isNotEmpty)
                _chip(m.type),
              _chip('الكمية: ${Formatters.moneyPlain(m.quantity)}'),
              if (isMaintenance && m.status != null) _chip(m.status!),
              if (!isMaintenance && m.balanceBefore != null)
                _chip('قبل: ${Formatters.moneyPlain(m.balanceBefore!)}'),
              if (!isMaintenance && m.balanceAfter != null)
                _chip('بعد: ${Formatters.moneyPlain(m.balanceAfter!)}'),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            [
              dateLabel,
              if (!isMaintenance && m.partyName != null) m.partyName!,
              if (!isMaintenance && m.invoiceNumber != null)
                'مرجع: ${m.invoiceNumber}',
              if (m.by != null) 'بواسطة: ${m.by}',
            ].where((e) => e.isNotEmpty).join(' · '),
            style: const TextStyle(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
              fontSize: 12,
            ),
          ),
          if (!isMaintenance && m.totalPrice != null) ...[
            const SizedBox(height: 4),
            Text(
              'الإجمالي: ${Formatters.moneyPlain(m.totalPrice!)}'
              '${m.price != null ? ' · تكلفة الوحدة: ${Formatters.moneyPlain(m.price!)}' : ''}',
              style: const TextStyle(
                color: AppColors.primary,
                fontWeight: FontWeight.w800,
                fontSize: 12,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _chip(String text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: AppColors.primaryBg,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        text,
        style: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: AppColors.primary,
        ),
      ),
    );
  }
}
