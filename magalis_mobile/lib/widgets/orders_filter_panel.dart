import 'package:flutter/material.dart';
import 'package:intl/intl.dart' hide TextDirection;

import '../models/order.dart';
import '../models/order_filters.dart';
import '../theme/app_colors.dart';

/// Collapsible orders filter panel — mirrors web `orders-filters-panel`.
class OrdersFilterPanel extends StatefulWidget {
  const OrdersFilterPanel({
    super.key,
    required this.value,
    required this.onChanged,
    this.initiallyExpanded = true,
  });

  final OrderFilters value;
  final ValueChanged<OrderFilters> onChanged;
  final bool initiallyExpanded;

  @override
  State<OrdersFilterPanel> createState() => _OrdersFilterPanelState();
}

class _OrdersFilterPanelState extends State<OrdersFilterPanel> {
  late bool _expanded;
  late final TextEditingController _nameCtrl;
  late final TextEditingController _phoneCtrl;
  late final TextEditingController _orderCtrl;
  late final TextEditingController _shipCtrl;
  late final TextEditingController _productCtrl;

  @override
  void initState() {
    super.initState();
    _expanded = widget.initiallyExpanded;
    final f = widget.value;
    _nameCtrl = TextEditingController(text: f.customerName);
    _phoneCtrl = TextEditingController(text: f.customerPhone);
    _orderCtrl = TextEditingController(text: f.orderNumber);
    _shipCtrl = TextEditingController(text: f.shipmentNumber);
    _productCtrl = TextEditingController(text: f.productQuery);
  }

  @override
  void didUpdateWidget(covariant OrdersFilterPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.value != widget.value) {
      _setIfChanged(_nameCtrl, widget.value.customerName);
      _setIfChanged(_phoneCtrl, widget.value.customerPhone);
      _setIfChanged(_orderCtrl, widget.value.orderNumber);
      _setIfChanged(_shipCtrl, widget.value.shipmentNumber);
      _setIfChanged(_productCtrl, widget.value.productQuery);
    }
  }

  void _setIfChanged(TextEditingController c, String text) {
    if (c.text != text) {
      c.value = TextEditingValue(
        text: text,
        selection: TextSelection.collapsed(offset: text.length),
      );
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _orderCtrl.dispose();
    _shipCtrl.dispose();
    _productCtrl.dispose();
    super.dispose();
  }

  void _emit(OrderFilters next) => widget.onChanged(next);

  Future<void> _pickDate({
    required DateTime? current,
    required void Function(DateTime?) apply,
  }) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: current ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      locale: const Locale('ar'),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: Theme.of(context).colorScheme.copyWith(
                  primary: AppColors.primary,
                ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null) apply(picked);
  }

  String _fmt(DateTime? d) {
    if (d == null) return '';
    return DateFormat('yyyy-MM-dd').format(d);
  }

  @override
  Widget build(BuildContext context) {
    final f = widget.value;
    final cities = f.governorate.isEmpty
        ? const <String>[]
        : (OrderFilterOptions.governorates[f.governorate] ?? const <String>[]);

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: AppColors.navy.withValues(alpha: 0.04),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          InkWell(
            onTap: () => setState(() => _expanded = !_expanded),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              child: Row(
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: AppColors.primaryBg,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(
                      Icons.tune_rounded,
                      color: AppColors.primary,
                      size: 20,
                    ),
                  ),
                  const SizedBox(width: 10),
                  const Expanded(
                    child: Text(
                      'فلترة الطلبات',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                        color: AppColors.navy,
                      ),
                    ),
                  ),
                  if (f.activeCount > 0)
                    Container(
                      margin: const EdgeInsetsDirectional.only(end: 8),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.primary,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        '${f.activeCount}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  if (f.hasActiveFilters)
                    TextButton(
                      onPressed: () => _emit(OrderFilters.empty),
                      child: const Text('مسح'),
                    ),
                  Icon(
                    _expanded
                        ? Icons.keyboard_arrow_up_rounded
                        : Icons.keyboard_arrow_down_rounded,
                    color: AppColors.textMuted,
                  ),
                ],
              ),
            ),
          ),
          if (_expanded) ...[
            const Divider(height: 1, color: AppColors.border),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 12, 12, 14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _SectionLabel('التصنيف والحالة'),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _DropdownField(
                        label: 'نوع العميل',
                        value: f.customerType.isEmpty ? null : f.customerType,
                        items: OrderFilterOptions.customerTypes,
                        onChanged: (v) =>
                            _emit(f.copyWith(customerType: v ?? '')),
                      ),
                      _DropdownField(
                        label: 'نوع الطلب',
                        value: f.orderType.isEmpty ? null : f.orderType,
                        items: OrderFilterOptions.orderTypes,
                        onChanged: (v) =>
                            _emit(f.copyWith(orderType: v ?? '')),
                      ),
                      _DropdownField(
                        label: 'شركة الشحن',
                        value: f.shippingCompany.isEmpty
                            ? null
                            : f.shippingCompany,
                        items: OrderFilterOptions.shippingCompanies,
                        onChanged: (v) =>
                            _emit(f.copyWith(shippingCompany: v ?? '')),
                      ),
                      _DropdownField(
                        label: 'حالة الطلب',
                        value: f.orderStatus.isEmpty ? null : f.orderStatus,
                        items: OrderStatus.values.map((s) => s.apiValue).toList(),
                        onChanged: (v) =>
                            _emit(f.copyWith(orderStatus: v ?? '')),
                      ),
                      _DropdownField(
                        label: 'مصدر الطلب',
                        value: f.orderSource.isEmpty ? null : f.orderSource,
                        items: OrderFilterOptions.orderSources,
                        onChanged: (v) =>
                            _emit(f.copyWith(orderSource: v ?? '')),
                      ),
                      _DropdownField(
                        label: 'طريقة الشحن',
                        value:
                            f.shippingMethod.isEmpty ? null : f.shippingMethod,
                        items: OrderFilterOptions.shippingMethods,
                        onChanged: (v) =>
                            _emit(f.copyWith(shippingMethod: v ?? '')),
                      ),
                      _DropdownField(
                        label: 'خط الشحن',
                        value: f.shippingLine.isEmpty ? null : f.shippingLine,
                        items: OrderFilterOptions.shippingLines,
                        onChanged: (v) =>
                            _emit(f.copyWith(shippingLine: v ?? '')),
                      ),
                      _MapDropdownField(
                        label: 'المراجعة',
                        value: f.reviewed.isEmpty ? null : f.reviewed,
                        items: OrderFilterOptions.reviewOptions,
                        onChanged: (v) =>
                            _emit(f.copyWith(reviewed: v ?? '')),
                      ),
                      _MapDropdownField(
                        label: 'طلبات Shopify',
                        value: f.shopify.isEmpty ? null : f.shopify,
                        items: OrderFilterOptions.shopifyOptions,
                        onChanged: (v) =>
                            _emit(f.copyWith(shopify: v ?? '')),
                      ),
                      _DropdownField(
                        label: 'نوع التحصيل',
                        value: f.collectType.isEmpty ? null : f.collectType,
                        items: OrderFilterOptions.collectTypes,
                        onChanged: (v) =>
                            _emit(f.copyWith(collectType: v ?? '')),
                      ),
                      _DropdownField(
                        label: 'خصوصية الطلب',
                        value: f.privateOrder.isEmpty ? null : f.privateOrder,
                        items: const ['1'],
                        itemLabel: (v) => v == '1' ? 'أدمن' : v,
                        onChanged: (v) =>
                            _emit(f.copyWith(privateOrder: v ?? '')),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  _SectionLabel('خيارات سريعة'),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 6,
                    runSpacing: 4,
                    children: [
                      _FlagChip(
                        label: 'VIP',
                        selected: f.vip,
                        onTap: () => _emit(f.copyWith(vip: !f.vip)),
                      ),
                      _FlagChip(
                        label: 'نواقص',
                        selected: f.shortage,
                        onTap: () => _emit(f.copyWith(shortage: !f.shortage)),
                      ),
                      _FlagChip(
                        label: 'مدفوع',
                        selected: f.paid,
                        onTap: () => _emit(f.copyWith(paid: !f.paid)),
                      ),
                      _FlagChip(
                        label: 'مبلغ تحت الحساب',
                        selected: f.prepaidAmount,
                        onTap: () =>
                            _emit(f.copyWith(prepaidAmount: !f.prepaidAmount)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  _SectionLabel('التواريخ'),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _DateChip(
                        label: 'تاريخ الشحن المطلوب',
                        value: _fmt(f.needByDate),
                        onPick: () => _pickDate(
                          current: f.needByDate,
                          apply: (d) => _emit(f.copyWith(needByDate: d)),
                        ),
                        onClear: f.needByDate == null
                            ? null
                            : () => _emit(f.copyWith(clearNeedByDate: true)),
                      ),
                      _DateChip(
                        label: 'تاريخ الحالة',
                        value: _fmt(f.statusDate),
                        onPick: () => _pickDate(
                          current: f.statusDate,
                          apply: (d) => _emit(f.copyWith(statusDate: d)),
                        ),
                        onClear: f.statusDate == null
                            ? null
                            : () => _emit(f.copyWith(clearStatusDate: true)),
                      ),
                      _DateChip(
                        label: 'تاريخ الطلب',
                        value: _fmt(f.orderDate),
                        onPick: () => _pickDate(
                          current: f.orderDate,
                          apply: (d) => _emit(f.copyWith(orderDate: d)),
                        ),
                        onClear: f.orderDate == null
                            ? null
                            : () => _emit(f.copyWith(clearOrderDate: true)),
                      ),
                      _DateChip(
                        label: 'تاريخ التسليم (حسب العميل)',
                        value: _fmt(f.deliveryDate),
                        onPick: () => _pickDate(
                          current: f.deliveryDate,
                          apply: (d) => _emit(f.copyWith(deliveryDate: d)),
                        ),
                        onClear: f.deliveryDate == null
                            ? null
                            : () => _emit(f.copyWith(clearDeliveryDate: true)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  _SectionLabel('الموقع والعميل'),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _DropdownField(
                        label: 'المحافظة',
                        value: f.governorate.isEmpty ? null : f.governorate,
                        items: OrderFilterOptions.governorates.keys.toList(),
                        onChanged: (v) => _emit(
                          f.copyWith(governorate: v ?? '', city: ''),
                        ),
                      ),
                      _DropdownField(
                        label: 'المدينة',
                        value: f.city.isEmpty ? null : f.city,
                        items: cities,
                        enabled: cities.isNotEmpty,
                        onChanged: (v) => _emit(f.copyWith(city: v ?? '')),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  _TextFilter(
                    controller: _nameCtrl,
                    hint: 'اسم العميل',
                    icon: Icons.person_outline,
                    onChanged: (v) => _emit(f.copyWith(customerName: v)),
                  ),
                  const SizedBox(height: 8),
                  _TextFilter(
                    controller: _phoneCtrl,
                    hint: 'رقم التليفون',
                    icon: Icons.phone_outlined,
                    keyboard: TextInputType.phone,
                    textDirection: TextDirection.ltr,
                    onChanged: (v) => _emit(f.copyWith(customerPhone: v)),
                  ),
                  const SizedBox(height: 8),
                  _TextFilter(
                    controller: _orderCtrl,
                    hint: 'رقم الطلب',
                    icon: Icons.tag,
                    onChanged: (v) => _emit(f.copyWith(orderNumber: v)),
                  ),
                  const SizedBox(height: 8),
                  _TextFilter(
                    controller: _shipCtrl,
                    hint: 'رقم البوليصة',
                    icon: Icons.local_shipping_outlined,
                    onChanged: (v) => _emit(f.copyWith(shipmentNumber: v)),
                  ),
                  const SizedBox(height: 8),
                  _TextFilter(
                    controller: _productCtrl,
                    hint: 'المنتج (اسم أو كود الصنف)',
                    icon: Icons.inventory_2_outlined,
                    onChanged: (v) => _emit(f.copyWith(productQuery: v)),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(
        fontWeight: FontWeight.w800,
        fontSize: 12,
        color: AppColors.textSecondary,
        letterSpacing: 0.2,
      ),
    );
  }
}

class _DropdownField extends StatelessWidget {
  const _DropdownField({
    required this.label,
    required this.items,
    required this.onChanged,
    this.value,
    this.enabled = true,
    this.itemLabel,
  });

  final String label;
  final String? value;
  final List<String> items;
  final ValueChanged<String?> onChanged;
  final bool enabled;
  final String Function(String)? itemLabel;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 168,
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          isDense: true,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
          enabled: enabled,
        ),
        child: DropdownButtonHideUnderline(
          child: DropdownButton<String>(
            isExpanded: true,
            value: value != null && items.contains(value) ? value : '',
            hint: Text(
              label,
              style: const TextStyle(fontSize: 13, color: AppColors.textMuted),
            ),
            items: [
              const DropdownMenuItem<String>(
                value: '',
                child: Text('— الكل —', style: TextStyle(fontSize: 13)),
              ),
              ...items.map(
                (e) => DropdownMenuItem(
                  value: e,
                  child: Text(
                    itemLabel?.call(e) ?? e,
                    style: const TextStyle(fontSize: 13),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ),
            ],
            onChanged: enabled
                ? (v) => onChanged(v == null || v.isEmpty ? null : v)
                : null,
          ),
        ),
      ),
    );
  }
}

class _MapDropdownField extends StatelessWidget {
  const _MapDropdownField({
    required this.label,
    required this.items,
    required this.onChanged,
    this.value,
  });

  final String label;
  final String? value;
  final List<MapEntry<String, String>> items;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 168,
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          isDense: true,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
        ),
        child: DropdownButtonHideUnderline(
          child: DropdownButton<String>(
            isExpanded: true,
            value: value != null && items.any((e) => e.key == value)
                ? value
                : '',
            hint: Text(
              label,
              style: const TextStyle(fontSize: 13, color: AppColors.textMuted),
            ),
            items: [
              const DropdownMenuItem<String>(
                value: '',
                child: Text('— الكل —', style: TextStyle(fontSize: 13)),
              ),
              ...items.map(
                (e) => DropdownMenuItem(
                  value: e.key,
                  child: Text(
                    e.value,
                    style: const TextStyle(fontSize: 13),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ),
            ],
            onChanged: (v) => onChanged(v == null || v.isEmpty ? null : v),
          ),
        ),
      ),
    );
  }
}

class _FlagChip extends StatelessWidget {
  const _FlagChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return FilterChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onTap(),
      selectedColor: AppColors.primary,
      checkmarkColor: Colors.white,
      labelStyle: TextStyle(
        color: selected ? Colors.white : AppColors.textSecondary,
        fontWeight: FontWeight.w700,
        fontSize: 12,
      ),
      backgroundColor: AppColors.bg,
      side: BorderSide(
        color: selected ? AppColors.primary : AppColors.border,
      ),
    );
  }
}

class _DateChip extends StatelessWidget {
  const _DateChip({
    required this.label,
    required this.value,
    required this.onPick,
    this.onClear,
  });

  final String label;
  final String value;
  final VoidCallback onPick;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) {
    final has = value.isNotEmpty;
    return InkWell(
      onTap: onPick,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        width: 168,
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: has ? AppColors.primaryLight : AppColors.border,
          ),
          color: has ? AppColors.primaryBg : AppColors.surface,
        ),
        child: Row(
          children: [
            const Icon(Icons.calendar_today_outlined,
                size: 16, color: AppColors.primary),
            const SizedBox(width: 6),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textMuted,
                    ),
                  ),
                  Text(
                    has ? value : 'اختر تاريخ',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: has ? AppColors.primary : AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
            ),
            if (onClear != null)
              GestureDetector(
                onTap: onClear,
                child: const Icon(Icons.close, size: 16, color: AppColors.textMuted),
              ),
          ],
        ),
      ),
    );
  }
}

class _TextFilter extends StatelessWidget {
  const _TextFilter({
    required this.controller,
    required this.hint,
    required this.icon,
    required this.onChanged,
    this.keyboard,
    this.textDirection,
  });

  final TextEditingController controller;
  final String hint;
  final IconData icon;
  final ValueChanged<String> onChanged;
  final TextInputType? keyboard;
  final TextDirection? textDirection;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      keyboardType: keyboard,
      textDirection: textDirection,
      onChanged: onChanged,
      decoration: InputDecoration(
        hintText: hint,
        isDense: true,
        prefixIcon: Icon(icon, size: 20),
        suffixIcon: controller.text.isEmpty
            ? null
            : IconButton(
                icon: const Icon(Icons.clear, size: 18),
                onPressed: () {
                  controller.clear();
                  onChanged('');
                },
              ),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }
}
