enum OrderStatus {
  pending,
  confirmed,
  shipped,
  delivered,
  collected,
  cancelled,
}

class OrderItem {
  const OrderItem({
    required this.name,
    required this.qty,
    required this.price,
    this.itemCode,
    this.lineId,
    this.shippedQty = 0,
    this.cancelledQty = 0,
  });

  final String name;
  final int qty;
  final double price;
  final String? itemCode;
  /// `order_products.id` — required for ship payload.
  final String? lineId;
  final double shippedQty;
  final double cancelledQty;

  double get total => qty * price;

  double get remainingToShip {
    final left = qty - shippedQty - cancelledQty;
    return left < 0 ? 0 : left;
  }

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    final category = json['category'];
    final name = category is Map
        ? '${category['category_name'] ?? category['name'] ?? 'صنف'}'
        : '${json['category_name'] ?? json['name'] ?? 'صنف'}';
    final qty = _asInt(json['quantity'] ?? json['qty']) ?? 1;
    final price = _asDouble(json['price']) ?? 0;
    final code = category is Map
        ? category['item_code']?.toString()
        : json['item_code']?.toString();
    return OrderItem(
      name: name,
      qty: qty,
      price: price,
      itemCode: code,
      lineId: json['id']?.toString(),
      shippedQty: _asDouble(json['shipped_quantity']) ?? 0,
      cancelledQty: _asDouble(json['cancelled_quantity']) ?? 0,
    );
  }
}

class Order {
  const Order({
    required this.id,
    required this.code,
    required this.customerName,
    required this.phone,
    required this.city,
    required this.address,
    required this.status,
    required this.total,
    required this.createdAt,
    required this.shippingCompany,
    required this.items,
    this.statusText,
    this.notes,
    this.customerType = 'افراد',
    this.orderType = 'جديد',
    this.governorate = '',
    this.shipmentNumber = '',
    this.orderSource = '',
    this.shippingMethod = '',
    this.shippingLine = '',
    this.collectType = '',
    this.vip = false,
    this.shortage = false,
    this.paid = false,
    this.prepaidAmount = false,
    this.privateOrder = false,
    this.reviewed = 0,
    this.fromShopify = false,
    this.shopifyReviewed = false,
    this.offerId,
    this.needByDate,
    this.statusDate,
    this.deliveryDate,
  });

  final String id;
  final String code;
  final String customerName;
  final String phone;
  final String city;
  final String address;
  final OrderStatus status;
  /// Raw Arabic status from Laravel (`order_status`).
  final String? statusText;
  final double total;
  final DateTime createdAt;
  final String shippingCompany;
  final List<OrderItem> items;
  final String? notes;

  final String customerType;
  final String orderType;
  final String governorate;
  final String shipmentNumber;
  final String orderSource;
  final String shippingMethod;
  final String shippingLine;
  final String collectType;
  final bool vip;
  final bool shortage;
  final bool paid;
  final bool prepaidAmount;
  final bool privateOrder;
  final int reviewed;
  final bool fromShopify;
  final bool shopifyReviewed;
  final String? offerId;
  final DateTime? needByDate;
  final DateTime? statusDate;
  final DateTime? deliveryDate;

  bool get isOfferOrder => offerId != null && offerId!.isNotEmpty;

  bool get canConfirm {
    final s = statusLabel;
    return s == 'طلب جديد' || s == 'جديد' || s == 'تم الصيانة';
  }

  bool get canShip {
    final s = statusLabel;
    return s == 'طلب مؤكد' || s == 'شحن جزئي';
  }

  bool get canDeliver {
    final s = statusLabel;
    return s == 'تم شحن' || s == 'شحن جزئي' || s == 'تسليم جزئي';
  }

  bool get canCollect {
    final s = statusLabel;
    return s == 'تم شحن' || s == 'تم التسليم';
  }

  bool get canPostpone {
    final s = statusLabel;
    return s == 'طلب جديد' || s == 'طلب مؤكد' || s == 'تم شحن';
  }

  List<Map<String, dynamic>> get shippableProductsPayload {
    return items
        .where((i) => i.lineId != null && i.remainingToShip > 0)
        .map((i) => {'id': int.tryParse(i.lineId!) ?? i.lineId, 'quantity': i.remainingToShip})
        .toList();
  }

  String get statusLabel =>
      (statusText != null && statusText!.trim().isNotEmpty)
          ? statusText!.trim()
          : status.label;

  String get productSearchBlob => items
      .map((i) => '${i.name} ${i.itemCode ?? ''}')
      .join(' ')
      .toLowerCase();

  factory Order.fromJson(Map<String, dynamic> json) {
    final details = json['order_details'];
    final detailsMap = details is Map ? Map<String, dynamic>.from(details) : null;
    final shippingCompany = detailsMap?['shipping_company'];
    final shippingLine = detailsMap?['shipping_line'];
    final shippingMethod = json['shipping_method'];
    final orderSource = json['order_source'];

    final products = <OrderItem>[];
    final rawProducts = json['order_products'];
    if (rawProducts is List) {
      for (final p in rawProducts) {
        if (p is Map) {
          products.add(OrderItem.fromJson(Map<String, dynamic>.from(p)));
        }
      }
    }

    final statusRaw = '${json['order_status'] ?? ''}'.trim();
    final net = _asDouble(json['net_total']) ??
        _asDouble(json['total']) ??
        _asDouble(json['order_total']) ??
        0;
    final prepaid = _asDouble(json['prepaid_amount']) ?? 0;

    DateTime? parseDate(dynamic v) {
      if (v == null) return null;
      return DateTime.tryParse(v.toString());
    }

    final orderDate = parseDate(json['order_date']) ??
        parseDate(json['created_at']) ??
        DateTime.now();

    final shipments = json['order_shipment_number'];
    var shipmentNumber = '';
    if (shipments is List && shipments.isNotEmpty && shipments.first is Map) {
      shipmentNumber =
          '${shipments.first['shipment_number'] ?? shipments.first['number'] ?? ''}';
    } else if (detailsMap != null) {
      shipmentNumber = '${detailsMap['shipment_number'] ?? ''}';
    }

    return Order(
      id: '${json['id']}',
      code: '${json['id']}',
      customerName: '${json['customer_name'] ?? ''}',
      phone: '${json['customer_phone_1'] ?? json['customer_phone'] ?? ''}',
      city: '${json['city'] ?? ''}',
      address: '${json['address'] ?? ''}',
      status: OrderStatusX.fromApi(statusRaw),
      statusText: statusRaw.isEmpty ? null : statusRaw,
      total: net,
      createdAt: orderDate,
      shippingCompany: shippingCompany is Map
          ? '${shippingCompany['name'] ?? '—'}'
          : '—',
      items: products,
      notes: _extractNotes(json['note'] ?? detailsMap?['notes']),
      customerType: '${json['customer_type'] ?? ''}',
      orderType: '${json['order_type'] ?? ''}',
      governorate: '${json['governorate'] ?? ''}',
      shipmentNumber: shipmentNumber,
      orderSource: orderSource is Map
          ? '${orderSource['name'] ?? ''}'
          : '${json['order_source_name'] ?? ''}',
      shippingMethod: shippingMethod is Map
          ? '${shippingMethod['name'] ?? ''}'
          : '${json['shipping_method_name'] ?? ''}',
      shippingLine: shippingLine is Map
          ? '${shippingLine['name'] ?? ''}'
          : '',
      collectType: '${json['collect_type'] ?? detailsMap?['collect_type'] ?? ''}',
      vip: _asBool(json['vip']) || _asBool(detailsMap?['vip']),
      shortage: _asBool(json['shortage']) || _asBool(detailsMap?['shortage']),
      paid: net <= 0,
      prepaidAmount: prepaid > 0,
      privateOrder: json['private_order'] == 1 || json['private_order'] == true,
      reviewed: _asInt(detailsMap?['reviewed'] ?? json['reviewed']) ?? 0,
      fromShopify: json['shopify_order_id'] != null,
      shopifyReviewed: json['shopify_reviewed_at'] != null,
      offerId: json['offer_id']?.toString(),
      needByDate: parseDate(json['need_by_date'] ?? detailsMap?['need_by_date']),
      statusDate: parseDate(detailsMap?['status_date'] ?? json['status_date']),
      deliveryDate: parseDate(json['delivery_date']),
    );
  }
}

extension OrderStatusX on OrderStatus {
  String get label {
    switch (this) {
      case OrderStatus.pending:
        return 'قيد الانتظار';
      case OrderStatus.confirmed:
        return 'مؤكد';
      case OrderStatus.shipped:
        return 'تم الشحن';
      case OrderStatus.delivered:
        return 'تم التسليم';
      case OrderStatus.collected:
        return 'تم التحصيل';
      case OrderStatus.cancelled:
        return 'ملغي';
    }
  }

  String get apiValue {
    switch (this) {
      case OrderStatus.pending:
        return 'طلب جديد';
      case OrderStatus.confirmed:
        return 'طلب مؤكد';
      case OrderStatus.shipped:
        return 'تم شحن';
      case OrderStatus.delivered:
        return 'تم التسليم';
      case OrderStatus.collected:
        return 'تم التحصيل';
      case OrderStatus.cancelled:
        return 'ملغي';
    }
  }

  static OrderStatus fromApi(String raw) {
    final s = raw.trim();
    if (s.contains('تحصيل')) return OrderStatus.collected;
    if (s.contains('تسليم')) return OrderStatus.delivered;
    if (s.contains('شحن')) return OrderStatus.shipped;
    if (s.contains('مؤكد')) return OrderStatus.confirmed;
    if (s.contains('ملغي') ||
        s.contains('رفض') ||
        s == 'أرشيف' ||
        s.contains('الغاء')) {
      return OrderStatus.cancelled;
    }
    return OrderStatus.pending;
  }
}

/// Laravel `note` relation is a list of objects — never dump Map/List via toString().
String? _extractNotes(dynamic raw) {
  if (raw == null) return null;
  if (raw is String) {
    final t = raw.trim();
    return t.isEmpty ? null : t;
  }
  if (raw is List) {
    final texts = <String>[];
    for (final row in raw) {
      if (row is Map) {
        final n = '${row['note'] ?? row['text'] ?? row['body'] ?? ''}'.trim();
        if (n.isNotEmpty) texts.add(n);
      } else if (row is String && row.trim().isNotEmpty) {
        texts.add(row.trim());
      }
    }
    if (texts.isEmpty) return null;
    // Latest notes first if API returns chronological; show up to 3.
    final shown = texts.length > 3 ? texts.take(3) : texts;
    return shown.join('\n');
  }
  if (raw is Map) {
    final n = '${raw['note'] ?? raw['text'] ?? raw['body'] ?? ''}'.trim();
    return n.isEmpty ? null : n;
  }
  return null;
}

int? _asInt(dynamic v) {
  if (v == null) return null;
  if (v is int) return v;
  return int.tryParse(v.toString());
}

double? _asDouble(dynamic v) {
  if (v == null) return null;
  if (v is num) return v.toDouble();
  return double.tryParse(v.toString());
}

bool _asBool(dynamic v) {
  if (v == true || v == 1 || v == '1') return true;
  return false;
}
