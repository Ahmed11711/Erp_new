import 'order.dart';

/// Filter state mirroring web `FilterOrderService` + list-orders panel.
class OrderFilters {
  const OrderFilters({
    this.customerType = '',
    this.orderType = '',
    this.shippingCompany = '',
    this.privateOrder = '',
    this.vip = false,
    this.shortage = false,
    this.paid = false,
    this.prepaidAmount = false,
    this.needByDate,
    this.statusDate,
    this.orderDate,
    this.deliveryDate,
    this.governorate = '',
    this.city = '',
    this.customerName = '',
    this.customerPhone = '',
    this.orderNumber = '',
    this.shipmentNumber = '',
    this.productQuery = '',
    this.orderSource = '',
    this.orderStatus = '',
    this.shippingMethod = '',
    this.shippingLine = '',
    this.reviewed = '',
    this.shopify = '',
    this.collectType = '',
  });

  final String customerType;
  final String orderType;
  final String shippingCompany;
  /// '' = all, '1' = admin-only
  final String privateOrder;
  final bool vip;
  final bool shortage;
  final bool paid;
  final bool prepaidAmount;
  final DateTime? needByDate;
  final DateTime? statusDate;
  final DateTime? orderDate;
  final DateTime? deliveryDate;
  final String governorate;
  final String city;
  final String customerName;
  final String customerPhone;
  final String orderNumber;
  final String shipmentNumber;
  final String productQuery;
  final String orderSource;
  final String orderStatus;
  final String shippingMethod;
  final String shippingLine;
  final String reviewed;
  /// '' | all | 1 | pending_review | reviewed
  final String shopify;
  final String collectType;

  static const OrderFilters empty = OrderFilters();

  bool get hasActiveFilters {
    return customerType.isNotEmpty ||
        orderType.isNotEmpty ||
        shippingCompany.isNotEmpty ||
        privateOrder.isNotEmpty ||
        vip ||
        shortage ||
        paid ||
        prepaidAmount ||
        needByDate != null ||
        statusDate != null ||
        orderDate != null ||
        deliveryDate != null ||
        governorate.isNotEmpty ||
        city.isNotEmpty ||
        customerName.trim().isNotEmpty ||
        customerPhone.trim().isNotEmpty ||
        orderNumber.trim().isNotEmpty ||
        shipmentNumber.trim().isNotEmpty ||
        productQuery.trim().isNotEmpty ||
        orderSource.isNotEmpty ||
        orderStatus.isNotEmpty ||
        shippingMethod.isNotEmpty ||
        shippingLine.isNotEmpty ||
        reviewed.isNotEmpty ||
        shopify.isNotEmpty ||
        collectType.isNotEmpty;
  }

  int get activeCount {
    var n = 0;
    void count(bool v) {
      if (v) n++;
    }

    count(customerType.isNotEmpty);
    count(orderType.isNotEmpty);
    count(shippingCompany.isNotEmpty);
    count(privateOrder.isNotEmpty);
    count(vip);
    count(shortage);
    count(paid);
    count(prepaidAmount);
    count(needByDate != null);
    count(statusDate != null);
    count(orderDate != null);
    count(deliveryDate != null);
    count(governorate.isNotEmpty);
    count(city.isNotEmpty);
    count(customerName.trim().isNotEmpty);
    count(customerPhone.trim().isNotEmpty);
    count(orderNumber.trim().isNotEmpty);
    count(shipmentNumber.trim().isNotEmpty);
    count(productQuery.trim().isNotEmpty);
    count(orderSource.isNotEmpty);
    count(orderStatus.isNotEmpty);
    count(shippingMethod.isNotEmpty);
    count(shippingLine.isNotEmpty);
    count(reviewed.isNotEmpty);
    count(shopify.isNotEmpty);
    count(collectType.isNotEmpty);
    return n;
  }

  OrderFilters copyWith({
    String? customerType,
    String? orderType,
    String? shippingCompany,
    String? privateOrder,
    bool? vip,
    bool? shortage,
    bool? paid,
    bool? prepaidAmount,
    DateTime? needByDate,
    DateTime? statusDate,
    DateTime? orderDate,
    DateTime? deliveryDate,
    bool clearNeedByDate = false,
    bool clearStatusDate = false,
    bool clearOrderDate = false,
    bool clearDeliveryDate = false,
    String? governorate,
    String? city,
    String? customerName,
    String? customerPhone,
    String? orderNumber,
    String? shipmentNumber,
    String? productQuery,
    String? orderSource,
    String? orderStatus,
    String? shippingMethod,
    String? shippingLine,
    String? reviewed,
    String? shopify,
    String? collectType,
  }) {
    return OrderFilters(
      customerType: customerType ?? this.customerType,
      orderType: orderType ?? this.orderType,
      shippingCompany: shippingCompany ?? this.shippingCompany,
      privateOrder: privateOrder ?? this.privateOrder,
      vip: vip ?? this.vip,
      shortage: shortage ?? this.shortage,
      paid: paid ?? this.paid,
      prepaidAmount: prepaidAmount ?? this.prepaidAmount,
      needByDate: clearNeedByDate ? null : (needByDate ?? this.needByDate),
      statusDate: clearStatusDate ? null : (statusDate ?? this.statusDate),
      orderDate: clearOrderDate ? null : (orderDate ?? this.orderDate),
      deliveryDate:
          clearDeliveryDate ? null : (deliveryDate ?? this.deliveryDate),
      governorate: governorate ?? this.governorate,
      city: city ?? this.city,
      customerName: customerName ?? this.customerName,
      customerPhone: customerPhone ?? this.customerPhone,
      orderNumber: orderNumber ?? this.orderNumber,
      shipmentNumber: shipmentNumber ?? this.shipmentNumber,
      productQuery: productQuery ?? this.productQuery,
      orderSource: orderSource ?? this.orderSource,
      orderStatus: orderStatus ?? this.orderStatus,
      shippingMethod: shippingMethod ?? this.shippingMethod,
      shippingLine: shippingLine ?? this.shippingLine,
      reviewed: reviewed ?? this.reviewed,
      shopify: shopify ?? this.shopify,
      collectType: collectType ?? this.collectType,
    );
  }

  String _ymd(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  /// Query params for `GET /orders/search` (same keys as Angular FilterOrderService).
  Map<String, dynamic> toApiParams() {
    final params = <String, dynamic>{};
    void set(String key, dynamic value) {
      if (value == null) return;
      if (value is String && value.trim().isEmpty) return;
      if (value is bool && !value) return;
      params[key] = value is bool ? '1' : value;
    }

    set('customer_type', customerType);
    set('order_type', orderType);
    set('private_order', privateOrder);
    set('vip', vip);
    set('shortage', shortage);
    if (paid) set('paid', '1');
    if (prepaidAmount) set('prepaidAmount', '1');
    set('governorate', governorate);
    set('city', city);
    set('customer_name', customerName.trim());
    set('customer_phone', customerPhone.trim());
    set('order_number', orderNumber.trim());
    set('shippment_number', shipmentNumber.trim());
    set('order_status', orderStatus);
    set('reviewed', reviewed);
    if (shopify.isNotEmpty && shopify != 'all') set('shopify', shopify);
    if (collectType.isNotEmpty && collectType != 'all') {
      set('collectType', collectType);
    }
    if (needByDate != null) set('need_by_date', _ymd(needByDate!));
    if (statusDate != null) set('status_date', _ymd(statusDate!));
    if (orderDate != null) set('order_date', _ymd(orderDate!));
    if (deliveryDate != null) set('delivery_date', _ymd(deliveryDate!));
    return params;
  }

  /// Apply filters locally (UI / demo) — same semantics as web search params.
  List<Order> apply(List<Order> orders) {
    return orders.where(matches).toList();
  }

  bool matches(Order o) {
    if (customerType.isNotEmpty && o.customerType != customerType) return false;
    if (orderType.isNotEmpty && o.orderType != orderType) return false;
    if (shippingCompany.isNotEmpty && o.shippingCompany != shippingCompany) {
      return false;
    }
    if (privateOrder == '1' && !o.privateOrder) return false;
    if (vip && !o.vip) return false;
    if (shortage && !o.shortage) return false;
    if (paid && !o.paid) return false;
    if (prepaidAmount && !o.prepaidAmount) return false;

    if (governorate.isNotEmpty && o.governorate != governorate) return false;
    if (city.isNotEmpty && o.city != city) return false;

    final nameQ = customerName.trim().toLowerCase();
    if (nameQ.isNotEmpty && !o.customerName.toLowerCase().contains(nameQ)) {
      return false;
    }

    final phoneQ = customerPhone.trim();
    if (phoneQ.isNotEmpty && !o.phone.contains(phoneQ)) return false;

    final codeQ = orderNumber.trim().toLowerCase();
    if (codeQ.isNotEmpty && !o.code.toLowerCase().contains(codeQ)) return false;

    final shipQ = shipmentNumber.trim().toLowerCase();
    if (shipQ.isNotEmpty && !o.shipmentNumber.toLowerCase().contains(shipQ)) {
      return false;
    }

    final prodQ = productQuery.trim().toLowerCase();
    if (prodQ.isNotEmpty && !o.productSearchBlob.contains(prodQ)) return false;

    if (orderSource.isNotEmpty && o.orderSource != orderSource) return false;
    if (orderStatus.isNotEmpty &&
        o.statusLabel != orderStatus &&
        o.status.apiValue != orderStatus) {
      return false;
    }
    if (shippingMethod.isNotEmpty && o.shippingMethod != shippingMethod) {
      return false;
    }
    if (shippingLine.isNotEmpty && o.shippingLine != shippingLine) return false;

    if (reviewed.isNotEmpty && o.reviewed.toString() != reviewed) return false;

    if (shopify == '1' && !o.fromShopify) return false;
    if (shopify == 'pending_review' && !(o.fromShopify && !o.shopifyReviewed)) {
      return false;
    }
    if (shopify == 'reviewed' && !(o.fromShopify && o.shopifyReviewed)) {
      return false;
    }

    if (collectType.isNotEmpty &&
        collectType != 'all' &&
        o.collectType != collectType) {
      return false;
    }

    if (needByDate != null && !_sameDay(o.needByDate, needByDate)) return false;
    if (statusDate != null && !_sameDay(o.statusDate, statusDate)) return false;
    if (orderDate != null && !_sameDay(o.createdAt, orderDate)) return false;
    if (deliveryDate != null && !_sameDay(o.deliveryDate, deliveryDate)) {
      return false;
    }

    return true;
  }

  static bool _sameDay(DateTime? a, DateTime? b) {
    if (a == null || b == null) return false;
    return a.year == b.year && a.month == b.month && a.day == b.day;
  }
}

/// Static option lists for the filter panel (demo — mirrors web selects).
class OrderFilterOptions {
  OrderFilterOptions._();

  static const customerTypes = ['افراد', 'شركة'];

  static const orderTypes = [
    'جديد',
    'طلب صيانة',
    'طلب استبدال',
    'طلب مرتجع',
  ];

  static const shippingCompanies = [
    'أرامكس',
    'مواصلات مصر',
    'بوسطة',
  ];

  static const orderSources = [
    'الموقع',
    'واتساب',
    'Shopify',
    'هاتف',
  ];

  static const shippingMethods = [
    'توصيل منزلي',
    'استلام من الفرع',
  ];

  static const shippingLines = [
    'خط القاهرة',
    'خط الدلتا',
    'خط الإسكندرية',
  ];

  static const collectTypes = [
    'تحصيل متغير',
    'تحصيل الكتروني',
  ];

  static const reviewOptions = <MapEntry<String, String>>[
    MapEntry('0', 'لم تتم المراجعة'),
    MapEntry('2', 'تم المراجعة بملحوظة'),
    MapEntry('1', 'تم المراجعة'),
  ];

  static const shopifyOptions = <MapEntry<String, String>>[
    MapEntry('all', 'الكل'),
    MapEntry('1', 'Shopify فقط'),
    MapEntry('pending_review', 'بانتظار المراجعة'),
    MapEntry('reviewed', 'تمت مراجعتها'),
  ];

  static const governorates = <String, List<String>>{
    'القاهرة': ['مدينة نصر', 'المعادي', 'مصر الجديدة'],
    'الجيزة': ['الدقي', 'الهرم', '6 أكتوبر'],
    'الإسكندرية': ['سموحة', 'ستانلي', 'محرم بك'],
    'الدقهلية': ['المنصورة', 'طلخا'],
    'الغربية': ['طنطا', 'المحلة'],
  };
}
