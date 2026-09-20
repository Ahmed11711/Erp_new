import 'package:flutter/material.dart';

import '../models/offer.dart';
import '../models/order.dart';
import '../theme/app_colors.dart';

class StatusChip extends StatelessWidget {
  const StatusChip({
    super.key,
    required this.label,
    required this.color,
  });

  final String label;
  final Color color;

  factory StatusChip.order(OrderStatus status, {String? label}) {
    late final Color color;
    switch (status) {
      case OrderStatus.pending:
        color = AppColors.warning;
      case OrderStatus.confirmed:
        color = AppColors.info;
      case OrderStatus.shipped:
        color = AppColors.navy;
      case OrderStatus.delivered:
        color = AppColors.success;
      case OrderStatus.collected:
        color = AppColors.primary;
      case OrderStatus.cancelled:
        color = AppColors.danger;
    }
    return StatusChip(
      label: (label != null && label.trim().isNotEmpty) ? label : status.label,
      color: color,
    );
  }

  factory StatusChip.forOrder(Order order) =>
      StatusChip.order(order.status, label: order.statusLabel);

  factory StatusChip.offer(OfferStatus status) {
    switch (status) {
      case OfferStatus.draft:
        return StatusChip(label: 'مسودة', color: AppColors.textMuted);
      case OfferStatus.sent:
        return StatusChip(label: 'مرسل', color: AppColors.info);
      case OfferStatus.accepted:
        return StatusChip(label: 'مقبول', color: AppColors.success);
      case OfferStatus.rejected:
        return StatusChip(label: 'مرفوض', color: AppColors.danger);
      case OfferStatus.converted:
        return StatusChip(label: 'محوّل لطلب', color: AppColors.primary);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
