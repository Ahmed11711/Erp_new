import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

class PageBar extends StatelessWidget {
  const PageBar({
    super.key,
    required this.page,
    required this.pageSize,
    required this.total,
    required this.onPageChanged,
    required this.onPageSizeChanged,
    this.pageSizeOptions = const [15, 50, 100],
    this.label,
  });

  final int page;
  final int pageSize;
  final int total;
  final ValueChanged<int> onPageChanged;
  final ValueChanged<int> onPageSizeChanged;
  final List<int> pageSizeOptions;
  final String? label;

  int get _lastPage => total <= 0 ? 1 : ((total - 1) ~/ pageSize) + 1;

  @override
  Widget build(BuildContext context) {
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
              label ?? '$total',
              style: const TextStyle(
                fontSize: 12,
                color: AppColors.textSecondary,
                fontWeight: FontWeight.w600,
              ),
            ),
            const Spacer(),
            DropdownButtonHideUnderline(
              child: DropdownButton<int>(
                value: pageSizeOptions.contains(pageSize)
                    ? pageSize
                    : pageSizeOptions.first,
                items: [
                  for (final s in pageSizeOptions)
                    DropdownMenuItem(value: s, child: Text('$s / صفحة')),
                ],
                onChanged: (v) {
                  if (v == null) return;
                  onPageSizeChanged(v);
                },
              ),
            ),
            IconButton(
              onPressed: page > 1 ? () => onPageChanged(page - 1) : null,
              icon: const Icon(Icons.chevron_right),
              tooltip: 'السابق',
            ),
            Text(
              '$page / $_lastPage',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            IconButton(
              onPressed: page < _lastPage ? () => onPageChanged(page + 1) : null,
              icon: const Icon(Icons.chevron_left),
              tooltip: 'التالي',
            ),
          ],
        ),
      ),
    );
  }
}
