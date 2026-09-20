import 'dart:async';

import 'package:flutter/material.dart';

import '../../config/api_config.dart';
import '../../core/api_client.dart';
import '../../services/inventory_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';

/// تقرير المخزون — mirrors Angular `StorageComponent` (`/dashboard/reports/storage`).
class StorageReportScreen extends StatefulWidget {
  const StorageReportScreen({super.key});

  @override
  State<StorageReportScreen> createState() => _StorageReportScreenState();
}

class _StorageReportScreenState extends State<StorageReportScreen> {
  final _searchCtrl = TextEditingController();
  final _listFocus = FocusNode(debugLabel: 'storageReportList');
  final _expanded = <int>{};
  final _imageFailed = <int>{};

  String _warehouse = 'مخزن منتج تام';
  String? _dateFrom;
  String? _dateTo;
  String _sortField = 'total_value';
  int _page = 1;
  int _pageSize = 15;
  bool _showFilters = true;
  bool _loading = true;
  String? _error;

  List<WarehouseInventoryLine> _items = [];
  int _total = 0;
  WarehouseInventoryTotals _totals = const WarehouseInventoryTotals();
  Timer? _debounce;

  static const _pageSizeOptions = [15, 50, 100];

  bool get _isFinished => _warehouse == 'مخزن منتج تام';

  String get _primaryValueLabel =>
      _isFinished ? 'قيمة البيع' : 'قيمة التكلفة';

  double get _primaryValueTotal =>
      _isFinished ? _totals.totalSellValue : _totals.totalValue;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _dateFrom =
        '${now.year}-${now.month.toString().padLeft(2, '0')}-01';
    _dateTo =
        '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtrl.dispose();
    _listFocus.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final report = await InventoryApi.instance.warehouseInventoryReport(
        warehouse: _warehouse,
        dateFrom: _dateFrom,
        dateTo: _dateTo,
        sort: _sortField,
        search: _searchCtrl.text,
        page: _page,
        itemsPerPage: _pageSize,
      );
      if (!mounted) return;
      setState(() {
        _items = report.items;
        _total = report.total;
        _totals = report.totals;
        _expanded.clear();
        _imageFailed.clear();
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _items = [];
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _items = [];
        _error =
            'تعذر تحميل التقرير. تحقق من التواريخ والاتصال بالخادم.';
        _loading = false;
      });
    }
  }

  void _reloadFromStart() {
    _page = 1;
    _load();
  }

  void _onSearchChanged(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), _reloadFromStart);
  }

  Future<void> _pickDate({required bool from}) async {
    final initial = _parseDate(from ? _dateFrom : _dateTo) ?? DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2018),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      locale: const Locale('ar'),
    );
    if (picked == null) return;
    final formatted =
        '${picked.year}-${picked.month.toString().padLeft(2, '0')}-${picked.day.toString().padLeft(2, '0')}';
    setState(() {
      if (from) {
        _dateFrom = formatted;
      } else {
        _dateTo = formatted;
      }
    });
    _reloadFromStart();
  }

  DateTime? _parseDate(String? v) {
    if (v == null || v.isEmpty) return null;
    return DateTime.tryParse(v);
  }

  double _itemPrimaryValue(WarehouseInventoryLine row) =>
      _isFinished ? row.sellValue : row.totalValue;

  void _toggleExpand(int index) {
    setState(() {
      if (_expanded.contains(index)) {
        _expanded.remove(index);
      } else {
        _expanded.add(index);
      }
    });
  }

  int get _lastPage {
    if (_total <= 0) return 1;
    return ((_total - 1) ~/ _pageSize) + 1;
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: AppColors.bg,
        appBar: AppBar(
          title: const Text('تقرير المخزون'),
          backgroundColor: AppColors.navy,
          foregroundColor: Colors.white,
        ),
        body: Column(
          children: [
            _buildFilterBar(),
            Expanded(child: _buildBody()),
            if (!_loading && _error == null && _items.isNotEmpty)
              _buildPaginator(),
          ],
        ),
      ),
    );
  }

  Widget _buildFilterBar() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
      color: AppColors.surface,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              ActionChip(
                avatar: Icon(
                  _showFilters ? Icons.tune : Icons.tune_outlined,
                  size: 18,
                  color: AppColors.primary,
                ),
                label: const Text('فلترة'),
                onPressed: () =>
                    setState(() => _showFilters = !_showFilters),
              ),
              if (_showFilters) ...[
                _filterChip(
                  child: DropdownButtonHideUnderline(
                    child: DropdownButton<String>(
                      value: _warehouse,
                      isDense: true,
                      items: [
                        for (final w in kWarehouseReportOptions)
                          DropdownMenuItem(
                            value: w.value,
                            child: Text(w.label, style: const TextStyle(fontSize: 13)),
                          ),
                      ],
                      onChanged: (v) {
                        if (v == null) return;
                        setState(() {
                          _warehouse = v;
                          if (!_isFinished && _sortField == 'sell_value') {
                            _sortField = 'total_value';
                          }
                        });
                        _reloadFromStart();
                      },
                    ),
                  ),
                ),
                _dateChip(
                  label: 'من',
                  value: _dateFrom,
                  onTap: () => _pickDate(from: true),
                ),
                _dateChip(
                  label: 'إلى',
                  value: _dateTo,
                  onTap: () => _pickDate(from: false),
                ),
                SizedBox(
                  width: 180,
                  child: TextField(
                    controller: _searchCtrl,
                    onChanged: _onSearchChanged,
                    decoration: InputDecoration(
                      hintText: 'بحث باسم الصنف...',
                      isDense: true,
                      prefixIcon: const Icon(Icons.search, size: 18),
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 8,
                      ),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(20),
                        borderSide: const BorderSide(color: AppColors.border),
                      ),
                      filled: true,
                      fillColor: AppColors.bg,
                    ),
                  ),
                ),
                FilledButton.icon(
                  onPressed: _loading ? null : _load,
                  icon: const Icon(Icons.refresh, size: 18),
                  label: const Text('تحديث'),
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    visualDensity: VisualDensity.compact,
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }

  Widget _filterChip({required Widget child}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
      decoration: BoxDecoration(
        color: AppColors.bg,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.border),
      ),
      child: child,
    );
  }

  Widget _dateChip({
    required String label,
    required String? value,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: AppColors.bg,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: AppColors.border),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.calendar_today_outlined, size: 14),
            const SizedBox(width: 6),
            Text(
              '$label: ${value ?? '—'}',
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
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
              const Icon(Icons.error_outline, color: AppColors.danger, size: 40),
              const SizedBox(height: 12),
              Text(
                _error!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.danger),
              ),
              const SizedBox(height: 16),
              FilledButton(onPressed: _load, child: const Text('إعادة المحاولة')),
            ],
          ),
        ),
      );
    }
    if (_items.isEmpty) {
      return const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.folder_open_outlined, size: 48, color: AppColors.textMuted),
            SizedBox(height: 12),
            Text(
              'لا توجد بيانات لهذا المخزن أو الفترة المحددة.',
              style: TextStyle(color: AppColors.textSecondary),
            ),
          ],
        ),
      );
    }

    // Web/desktop: arrow keys scroll only via PrimaryScrollController.
    // ListView must be `primary: true` (Flutter does not auto-inherit on web).
    return Focus(
      autofocus: true,
      focusNode: _listFocus,
      child: GestureDetector(
        behavior: HitTestBehavior.translucent,
        onTap: () => _listFocus.requestFocus(),
        child: RefreshIndicator(
          onRefresh: _load,
          child: ListView(
            primary: true,
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 16),
            children: [
              _buildSummaryGrid(),
              const SizedBox(height: 12),
              _buildSortBar(),
              const SizedBox(height: 8),
              for (var i = 0; i < _items.length; i++) ...[
                _buildItemCard(i, _items[i]),
                const SizedBox(height: 10),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSummaryGrid() {
    return LayoutBuilder(
      builder: (context, constraints) {
        final wide = constraints.maxWidth >= 520;
        final cards = [
          _summaryCard(
            icon: Icons.inventory_2_outlined,
            label: 'عدد الأصناف',
            value: Formatters.moneyPlain(_totals.itemsCount),
            color: AppColors.navy,
          ),
          _summaryCard(
            icon: Icons.grid_view_rounded,
            label: 'إجمالي الكمية (رصيد آخر)',
            value: Formatters.moneyPlain(_totals.totalQuantity),
            color: AppColors.info,
          ),
          _summaryCard(
            icon: Icons.payments_outlined,
            label: _primaryValueLabel,
            value: Formatters.moneyPlain(_primaryValueTotal),
            unit: 'EGP',
            color: AppColors.warning,
          ),
          _summaryCard(
            icon: Icons.swap_horiz_rounded,
            label: 'وارد / صادر (الفترة)',
            value:
                '${Formatters.moneyPlain(_totals.periodInTotal)} / ${Formatters.moneyPlain(_totals.periodOutTotal)}',
            color: AppColors.success,
          ),
        ];
        if (wide) {
          return Row(
            children: [
              for (var i = 0; i < cards.length; i++) ...[
                if (i > 0) const SizedBox(width: 8),
                Expanded(child: cards[i]),
              ],
            ],
          );
        }
        return Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final c in cards)
              SizedBox(
                width: (constraints.maxWidth - 8) / 2,
                child: c,
              ),
          ],
        );
      },
    );
  }

  Widget _summaryCard({
    required IconData icon,
    required String label,
    required String value,
    required Color color,
    String? unit,
  }) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: color, size: 18),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: AppColors.navy,
            ),
          ),
          if (unit != null)
            Text(
              unit,
              style: const TextStyle(fontSize: 11, color: AppColors.textMuted),
            ),
        ],
      ),
    );
  }

  Widget _buildSortBar() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          const Icon(Icons.sort_rounded, size: 18, color: AppColors.textSecondary),
          const SizedBox(width: 8),
          Expanded(
            child: DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                value: _sortField,
                isExpanded: true,
                items: [
                  const DropdownMenuItem(
                    value: 'quantity',
                    child: Text('ترتيب حسب أعلى كمية'),
                  ),
                  const DropdownMenuItem(
                    value: 'total_value',
                    child: Text('ترتيب حسب أعلى قيمة تكلفة'),
                  ),
                  if (_isFinished)
                    const DropdownMenuItem(
                      value: 'sell_value',
                      child: Text('ترتيب حسب أعلى قيمة بيع'),
                    ),
                  const DropdownMenuItem(
                    value: 'period_in_qty',
                    child: Text('ترتيب حسب أعلى وارد (الفترة)'),
                  ),
                  const DropdownMenuItem(
                    value: 'period_out_qty',
                    child: Text('ترتيب حسب أعلى صادر (الفترة)'),
                  ),
                  const DropdownMenuItem(
                    value: 'period_net_qty',
                    child: Text('ترتيب حسب صافي الحركة'),
                  ),
                  const DropdownMenuItem(
                    value: 'category_name',
                    child: Text('ترتيب حسب اسم الصنف'),
                  ),
                ],
                onChanged: (v) {
                  if (v == null) return;
                  setState(() => _sortField = v);
                  _reloadFromStart();
                },
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildItemCard(int index, WarehouseInventoryLine row) {
    final expanded = _expanded.contains(index);
    final imgBroken = _imageFailed.contains(index);
    final hasImage =
        row.categoryImage != null && row.categoryImage!.isNotEmpty && !imgBroken;

    return Container(
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: expanded ? AppColors.primary.withValues(alpha: 0.35) : AppColors.border,
        ),
      ),
      child: Column(
        children: [
          InkWell(
            onTap: () => _toggleExpand(index),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(10, 10, 10, 6),
              child: Row(
                children: [
                  Icon(
                    expanded ? Icons.expand_less : Icons.expand_more,
                    color: AppColors.primary,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      row.categoryName,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                        color: AppColors.navy,
                      ),
                    ),
                  ),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: SizedBox(
                      width: 44,
                      height: 44,
                      child: hasImage
                          ? Image.network(
                              '${ApiConfig.imgUrl}${row.categoryImage}',
                              fit: BoxFit.cover,
                              errorBuilder: (context, error, stackTrace) {
                                WidgetsBinding.instance.addPostFrameCallback((_) {
                                  if (mounted) {
                                    setState(() => _imageFailed.add(index));
                                  }
                                });
                                return _thumbFallback();
                              },
                            )
                          : _thumbFallback(),
                    ),
                  ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(10, 0, 10, 8),
            child: Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                _statChip('الكمية (رصيد آخر)', Formatters.moneyPlain(row.quantity)),
                _statChip(
                  _primaryValueLabel,
                  Formatters.moneyPlain(_itemPrimaryValue(row)),
                  unit: 'EGP',
                ),
                _statChip('وارد الفترة', Formatters.moneyPlain(row.periodInQty)),
                _statChip('صادر الفترة', Formatters.moneyPlain(row.periodOutQty)),
                _statChip('رصيد أول', Formatters.moneyPlain(row.openingQty)),
              ],
            ),
          ),
          TextButton.icon(
            onPressed: () => _toggleExpand(index),
            icon: Icon(
              expanded ? Icons.expand_less : Icons.expand_more,
              size: 18,
            ),
            label: Text(expanded ? 'إخفاء التفاصيل' : 'عرض التفاصيل'),
          ),
          if (expanded)
            Container(
              width: double.infinity,
              margin: const EdgeInsets.fromLTRB(10, 0, 10, 12),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppColors.bg,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Column(
                children: [
                  _detailRow('وحدة القياس', row.measurementUnit),
                  _detailRow(
                    'تكلفة الوحدة',
                    '${Formatters.moneyPlain(row.unitCost)} EGP',
                  ),
                  _detailRow(
                    'قيمة التكلفة',
                    '${Formatters.moneyPlain(row.totalValue)} EGP',
                  ),
                  if (_isFinished)
                    _detailRow(
                      'قيمة البيع',
                      '${Formatters.moneyPlain(row.sellValue)} EGP',
                      highlight: true,
                    ),
                  _detailRow(
                    'صافي الحركة',
                    Formatters.moneyPlain(row.periodNetQty),
                  ),
                  _detailRow('المخزن', _warehouse),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _thumbFallback() {
    return Container(
      color: AppColors.primaryBg,
      child: const Icon(Icons.inventory_2_outlined, color: AppColors.primary),
    );
  }

  Widget _statChip(String label, String value, {String? unit}) {
    return Container(
      width: 110,
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
      decoration: BoxDecoration(
        color: AppColors.bg,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(fontSize: 10, color: AppColors.textSecondary),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              fontWeight: FontWeight.w800,
              fontSize: 13,
              color: AppColors.navy,
            ),
          ),
          if (unit != null)
            Text(
              unit,
              style: const TextStyle(fontSize: 10, color: AppColors.textMuted),
            ),
        ],
      ),
    );
  }

  Widget _detailRow(String label, String value, {bool highlight = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: highlight ? AppColors.success : AppColors.text,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPaginator() {
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
      decoration: const BoxDecoration(
        color: AppColors.surface,
        border: Border(top: BorderSide(color: AppColors.border)),
      ),
      child: SafeArea(
        top: false,
        child: Row(
          children: [
            Text(
              '$_total صنف',
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w600,
              ),
            ),
            const Spacer(),
            DropdownButtonHideUnderline(
              child: DropdownButton<int>(
                value: _pageSize,
                items: [
                  for (final s in _pageSizeOptions)
                    DropdownMenuItem(value: s, child: Text('$s / صفحة')),
                ],
                onChanged: (v) {
                  if (v == null) return;
                  setState(() => _pageSize = v);
                  _reloadFromStart();
                },
              ),
            ),
            IconButton(
              onPressed: _page > 1
                  ? () {
                      setState(() => _page--);
                      _load();
                    }
                  : null,
              icon: const Icon(Icons.chevron_right),
              tooltip: 'السابق',
            ),
            Text(
              '$_page / $_lastPage',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            IconButton(
              onPressed: _page < _lastPage
                  ? () {
                      setState(() => _page++);
                      _load();
                    }
                  : null,
              icon: const Icon(Icons.chevron_left),
              tooltip: 'التالي',
            ),
          ],
        ),
      ),
    );
  }
}
