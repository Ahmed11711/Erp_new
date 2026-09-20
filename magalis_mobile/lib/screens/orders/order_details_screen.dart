import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/api_client.dart';
import '../../models/order.dart';
import '../../services/orders_api.dart';
import '../../theme/app_colors.dart';
import '../../utils/formatters.dart';
import '../../widgets/status_chip.dart';

class OrderDetailsScreen extends StatefulWidget {
  const OrderDetailsScreen({super.key, required this.orderId});

  final String orderId;

  @override
  State<OrderDetailsScreen> createState() => _OrderDetailsScreenState();
}

class _OrderDetailsScreenState extends State<OrderDetailsScreen> {
  Order? _order;
  bool _loading = true;
  bool _acting = false;
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
      final order = await OrdersApi.instance.getById(widget.orderId);
      if (!mounted) return;
      setState(() {
        _order = order;
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
        _error = 'تعذر تحميل الطلب';
        _loading = false;
      });
    }
  }

  String get _todayYmd => DateFormat('yyyy-MM-dd').format(DateTime.now());

  Future<void> _runAction(Future<void> Function() action) async {
    setState(() => _acting = true);
    try {
      await action();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('تم تنفيذ الإجراء بنجاح'),
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
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('فشل تنفيذ الإجراء'),
          backgroundColor: AppColors.danger,
          behavior: SnackBarBehavior.floating,
        ),
      );
    } finally {
      if (mounted) setState(() => _acting = false);
    }
  }

  Future<void> _confirmAction() async {
    final order = _order!;
    final noteCtrl = TextEditingController();
    final dateCtrl = TextEditingController(text: _todayYmd);
    String? lineId;

    List<LookupItem> lines = [];
    try {
      lines = await OrdersApi.instance.shippingLines();
    } catch (_) {}

    if (!mounted) return;
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setModal) {
            return Padding(
              padding: EdgeInsets.only(
                left: 16,
                right: 16,
                top: 16,
                bottom: MediaQuery.viewInsetsOf(ctx).bottom + 16,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'تأكيد الطلب',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 18,
                      color: AppColors.navy,
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: dateCtrl,
                    decoration: const InputDecoration(
                      labelText: 'تاريخ التسليم المطلوب (YYYY-MM-DD)',
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (!order.isOfferOrder)
                    DropdownButtonFormField<String>(
                      value: lineId,
                      decoration: const InputDecoration(labelText: 'خط التوزيع'),
                      items: lines
                          .map(
                            (l) => DropdownMenuItem(
                              value: l.id,
                              child: Text(l.name),
                            ),
                          )
                          .toList(),
                      onChanged: (v) => setModal(() => lineId = v),
                    ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: noteCtrl,
                    decoration: const InputDecoration(labelText: 'ملاحظة (اختياري)'),
                  ),
                  const SizedBox(height: 14),
                  ElevatedButton(
                    onPressed: () {
                      if (!order.isOfferOrder && (lineId == null || lineId!.isEmpty)) {
                        ScaffoldMessenger.of(ctx).showSnackBar(
                          const SnackBar(content: Text('خط التوزيع مطلوب')),
                        );
                        return;
                      }
                      Navigator.pop(ctx, true);
                    },
                    child: const Text('تأكيد'),
                  ),
                ],
              ),
            );
          },
        );
      },
    );

    if (ok != true) return;
    await _runAction(
      () => OrdersApi.instance.confirm(
        id: order.id,
        date: dateCtrl.text.trim().isEmpty ? _todayYmd : dateCtrl.text.trim(),
        lineId: lineId,
        note: noteCtrl.text.trim(),
      ),
    );
  }

  Future<void> _shipAction() async {
    final order = _order!;
    final products = order.shippableProductsPayload;
    if (products.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لا توجد أصناف قابلة للشحن')),
      );
      return;
    }

    List<LookupItem> companies = [];
    try {
      companies = await OrdersApi.instance.shippingCompanies();
    } catch (_) {}

    if (!mounted) return;
    final dateCtrl = TextEditingController(text: _todayYmd);
    final shipNoCtrl = TextEditingController();
    String? companyId;

    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setModal) {
            return Padding(
              padding: EdgeInsets.only(
                left: 16,
                right: 16,
                top: 16,
                bottom: MediaQuery.viewInsetsOf(ctx).bottom + 16,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'شحن الطلب',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 18,
                      color: AppColors.navy,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'سيتم شحن ${products.length} بند/بنود بالكمية المتبقية',
                    style: const TextStyle(
                      color: AppColors.textSecondary,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: dateCtrl,
                    decoration: const InputDecoration(labelText: 'تاريخ الشحن'),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    value: companyId,
                    decoration: const InputDecoration(labelText: 'شركة الشحن'),
                    items: companies
                        .map(
                          (c) => DropdownMenuItem(
                            value: c.id,
                            child: Text(c.name),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setModal(() => companyId = v),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: shipNoCtrl,
                    decoration: const InputDecoration(
                      labelText: 'رقم الشحنة (اختياري)',
                    ),
                  ),
                  const SizedBox(height: 14),
                  ElevatedButton(
                    onPressed: () {
                      if (companyId == null || companyId!.isEmpty) {
                        ScaffoldMessenger.of(ctx).showSnackBar(
                          const SnackBar(content: Text('اختر شركة الشحن')),
                        );
                        return;
                      }
                      Navigator.pop(ctx, true);
                    },
                    child: const Text('تنفيذ الشحن'),
                  ),
                ],
              ),
            );
          },
        );
      },
    );

    if (ok != true || companyId == null) return;
    await _runAction(
      () => OrdersApi.instance.ship(
        id: order.id,
        date: dateCtrl.text.trim().isEmpty ? _todayYmd : dateCtrl.text.trim(),
        companyId: companyId!,
        productsToShip: products,
        shipmentNumber: shipNoCtrl.text.trim(),
        paymentWay: order.customerType.contains('شركة') ? 'أجل' : null,
      ),
    );
  }

  Future<void> _deliverAction() async {
    final noteCtrl = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('تأكيد التسليم'),
        content: TextField(
          controller: noteCtrl,
          decoration: const InputDecoration(labelText: 'ملاحظة (اختياري)'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('إلغاء')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('تم التسليم')),
        ],
      ),
    );
    if (ok != true) return;
    await _runAction(
      () => OrdersApi.instance.deliver(
        id: _order!.id,
        note: noteCtrl.text.trim(),
      ),
    );
  }

  Future<void> _collectAction() async {
    List<LookupItem> banks = [];
    try {
      banks = await OrdersApi.instance.banks();
    } catch (_) {}

    if (!mounted) return;
    final noteCtrl = TextEditingController();
    String paymentType = 'bank';
    String? bankId = banks.isNotEmpty ? banks.first.id : null;

    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setModal) {
            return Padding(
              padding: EdgeInsets.only(
                left: 16,
                right: 16,
                top: 16,
                bottom: MediaQuery.viewInsetsOf(ctx).bottom + 16,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'تحصيل الطلب',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 18,
                      color: AppColors.navy,
                    ),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    value: paymentType,
                    decoration: const InputDecoration(labelText: 'طريقة التحصيل'),
                    items: const [
                      DropdownMenuItem(value: 'bank', child: Text('بنك / خزينة')),
                      DropdownMenuItem(value: 'safe', child: Text('خزنة')),
                    ],
                    onChanged: (v) => setModal(() => paymentType = v ?? 'bank'),
                  ),
                  const SizedBox(height: 10),
                  if (paymentType == 'bank')
                    DropdownButtonFormField<String>(
                      value: bankId,
                      decoration: const InputDecoration(labelText: 'البنك / الخزينة'),
                      items: banks
                          .map(
                            (b) => DropdownMenuItem(
                              value: b.id,
                              child: Text(b.name),
                            ),
                          )
                          .toList(),
                      onChanged: (v) => setModal(() => bankId = v),
                    ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: noteCtrl,
                    decoration: const InputDecoration(labelText: 'ملاحظة (اختياري)'),
                  ),
                  const SizedBox(height: 14),
                  ElevatedButton(
                    onPressed: () {
                      if (paymentType == 'bank' && (bankId == null || bankId!.isEmpty)) {
                        ScaffoldMessenger.of(ctx).showSnackBar(
                          const SnackBar(content: Text('اختر البنك')),
                        );
                        return;
                      }
                      Navigator.pop(ctx, true);
                    },
                    child: const Text('تنفيذ التحصيل'),
                  ),
                ],
              ),
            );
          },
        );
      },
    );

    if (ok != true) return;
    await _runAction(
      () => OrdersApi.instance.collect(
        id: _order!.id,
        paymentType: paymentType,
        bankId: paymentType == 'bank' ? bankId : null,
        safeId: paymentType == 'safe' ? bankId : null,
        note: noteCtrl.text.trim(),
      ),
    );
  }

  Future<void> _postponeAction() async {
    final noteCtrl = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('تأجيل الطلب'),
        content: TextField(
          controller: noteCtrl,
          decoration: const InputDecoration(labelText: 'سبب التأجيل'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('إلغاء')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('تأجيل')),
        ],
      ),
    );
    if (!mounted) return;
    if (ok != true) return;
    if (noteCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('يجب إدخال سبب التأجيل')),
      );
      return;
    }
    await _runAction(
      () => OrdersApi.instance.changeStatus(
        id: _order!.id,
        status: 'postponed',
        note: noteCtrl.text.trim(),
      ),
    );
  }

  Widget _actionsBar(Order order) {
    final actions = <Widget>[];

    if (order.canConfirm) {
      actions.add(_ActionBtn(
        label: 'تأكيد',
        icon: Icons.check_circle_outline,
        color: AppColors.info,
        onTap: _acting ? null : _confirmAction,
      ));
    }
    if (order.canShip) {
      actions.add(_ActionBtn(
        label: 'شحن',
        icon: Icons.local_shipping_outlined,
        color: AppColors.navy,
        onTap: _acting ? null : _shipAction,
      ));
    }
    if (order.canDeliver) {
      actions.add(_ActionBtn(
        label: 'تسليم',
        icon: Icons.handshake_outlined,
        color: AppColors.success,
        onTap: _acting ? null : _deliverAction,
      ));
    }
    if (order.canCollect) {
      actions.add(_ActionBtn(
        label: 'تحصيل',
        icon: Icons.payments_outlined,
        color: AppColors.primary,
        onTap: _acting ? null : _collectAction,
      ));
    }
    if (order.canPostpone) {
      actions.add(_ActionBtn(
        label: 'تأجيل',
        icon: Icons.schedule,
        color: AppColors.warning,
        onTap: _acting ? null : _postponeAction,
      ));
    }

    if (actions.isEmpty) {
      return const SizedBox.shrink();
    }

    return SafeArea(
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
        decoration: const BoxDecoration(
          color: AppColors.surface,
          border: Border(top: BorderSide(color: AppColors.border)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (_acting)
              const Padding(
                padding: EdgeInsets.only(bottom: 8),
                child: LinearProgressIndicator(minHeight: 2),
              ),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(children: [
                for (var i = 0; i < actions.length; i++) ...[
                  if (i > 0) const SizedBox(width: 8),
                  actions[i],
                ],
              ]),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: const Text('تفاصيل الطلب')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null || _order == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('تفاصيل الطلب')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _error ?? 'الطلب غير موجود',
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

    final order = _order!;

    return Scaffold(
      appBar: AppBar(
        title: Text('#${order.code}'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _acting ? null : _load,
          ),
        ],
      ),
      bottomNavigationBar: _actionsBar(order),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          Row(
            children: [
              StatusChip.forOrder(order),
              const Spacer(),
              Text(
                Formatters.dateTime(order.createdAt),
                style: const TextStyle(
                  color: AppColors.textMuted,
                  fontWeight: FontWeight.w600,
                  fontSize: 12,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _InfoCard(
            title: 'بيانات العميل',
            children: [
              _InfoRow(Icons.person_outline, 'الاسم', order.customerName),
              _InfoRow(Icons.phone_outlined, 'الهاتف', order.phone),
              _InfoRow(Icons.location_city_outlined, 'المدينة', order.city),
              _InfoRow(Icons.home_outlined, 'العنوان', order.address),
            ],
          ),
          const SizedBox(height: 12),
          _InfoCard(
            title: 'الشحن',
            children: [
              _InfoRow(
                Icons.local_shipping_outlined,
                'شركة الشحن',
                order.shippingCompany.isEmpty ? '—' : order.shippingCompany,
              ),
              if (order.shippingMethod.isNotEmpty)
                _InfoRow(
                  Icons.delivery_dining_outlined,
                  'طريقة الشحن',
                  order.shippingMethod,
                ),
              if (order.shippingLine.isNotEmpty)
                _InfoRow(
                  Icons.alt_route_outlined,
                  'خط الشحن',
                  order.shippingLine,
                ),
              if (order.shipmentNumber.isNotEmpty)
                _InfoRow(
                  Icons.qr_code_2_outlined,
                  'رقم الشحنة',
                  order.shipmentNumber,
                ),
            ],
          ),
          if (order.notes != null && order.notes!.trim().isNotEmpty) ...[
            const SizedBox(height: 12),
            _InfoCard(
              title: 'ملاحظات',
              children: [
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Text(
                    order.notes!,
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                      height: 1.45,
                    ),
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 12),
          _InfoCard(
            title: 'الأصناف',
            children: [
              if (order.items.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 8),
                  child: Text(
                    'لا توجد أصناف معروضة',
                    style: TextStyle(
                      color: AppColors.textMuted,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                )
              else
                for (final item in order.items) ...[
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                item.name,
                                style: const TextStyle(fontWeight: FontWeight.w700),
                              ),
                              Text(
                                '${item.qty} × ${Formatters.money(item.price)}'
                                '${item.remainingToShip < item.qty ? ' · متبقي للشحن: ${item.remainingToShip}' : ''}',
                                style: const TextStyle(
                                  color: AppColors.textSecondary,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                        Text(
                          Formatters.money(item.total),
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            color: AppColors.navy,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (item != order.items.last) const Divider(height: 1),
                ],
            ],
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.primaryBg,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Row(
              children: [
                const Text(
                  'صافي المطلوب',
                  style: TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.primaryDark,
                    fontSize: 16,
                  ),
                ),
                const Spacer(),
                Text(
                  Formatters.money(order.total),
                  style: const TextStyle(
                    fontWeight: FontWeight.w900,
                    color: AppColors.primary,
                    fontSize: 18,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionBtn extends StatelessWidget {
  const _ActionBtn({
    required this.label,
    required this.icon,
    required this.color,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return ElevatedButton.icon(
      onPressed: onTap,
      style: ElevatedButton.styleFrom(
        backgroundColor: color,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      ),
      icon: Icon(icon, size: 18),
      label: Text(label, style: const TextStyle(fontWeight: FontWeight.w800)),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.title, required this.children});

  final String title;
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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontWeight: FontWeight.w800,
              color: AppColors.navy,
              fontSize: 15,
            ),
          ),
          const SizedBox(height: 8),
          ...children,
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow(this.icon, this.label, this.value);

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: AppColors.primary),
          const SizedBox(width: 8),
          SizedBox(
            width: 88,
            child: Text(
              label,
              style: const TextStyle(
                color: AppColors.textMuted,
                fontWeight: FontWeight.w600,
                fontSize: 12,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              softWrap: true,
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}
