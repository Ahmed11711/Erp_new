import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../models/offer.dart';
import '../../services/offers_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/status_chip.dart';

class OfferDetailsScreen extends StatefulWidget {
  const OfferDetailsScreen({super.key, required this.offerId});

  final String offerId;

  @override
  State<OfferDetailsScreen> createState() => _OfferDetailsScreenState();
}

class _OfferDetailsScreenState extends State<OfferDetailsScreen> {
  Offer? _offer;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final offer = await OffersApi.instance.getById(widget.offerId);
      if (!mounted) return;
      setState(() {
        _offer = offer;
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
        _error = 'تعذر تحميل العرض';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: const Text('تفاصيل العرض')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null || _offer == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('تفاصيل العرض')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _error ?? 'العرض غير موجود',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: AppColors.danger,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 12),
                ElevatedButton(onPressed: _load, child: const Text('إعادة المحاولة')),
              ],
            ),
          ),
        ),
      );
    }

    final offer = _offer!;

    return Scaffold(
      appBar: AppBar(title: Text(offer.code)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              StatusChip.offer(offer.status),
              const SizedBox(width: 8),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: AppColors.navy.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  'عرض سعر ${offer.type}',
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    color: AppColors.navy,
                    fontSize: 12,
                  ),
                ),
              ),
              const Spacer(),
              Text(
                Formatters.date(offer.createdAt),
                style: const TextStyle(
                  color: AppColors.textMuted,
                  fontWeight: FontWeight.w600,
                  fontSize: 12,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          _Card(
            children: [
              _row('العميل', offer.customerName),
              _row('الشركة', offer.company),
              _row('عدد الأصناف', '${offer.itemsCount}'),
              _row('الإجمالي', Formatters.money(offer.total), highlight: true),
              if (offer.note != null && offer.note!.isNotEmpty)
                _row('ملاحظة', offer.note!),
            ],
          ),
        ],
      ),
    );
  }

  Widget _row(String label, String value, {bool highlight = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: const TextStyle(
                color: AppColors.textMuted,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                fontWeight: FontWeight.w800,
                color: highlight ? AppColors.primary : AppColors.text,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Card extends StatelessWidget {
  const _Card({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(children: children),
    );
  }
}
