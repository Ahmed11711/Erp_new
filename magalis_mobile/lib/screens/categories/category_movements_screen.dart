import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../services/categories_api.dart';
import '../../services/inventory_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';

/// تفاصيل حركة صنف — mirrors Angular `cat_details`.
class CategoryMovementsScreen extends StatefulWidget {
  const CategoryMovementsScreen({
    super.key,
    required this.categoryId,
    this.categoryName,
  });

  final String categoryId;
  final String? categoryName;

  @override
  State<CategoryMovementsScreen> createState() =>
      _CategoryMovementsScreenState();
}

class _CategoryMovementsScreenState extends State<CategoryMovementsScreen> {
  final _refCtrl = TextEditingController();
  List<WarehouseMovement> _items = [];
  String _name = '';
  int _total = 0;
  int _page = 1;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _name = widget.categoryName ?? '';
    _load(reset: true);
  }

  @override
  void dispose() {
    _refCtrl.dispose();
    super.dispose();
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
      final res = await CategoriesApi.instance.categoryMovements(
        id: widget.categoryId,
        page: reset ? 1 : _page,
        ref: _refCtrl.text,
      );
      if (!mounted) return;
      setState(() {
        _name = res.name.isNotEmpty ? res.name : _name;
        if (reset) {
          _items = res.page.items;
        } else {
          _items = [..._items, ...res.page.items];
        }
        _total = res.page.total;
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
        _error = 'تعذر تحميل تفاصيل الصنف';
        _loading = false;
        _loadingMore = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final canMore = _items.length < _total;
    return Scaffold(
      backgroundColor: AppColors.bg,
      appBar: AppBar(
        title: Text(_name.isEmpty ? 'تفاصيل الصنف' : _name),
        actions: [
          IconButton(
            onPressed: _loading ? null : () => _load(reset: true),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
            child: TextField(
              controller: _refCtrl,
              onSubmitted: (_) => _load(reset: true),
              decoration: InputDecoration(
                hintText: 'بحث بالمرجع...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(
                  onPressed: () => _load(reset: true),
                  icon: const Icon(Icons.arrow_back),
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
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
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_error!, style: const TextStyle(color: AppColors.danger)),
                            const SizedBox(height: 12),
                            ElevatedButton(
                              onPressed: () => _load(reset: true),
                              child: const Text('إعادة المحاولة'),
                            ),
                          ],
                        ),
                      )
                    : _items.isEmpty
                        ? const Center(child: Text('لا توجد حركات'))
                        : RefreshIndicator(
                            onRefresh: () => _load(reset: true),
                            child: ListView.separated(
                              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                              itemCount: _items.length + (canMore ? 1 : 0),
                              separatorBuilder: (_, __) =>
                                  const SizedBox(height: 10),
                              itemBuilder: (context, i) {
                                if (canMore && i == _items.length) {
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
                                final date = m.movementDate == null
                                    ? '—'
                                    : DateFormat('dd/MM/yyyy')
                                        .format(m.movementDate!.toLocal());
                                return Container(
                                  padding: const EdgeInsets.all(14),
                                  decoration: BoxDecoration(
                                    color: AppColors.surface,
                                    borderRadius: BorderRadius.circular(14),
                                    border: Border.all(color: AppColors.border),
                                  ),
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        m.type.isEmpty ? 'حركة' : m.type,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w800,
                                          color: AppColors.navy,
                                        ),
                                      ),
                                      const SizedBox(height: 6),
                                      Text(
                                        [
                                          'الكمية: ${Formatters.moneyPlain(m.quantity)}',
                                          if (m.balanceBefore != null)
                                            'قبل: ${Formatters.moneyPlain(m.balanceBefore!)}',
                                          if (m.balanceAfter != null)
                                            'بعد: ${Formatters.moneyPlain(m.balanceAfter!)}',
                                          date,
                                          if (m.by != null) 'بواسطة: ${m.by}',
                                        ].join(' · '),
                                        style: const TextStyle(
                                          color: AppColors.textSecondary,
                                          fontWeight: FontWeight.w600,
                                          fontSize: 12,
                                        ),
                                      ),
                                    ],
                                  ),
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
