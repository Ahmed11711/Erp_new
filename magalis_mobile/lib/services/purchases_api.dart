import 'dart:convert';

import 'package:dio/dio.dart';

import '../core/api_client.dart';
import 'catalog_api.dart';
import 'inventory_api.dart';

const kPurchaseInvoiceTypes = <String>[
  'اضافة وارد جديد',
  'مرتجع مبيعات',
  'امانات',
  'تم الاستلام',
  'اضافة وارد تشغيل',
  'مرتجع',
];

class PurchaseCategoryOption {
  const PurchaseCategoryOption({
    required this.id,
    required this.name,
    required this.unit,
    this.unitPrice = 0,
    this.quantity = 0,
    this.totalPrice = 0,
  });

  final String id;
  final String name;
  final String unit;
  final double unitPrice;
  final double quantity;
  final double totalPrice;

  double get averageUnitCost {
    if (quantity > 0.0000001) return totalPrice / quantity;
    if (unitPrice > 0.0000001) return unitPrice;
    return 0;
  }
}

class PurchaseLineDraft {
  PurchaseLineDraft({
    required this.categoryId,
    required this.productName,
    required this.productUnit,
    required this.productQuantity,
    required this.productPrice,
    this.priceEdited = false,
  });

  final String? categoryId;
  final String productName;
  final String productUnit;
  double productQuantity;
  double productPrice;
  bool priceEdited;

  double get total => productQuantity * productPrice;

  Map<String, dynamic> toJson() => {
        'category_id': categoryId == null || categoryId!.isEmpty
            ? null
            : int.tryParse(categoryId!),
        'product_name': productName,
        'product_unit': productUnit,
        'product_quantity': productQuantity,
        'product_price': productPrice,
        'total': total,
        'price_edited': priceEdited,
      };
}

class PurchaseInvoiceSummary {
  const PurchaseInvoiceSummary({
    required this.id,
    required this.invoiceNumber,
    required this.invoiceType,
    required this.supplierName,
    required this.totalPrice,
    required this.paidAmount,
    required this.dueAmount,
    this.transportCost = 0,
    this.productTotal = 0,
    this.shippingTotal = 0,
    this.grandTotal = 0,
    this.receiptDate,
    this.status,
    this.linesQty = 0,
    this.firstProductName,
    this.firstProductUnit,
  });

  final String id;
  final String invoiceNumber;
  final String invoiceType;
  final String supplierName;
  final double totalPrice;
  final double paidAmount;
  final double dueAmount;
  final double transportCost;
  /// تكلفة البضاعة فقط (تقرير المشتريات).
  final double productTotal;
  /// شحن التوريد.
  final double shippingTotal;
  final double grandTotal;
  final String? receiptDate;
  final dynamic status;
  final double linesQty;
  final String? firstProductName;
  final String? firstProductUnit;

  bool get isDeleted => status == 1 || status == '1';
  bool get isSuperseded => status == 0 || status == '0';

  factory PurchaseInvoiceSummary.fromJson(Map<String, dynamic> j) {
    final supplier = j['supplier'] is Map
        ? Map<String, dynamic>.from(j['supplier'] as Map)
        : <String, dynamic>{};
    final updated = j['updated_purchase'] is Map
        ? Map<String, dynamic>.from(j['updated_purchase'] as Map)
        : (j['updatedPurchase'] is Map
            ? Map<String, dynamic>.from(j['updatedPurchase'] as Map)
            : null);
    final src = updated ?? j;
    final productTotal = src['product_total'] != null && '${src['product_total']}' != ''
        ? _toD(src['product_total'])
        : _toD(src['total_price'] ?? j['total_price']);
    final shippingTotal = src['shipping_total'] != null && '${src['shipping_total']}' != ''
        ? _toD(src['shipping_total'])
        : _toD(src['transport_cost'] ?? j['transport_cost']);
    final grandTotal = src['grand_total'] != null && '${src['grand_total']}' != ''
        ? _toD(src['grand_total'])
        : productTotal + shippingTotal;
    return PurchaseInvoiceSummary(
      id: pick(j, ['id'], fallback: '0'),
      invoiceNumber: pick(
        j,
        ['invoice_number', 'invoice_no'],
        fallback: '#${pick(j, ['id'])}',
      ),
      invoiceType: pick(j, ['invoice_type'], fallback: '—'),
      supplierName: pick(
        supplier,
        ['supplier_name', 'name'],
        fallback: 'مورد',
      ),
      totalPrice: _toD(src['total_price'] ?? j['total_price']),
      paidAmount: _toD(src['paid_amount'] ?? j['paid_amount']),
      dueAmount: _toD(src['due_amount'] ?? j['due_amount']),
      transportCost: _toD(src['transport_cost'] ?? j['transport_cost']),
      productTotal: productTotal,
      shippingTotal: shippingTotal,
      grandTotal: grandTotal,
      receiptDate: _nullStr(pick(j, ['receipt_date'])),
      status: j['status'],
      linesQty: _toD(j['invoice_lines_qty']),
      firstProductName: _nullStr(pick(j, ['first_product_name'])),
      firstProductUnit: _nullStr(pick(j, ['first_product_unit'])),
    );
  }
}

class PurchaseLine {
  const PurchaseLine({
    required this.productName,
    required this.productUnit,
    required this.quantity,
    required this.price,
    required this.total,
  });

  final String productName;
  final String productUnit;
  final double quantity;
  final double price;
  final double total;

  factory PurchaseLine.fromJson(Map<String, dynamic> j) {
    return PurchaseLine(
      productName: pick(j, ['product_name', 'category_name'], fallback: 'صنف'),
      productUnit: pick(j, ['product_unit', 'unit'], fallback: '-'),
      quantity: _toD(j['product_quantity'] ?? j['quantity']),
      price: _toD(j['product_price'] ?? j['price']),
      total: _toD(j['total']),
    );
  }
}

class PurchaseInvoiceDetail {
  const PurchaseInvoiceDetail({
    required this.id,
    required this.invoiceNumber,
    required this.invoiceType,
    required this.supplierName,
    this.supplierId,
    required this.totalPrice,
    required this.paidAmount,
    required this.dueAmount,
    this.transportCost = 0,
    this.receiptDate,
    this.externalInvoiceNo,
    this.customInvoiceNo,
    this.printUrl,
    required this.lines,
  });

  final String id;
  final String invoiceNumber;
  final String invoiceType;
  final String supplierName;
  final String? supplierId;
  final double totalPrice;
  final double paidAmount;
  final double dueAmount;
  final double transportCost;
  final String? receiptDate;
  final String? externalInvoiceNo;
  final String? customInvoiceNo;
  final String? printUrl;
  final List<PurchaseLine> lines;

  factory PurchaseInvoiceDetail.fromJson(Map<String, dynamic> body) {
    final inv = body['invoice'] is Map
        ? Map<String, dynamic>.from(body['invoice'] as Map)
        : body;
    final supplier = inv['supplier'] is Map
        ? Map<String, dynamic>.from(inv['supplier'] as Map)
        : <String, dynamic>{};
    final cats = body['categories'] is List
        ? body['categories'] as List
        : const [];
    final supplierId = pick(supplier, ['id']).isNotEmpty
        ? pick(supplier, ['id'])
        : pick(inv, ['supplier_id']);
    return PurchaseInvoiceDetail(
      id: pick(inv, ['id'], fallback: '0'),
      invoiceNumber: pick(
        inv,
        ['invoice_number', 'invoice_no'],
        fallback: '#${pick(inv, ['id'])}',
      ),
      invoiceType: pick(inv, ['invoice_type'], fallback: '—'),
      supplierName: pick(
        supplier,
        ['supplier_name', 'name'],
        fallback: 'مورد',
      ),
      supplierId: supplierId.isEmpty ? null : supplierId,
      totalPrice: _toD(inv['total_price']),
      paidAmount: _toD(inv['paid_amount']),
      dueAmount: _toD(inv['due_amount']),
      transportCost: _toD(inv['transport_cost']),
      receiptDate: _nullStr(pick(inv, ['receipt_date'])),
      externalInvoiceNo: _nullStr(pick(inv, ['external_invoice_no'])),
      customInvoiceNo: _nullStr(pick(inv, ['custom_invoice_no'])),
      printUrl: _nullStr(pick(body, ['print_url'])),
      lines: [
        for (final row in cats)
          if (row is Map) PurchaseLine.fromJson(Map<String, dynamic>.from(row)),
      ],
    );
  }
}

/// Purchases APIs — same Laravel routes as Angular `InvoiceService`.
class PurchasesApi {
  PurchasesApi._();
  static final PurchasesApi instance = PurchasesApi._();

  final _api = ApiClient.instance;

  Future<InventoryPage<PurchaseInvoiceSummary>> search({
    int page = 1,
    int itemsPerPage = 50,
    String? q,
    String? supplierId,
    String? invoiceType,
    String? receiptDate,
    String? dateFrom,
    String? dateTo,
    String? purchaseId,
  }) async {
    try {
      final params = <String, dynamic>{
        'page': page,
        'itemsPerPage': itemsPerPage,
      };
      final query = q?.trim();
      if (query != null && query.isNotEmpty) params['q'] = query;
      if (supplierId != null && supplierId.isNotEmpty) {
        params['supplier_id'] = supplierId;
      }
      if (invoiceType != null && invoiceType.isNotEmpty) {
        params['invoice_type'] = invoiceType;
      }
      if (receiptDate != null && receiptDate.isNotEmpty) {
        params['receipt_date'] = receiptDate;
      }
      if (dateFrom != null && dateFrom.isNotEmpty) {
        params['date_from'] = dateFrom;
      }
      if (dateTo != null && dateTo.isNotEmpty) {
        params['date_to'] = dateTo;
      }
      if (purchaseId != null && purchaseId.isNotEmpty) {
        params['purchase_id'] = purchaseId;
      }

      final res = await _api.dio.get(
        '/purchases/search',
        queryParameters: params,
      );
      final body = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      final list = body['data'] is List
          ? body['data'] as List
          : (res.data is List ? res.data as List : const []);
      final items = <PurchaseInvoiceSummary>[];
      for (final row in list) {
        if (row is Map) {
          items.add(
            PurchaseInvoiceSummary.fromJson(Map<String, dynamic>.from(row)),
          );
        }
      }
      final total =
          int.tryParse('${body['total'] ?? items.length}') ?? items.length;
      return InventoryPage(items: items, total: total);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<PurchaseInvoiceDetail> show(String id, {bool forEdit = false}) async {
    try {
      final res = await _api.dio.get(
        '/purchases/$id',
        queryParameters: forEdit ? {'foredit': true} : null,
      );
      final data = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      return PurchaseInvoiceDetail.fromJson(data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<List<PurchaseCategoryOption>> rawCategories() async {
    try {
      final res = await _api.dio.get(
        '/categories/categoryByWarehouse',
        queryParameters: {'warehouse': 'مخزن مواد خام'},
      );
      final list = res.data is List
          ? res.data as List
          : (res.data is Map && res.data['data'] is List
              ? res.data['data'] as List
              : const []);
      final out = <PurchaseCategoryOption>[];
      for (final row in list) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        final meas = j['measurement'] is Map
            ? Map<String, dynamic>.from(j['measurement'] as Map)
            : <String, dynamic>{};
        out.add(
          PurchaseCategoryOption(
            id: pick(j, ['id'], fallback: '0'),
            name: pick(j, ['category_name', 'name'], fallback: 'صنف'),
            unit: pick(meas, ['unit'], fallback: '-'),
            unitPrice: _toD(j['unit_price'] ?? j['category_price']),
            quantity: _toD(j['quantity']),
            totalPrice: _toD(j['total_price']),
          ),
        );
      }
      return out;
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> create({
    required String supplierId,
    required String invoiceType,
    required String receiptDate,
    required List<PurchaseLineDraft> products,
    required double totalPrice,
    required double paidAmount,
    required double dueAmount,
    required double transportCost,
    String priceEdited = '0',
    String? shippingCompanyId,
    String? paymentType,
    String? bankId,
    String? safeId,
    String? serviceAccountId,
    String? externalInvoiceNo,
    String? customInvoiceNo,
    String? invoiceId,
  }) async {
    try {
      final map = <String, dynamic>{
        'supplier_id': supplierId,
        'invoice_type': invoiceType,
        'receipt_date': receiptDate,
        'total_price': totalPrice,
        'paid_amount': paidAmount,
        'due_amount': dueAmount,
        'transport_cost': transportCost,
        'price_edited': priceEdited,
        'products': jsonEncode(products.map((e) => e.toJson()).toList()),
      };
      if (invoiceId != null && invoiceId.isNotEmpty) {
        map['invoiceId'] = invoiceId;
      }
      if (shippingCompanyId != null && shippingCompanyId.isNotEmpty) {
        map['shipping_company_id'] = shippingCompanyId;
      }
      if (paymentType != null && paymentType.isNotEmpty) {
        map['payment_type'] = paymentType;
      }
      if (bankId != null && bankId.isNotEmpty) map['bank_id'] = bankId;
      if (safeId != null && safeId.isNotEmpty) map['safe_id'] = safeId;
      if (serviceAccountId != null && serviceAccountId.isNotEmpty) {
        map['service_account_id'] = serviceAccountId;
      }
      final ext = externalInvoiceNo?.trim();
      if (ext != null && ext.isNotEmpty) map['external_invoice_no'] = ext;
      final custom = customInvoiceNo?.trim();
      if (custom != null && custom.isNotEmpty) map['custom_invoice_no'] = custom;

      final res = await _api.dio.post(
        '/purchases',
        data: FormData.fromMap(map),
      );
      final data = res.data;
      if (data is Map && data['success'] == false) {
        throw ApiException('${data['message'] ?? 'فشل حفظ الفاتورة'}');
      }
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> delete(String id) async {
    try {
      await _api.dio.delete('/purchases/$id');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }
}

double _toD(dynamic v) {
  if (v == null) return 0;
  if (v is num) return v.toDouble();
  return double.tryParse('$v') ?? 0;
}

String? _nullStr(String s) => s.isEmpty ? null : s;
