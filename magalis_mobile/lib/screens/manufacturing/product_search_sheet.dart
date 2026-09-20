import 'package:flutter/material.dart';

import '../../services/manufacturing_api.dart';
import '../../theme/app_colors.dart';

Future<ManufactureProductOption?> showProductSearchSheet({
  required BuildContext context,
  required String title,
  required List<ManufactureProductOption> items,
}) {
  return showModalBottomSheet<ManufactureProductOption>(
    context: context,
    isScrollControlled: true,
    builder: (_) => _ProductSearchSheet(title: title, items: items),
  );
}

class _ProductSearchSheet extends StatefulWidget {
  const _ProductSearchSheet({required this.title, required this.items});

  final String title;
  final List<ManufactureProductOption> items;

  @override
  State<_ProductSearchSheet> createState() => _ProductSearchSheetState();
}

class _ProductSearchSheetState extends State<_ProductSearchSheet> {
  final _ctrl = TextEditingController();

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  List<ManufactureProductOption> get _visible {
    final q = _ctrl.text.trim().toLowerCase();
    if (q.isEmpty) return widget.items;
    return widget.items.where((e) {
      return e.name.toLowerCase().contains(q) ||
          (e.itemCode ?? '').toLowerCase().contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final items = _visible;
    return Directionality(
      textDirection: TextDirection.rtl,
      child: SafeArea(
        child: SizedBox(
          height: MediaQuery.of(context).size.height * 0.8,
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                child: Text(
                  widget.title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                    color: AppColors.navy,
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: TextField(
                  controller: _ctrl,
                  autofocus: true,
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    hintText: 'بحث…',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: _ctrl.text.isEmpty
                        ? null
                        : IconButton(
                            onPressed: () {
                              _ctrl.clear();
                              setState(() {});
                            },
                            icon: const Icon(Icons.clear),
                          ),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Expanded(
                child: items.isEmpty
                    ? const Center(
                        child: Text(
                          'لا توجد نتائج',
                          style: TextStyle(color: AppColors.textMuted),
                        ),
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        itemCount: items.length,
                        separatorBuilder: (_, _) => const Divider(height: 1),
                        itemBuilder: (context, i) {
                          final item = items[i];
                          return ListTile(
                            onTap: () => Navigator.pop(context, item),
                            title: Text(
                              item.name,
                              style: const TextStyle(fontWeight: FontWeight.w700),
                            ),
                            subtitle: Text(
                              [
                                if (item.itemCode != null) item.itemCode!,
                                if (item.warehouse != null) item.warehouse!,
                              ].join(' · '),
                            ),
                          );
                        },
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
