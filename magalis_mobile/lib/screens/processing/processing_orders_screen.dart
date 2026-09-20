import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../services/processing_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/status_chip.dart';
import 'processing_order_detail_screen.dart';
import 'processing_dispatch_form_screen.dart';

/// إذونات الصرف والاستلام — mirrors Angular `ProcessingOrdersComponent`.
class ProcessingOrdersScreen extends StatefulWidget {
  const ProcessingOrdersScreen({super.key});

  @override
  State<ProcessingOrdersScreen> createState() => _ProcessingOrdersScreenState();
}

class _ProcessingOrdersScreenState extends State<ProcessingOrdersScreen> {
  final _searchCtrl = TextEditingController();
  Timer? _debounce;

  List<ProcessingOrderSummary> _items = [];
  ProcessingMeta _meta = ProcessingMeta.fromJson({});
  String _status = '';
  int _total = 0;
  bool _loading = true;
  String? _error;

  static const _fallbackStatuses = <String, String>{
    'draft': 'مسودة',
    'approved': 'معتمد',
    'in_progress': 'قيد التشغيل',
    'partially_received': 'استلام جزئي',
    'completed': 'مكتمل',
    'cancelled': 'ملغى',
  };

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
      final meta = await ProcessingApi.instance.meta();
      if (mounted) setState(() => _meta = meta);
    } catch (_) {
      /* use fallback labels */
    }
    await _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final page = await ProcessingApi.instance.listOrders(
        q: _searchCtrl.text,
        status: _status.isEmpty ? null : _status,
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
        _error = 'تعذر تحميل إذونات الصرف';
        _loading = false;
      });
    }
  }

  void _onSearch(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), _load);
  }

  String _statusLabel(String code) {
    if (_meta.orderStatuses.containsKey(code)) {
      return _meta.orderStatuses[code]!;
    }
    return _fallbackStatuses[code] ?? code;
  }

  Color _statusColor(String code) {
    switch (code) {
      case 'completed':
        return AppColors.success;
      case 'partially_received':
      case 'in_progress':
        return AppColors.info;
      case 'approved':
        return AppColors.navy;
      case 'cancelled':
        return AppColors.danger;
      case 'draft':
      default:
        return AppColors.textMuted;
    }
  }

  void _openCreate() {
    Navigator.of(context)
        .push(
          MaterialPageRoute(
            builder: (_) => const ProcessingDispatchFormScreen(),
          ),
        )
        .then((_) {
      if (mounted) _load();
    });
  }

  void _openDetail(ProcessingOrderSummary o) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ProcessingOrderDetailScreen(
          orderId: o.id,
          statusLabels: {
            ..._fallbackStatuses,
            ..._meta.orderStatuses,
          },
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final statuses = _meta.orderStatuses.isEmpty
        ? _fallbackStatuses
        : _meta.orderStatuses;

    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('إذونات الصرف والاستلام'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: _openCreate,
          backgroundColor: AppColors.primary,
          icon: const Icon(Icons.add),
          label: const Text('إذن صرف جديد'),
        ),
        body: Column(
          children: [
            Container(
              width: double.infinity,
              color: AppColors.surface,
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextField(
                    controller: _searchCtrl,
                    onChanged: _onSearch,
                    decoration: InputDecoration(
                      hintText: 'بحث برقم الإذن / المورد...',
                      prefixIcon: const Icon(Icons.search, size: 20),
                      isDense: true,
                      filled: true,
                      fillColor: AppColors.bg,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppColors.border),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppColors.border),
                      ),
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 10,
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  DropdownButtonFormField<String>(
                    value: _status,
                    decoration: InputDecoration(
                      labelText: 'الحالة',
                      isDense: true,
                      filled: true,
                      fillColor: AppColors.bg,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 8,
                      ),
                    ),
                    items: [
                      const DropdownMenuItem(value: '', child: Text('الكل')),
                      for (final e in statuses.entries)
                        DropdownMenuItem(value: e.key, child: Text(e.value)),
                    ],
                    onChanged: (v) {
                      setState(() => _status = v ?? '');
                      _load();
                    },
                  ),
                  if (_total > 0) ...[
                    const SizedBox(height: 6),
                    Text(
                      '$_total إذن',
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w600,
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
          children: [
            const SizedBox(height: 80),
            const Icon(Icons.assignment_outlined, size: 48, color: AppColors.textMuted),
            const SizedBox(height: 12),
            Center(
              child: Text(
                _searchCtrl.text.trim().isNotEmpty || _status.isNotEmpty
                    ? 'لا توجد إذونات مطابقة'
                    : 'لا توجد إذونات صرف بعد. اضغط «إذن صرف جديد» للبدء.',
                textAlign: TextAlign.center,
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
        primary: true,
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 88),
        itemCount: _items.length,
        separatorBuilder: (context, index) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final o = _items[index];
          final date = o.dispatchDate ?? o.createdAt ?? '';
          final dateLabel = date.length >= 10 ? date.substring(0, 10) : date;
          return Material(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () => _openDetail(o),
              child: Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            o.dispatchNumber,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 16,
                              color: AppColors.navy,
                            ),
                          ),
                        ),
                        StatusChip(
                          label: _statusLabel(o.status),
                          color: _statusColor(o.status),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      o.supplierName,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        color: AppColors.text,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        if (dateLabel.isNotEmpty)
                          Text(
                            dateLabel,
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        const Spacer(),
                        Text(
                          Formatters.money(o.amount),
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            color: AppColors.primary,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'تفاصيل / استلام',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: AppColors.navy.withValues(alpha: 0.8),
                      ),
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
