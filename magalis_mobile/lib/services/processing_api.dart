import 'package:dio/dio.dart';

import '../core/api_client.dart';
import 'catalog_api.dart';
import 'inventory_api.dart';

class ProcessingOrderSummary {
  const ProcessingOrderSummary({
    required this.id,
    required this.orderNumber,
    required this.dispatchNumber,
    required this.status,
    required this.supplierName,
    required this.amount,
    this.dispatchDate,
    this.createdAt,
    this.linesCount = 0,
  });

  final String id;
  final String orderNumber;
  final String dispatchNumber;
  final String status;
  final String supplierName;
  final double amount;
  final String? dispatchDate;
  final String? createdAt;
  final int linesCount;

  factory ProcessingOrderSummary.fromJson(Map<String, dynamic> j) {
    final supplier = j['supplier'] is Map
        ? Map<String, dynamic>.from(j['supplier'] as Map)
        : <String, dynamic>{};
    final notes = j['dispatch_notes'] is List
        ? j['dispatch_notes'] as List
        : (j['dispatchNotes'] is List ? j['dispatchNotes'] as List : const []);
    Map<String, dynamic> firstNote = {};
    if (notes.isNotEmpty && notes.first is Map) {
      firstNote = Map<String, dynamic>.from(notes.first as Map);
    }
    final orderNumber = pick(j, ['order_number'], fallback: '#${pick(j, ['id'])}');
    final dispatchNumber = pick(
      firstNote,
      ['dispatch_number'],
      fallback: orderNumber,
    );
    return ProcessingOrderSummary(
      id: pick(j, ['id'], fallback: '0'),
      orderNumber: orderNumber,
      dispatchNumber: dispatchNumber,
      status: pick(j, ['status']),
      supplierName: pick(
        supplier,
        ['supplier_name', 'name'],
        fallback: 'مورد',
      ),
      amount: _toD(j['expected_service_total']),
      dispatchDate: _nullStr(pick(firstNote, ['dispatch_date'])),
      createdAt: _nullStr(pick(j, ['created_at'])),
      linesCount: int.tryParse('${j['lines_count'] ?? 0}') ?? 0,
    );
  }
}

class ProcessingOrderLine {
  const ProcessingOrderLine({
    required this.id,
    this.categoryId,
    required this.categoryName,
    required this.orderedQty,
    required this.dispatchedQty,
    required this.receivedGoodQty,
    required this.receivedDamagedQty,
    required this.receivedRejectedQty,
    required this.expectedServiceAmount,
    this.unitMaterialCost,
  });

  final String id;
  final String? categoryId;
  final String categoryName;
  final double orderedQty;
  final double dispatchedQty;
  final double receivedGoodQty;
  final double receivedDamagedQty;
  final double receivedRejectedQty;
  final double expectedServiceAmount;
  final double? unitMaterialCost;

  double get atVendorQty =>
      dispatchedQty - receivedGoodQty - receivedDamagedQty - receivedRejectedQty;

  factory ProcessingOrderLine.fromJson(Map<String, dynamic> j) {
    final cat = j['category'] is Map
        ? Map<String, dynamic>.from(j['category'] as Map)
        : <String, dynamic>{};
    return ProcessingOrderLine(
      id: pick(j, ['id'], fallback: '0'),
      categoryId: pick(cat, ['id'], fallback: pick(j, ['category_id'])),
      categoryName: pick(cat, ['category_name', 'name'], fallback: 'صنف'),
      orderedQty: _toD(j['ordered_qty']),
      dispatchedQty: _toD(j['dispatched_qty']),
      receivedGoodQty: _toD(j['received_good_qty']),
      receivedDamagedQty: _toD(j['received_damaged_qty']),
      receivedRejectedQty: _toD(j['received_rejected_qty']),
      expectedServiceAmount: _toD(j['expected_service_amount']),
      unitMaterialCost: _nullable(j['unit_material_cost']),
    );
  }
}

class ProcessingReceiptLine {
  const ProcessingReceiptLine({
    required this.categoryName,
    required this.destinationName,
    required this.goodQty,
    required this.damagedQty,
    this.destUnitCost,
    this.priorUnitCost,
    this.resultingUnitCost,
  });

  final String categoryName;
  final String destinationName;
  final double goodQty;
  final double damagedQty;
  final double? destUnitCost;
  final double? priorUnitCost;
  final double? resultingUnitCost;

  factory ProcessingReceiptLine.fromJson(Map<String, dynamic> j) {
    final dest = j['destination_category'] is Map
        ? Map<String, dynamic>.from(j['destination_category'] as Map)
        : <String, dynamic>{};
    final orderLine = j['processing_order_line'] is Map
        ? Map<String, dynamic>.from(j['processing_order_line'] as Map)
        : <String, dynamic>{};
    final srcCat = orderLine['category'] is Map
        ? Map<String, dynamic>.from(orderLine['category'] as Map)
        : <String, dynamic>{};
    return ProcessingReceiptLine(
      categoryName: pick(srcCat, ['category_name', 'name'], fallback: 'صنف'),
      destinationName:
          pick(dest, ['category_name', 'name'], fallback: '—'),
      goodQty: _toD(j['good_qty']),
      damagedQty: _toD(j['damaged_qty']),
      destUnitCost: _nullable(j['dest_unit_cost']),
      priorUnitCost: _nullable(j['prior_unit_cost']),
      resultingUnitCost: _nullable(j['resulting_unit_cost']),
    );
  }
}

class ProcessingReceipt {
  const ProcessingReceipt({
    required this.id,
    required this.receiptDate,
    required this.status,
    required this.lines,
  });

  final String id;
  final String receiptDate;
  final String status;
  final List<ProcessingReceiptLine> lines;

  factory ProcessingReceipt.fromJson(Map<String, dynamic> j) {
    final linesRaw = j['lines'] is List ? j['lines'] as List : const [];
    return ProcessingReceipt(
      id: pick(j, ['id'], fallback: '0'),
      receiptDate: pick(j, ['receipt_date'], fallback: '—'),
      status: pick(j, ['status'], fallback: ''),
      lines: [
        for (final row in linesRaw)
          if (row is Map)
            ProcessingReceiptLine.fromJson(Map<String, dynamic>.from(row)),
      ],
    );
  }
}

class ProcessingInvoice {
  const ProcessingInvoice({
    required this.id,
    required this.invoiceNumber,
    required this.status,
    required this.grandTotal,
    required this.paidAmount,
    required this.dueAmount,
  });

  final String id;
  final String invoiceNumber;
  final String status;
  final double grandTotal;
  final double paidAmount;
  final double dueAmount;

  factory ProcessingInvoice.fromJson(Map<String, dynamic> j) {
    return ProcessingInvoice(
      id: pick(j, ['id'], fallback: '0'),
      invoiceNumber: pick(j, ['invoice_number'], fallback: '—'),
      status: pick(j, ['status']),
      grandTotal: _toD(j['grand_total']),
      paidAmount: _toD(j['paid_amount']),
      dueAmount: _toD(j['due_amount']),
    );
  }
}

class ProcessingOrderDetail {
  const ProcessingOrderDetail({
    required this.id,
    required this.orderNumber,
    required this.dispatchNumber,
    required this.status,
    required this.supplierName,
    required this.amount,
    this.supplierBalance,
    this.dispatchDate,
    this.notes,
    required this.lines,
    required this.receipts,
    required this.invoices,
  });

  final String id;
  final String orderNumber;
  final String dispatchNumber;
  final String status;
  final String supplierName;
  final double amount;
  final double? supplierBalance;
  final String? dispatchDate;
  final String? notes;
  final List<ProcessingOrderLine> lines;
  final List<ProcessingReceipt> receipts;
  final List<ProcessingInvoice> invoices;

  factory ProcessingOrderDetail.fromJson(Map<String, dynamic> j) {
    final supplier = j['supplier'] is Map
        ? Map<String, dynamic>.from(j['supplier'] as Map)
        : <String, dynamic>{};
    final notes = j['dispatch_notes'] is List
        ? j['dispatch_notes'] as List
        : (j['dispatchNotes'] is List ? j['dispatchNotes'] as List : const []);
    Map<String, dynamic> firstNote = {};
    if (notes.isNotEmpty && notes.first is Map) {
      firstNote = Map<String, dynamic>.from(notes.first as Map);
    }
    final orderNumber = pick(j, ['order_number'], fallback: '#${pick(j, ['id'])}');
    final linesRaw = j['lines'] is List ? j['lines'] as List : const [];
    final receiptsRaw = j['receipts'] is List ? j['receipts'] as List : const [];
    final invoicesRaw = j['invoices'] is List ? j['invoices'] as List : const [];

    return ProcessingOrderDetail(
      id: pick(j, ['id'], fallback: '0'),
      orderNumber: orderNumber,
      dispatchNumber: pick(
        firstNote,
        ['dispatch_number'],
        fallback: orderNumber,
      ),
      status: pick(j, ['status']),
      supplierName: pick(
        supplier,
        ['supplier_name', 'name'],
        fallback: 'مورد',
      ),
      amount: _toD(j['expected_service_total']),
      supplierBalance: _nullable(supplier['balance']),
      dispatchDate: _nullStr(pick(firstNote, ['dispatch_date'])),
      notes: _nullStr(pick(j, ['notes'])),
      lines: [
        for (final row in linesRaw)
          if (row is Map)
            ProcessingOrderLine.fromJson(Map<String, dynamic>.from(row)),
      ],
      receipts: [
        for (final row in receiptsRaw)
          if (row is Map)
            ProcessingReceipt.fromJson(Map<String, dynamic>.from(row)),
      ],
      invoices: [
        for (final row in invoicesRaw)
          if (row is Map)
            ProcessingInvoice.fromJson(Map<String, dynamic>.from(row)),
      ],
    );
  }
}

class ProcessingMeta {
  const ProcessingMeta({
    required this.orderStatuses,
    this.dispatchTypes = const [
      (value: 'goods_to_supplier', label: 'صرف بضاعة لمورد'),
      (value: 'amanat', label: 'صرف أمانات'),
      (value: 'custody', label: 'صرف عهدة'),
    ],
    this.nextDispatchNumber,
  });

  final Map<String, String> orderStatuses;
  final List<({String value, String label})> dispatchTypes;
  final String? nextDispatchNumber;

  factory ProcessingMeta.fromJson(Map<String, dynamic> j) {
    final raw = j['order_statuses'];
    final statuses = <String, String>{};
    if (raw is Map) {
      raw.forEach((k, v) {
        if (v != null) statuses['$k'] = '$v';
      });
    }
    if (statuses.isEmpty) {
      statuses.addAll(const {
        'draft': 'مسودة',
        'approved': 'معتمد',
        'in_progress': 'قيد التشغيل',
        'partially_received': 'استلام جزئي',
        'completed': 'مكتمل',
        'cancelled': 'ملغى',
      });
    }
    final types = <({String value, String label})>[];
    final typesRaw = j['dispatch_types'];
    if (typesRaw is List) {
      for (final row in typesRaw) {
        if (row is! Map) continue;
        final m = Map<String, dynamic>.from(row);
        final value = pick(m, ['value']);
        final label = pick(m, ['label'], fallback: value);
        if (value.isNotEmpty) types.add((value: value, label: label));
      }
    }
    final next = pick(j, ['next_dispatch_number']);
    return ProcessingMeta(
      orderStatuses: statuses,
      dispatchTypes: types.isEmpty
          ? const [
              (value: 'goods_to_supplier', label: 'صرف بضاعة لمورد'),
              (value: 'amanat', label: 'صرف أمانات'),
              (value: 'custody', label: 'صرف عهدة'),
            ]
          : types,
      nextDispatchNumber: next.isEmpty ? null : next,
    );
  }

  String statusLabel(String code) => orderStatuses[code] ?? code;
}

class DispatchVoucherResult {
  const DispatchVoucherResult({
    required this.orderId,
    this.dispatchNumber = '',
  });

  final String orderId;
  final String dispatchNumber;
}

class ProcessingVendor {
  const ProcessingVendor({
    required this.id,
    required this.name,
    this.typeName,
  });

  final String id;
  final String name;
  final String? typeName;
}

class ProcessingKpis {
  const ProcessingKpis({
    this.openOrders = 0,
    this.qtyAtVendor = 0,
    this.outstandingAp = 0,
    this.overdueInvoices = 0,
  });

  final int openOrders;
  final double qtyAtVendor;
  final double outstandingAp;
  final int overdueInvoices;

  factory ProcessingKpis.fromJson(Map<String, dynamic> j) {
    return ProcessingKpis(
      openOrders: int.tryParse('${j['open_orders'] ?? 0}') ?? 0,
      qtyAtVendor: _toD(j['qty_at_vendor']),
      outstandingAp: _toD(j['outstanding_ap']),
      overdueInvoices: int.tryParse('${j['overdue_invoices'] ?? 0}') ?? 0,
    );
  }
}

class MaterialAtVendor {
  const MaterialAtVendor({
    required this.orderId,
    required this.orderNumber,
    required this.supplierName,
    required this.qtyAtVendor,
  });

  final String orderId;
  final String orderNumber;
  final String supplierName;
  final double qtyAtVendor;

  factory MaterialAtVendor.fromJson(Map<String, dynamic> j) {
    final order = j['order'] is Map
        ? Map<String, dynamic>.from(j['order'] as Map)
        : <String, dynamic>{};
    final supplier = j['supplier'] is Map
        ? Map<String, dynamic>.from(j['supplier'] as Map)
        : <String, dynamic>{};
    final orderId = pick(
      j,
      ['processing_order_id'],
      fallback: pick(order, ['id'], fallback: '0'),
    );
    return MaterialAtVendor(
      orderId: orderId,
      orderNumber: pick(
        order,
        ['order_number'],
        fallback: orderId,
      ),
      supplierName: pick(
        supplier,
        ['supplier_name', 'name'],
        fallback: 'معالج',
      ),
      qtyAtVendor: _toD(j['qty_at_vendor']),
    );
  }
}

/// Processing module APIs — same Laravel routes as Angular `ProcessingService`.
class ProcessingApi {
  ProcessingApi._();
  static final ProcessingApi instance = ProcessingApi._();

  final _api = ApiClient.instance;

  Future<ProcessingMeta> meta() async {
    try {
      final res = await _api.dio.get('/processing/meta');
      final data = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      return ProcessingMeta.fromJson(data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<InventoryPage<ProcessingOrderSummary>> listOrders({
    int page = 1,
    int itemsPerPage = 50,
    String? q,
    String? status,
    String? supplierId,
  }) async {
    try {
      final params = <String, dynamic>{
        'page': page,
        'itemsPerPage': itemsPerPage,
      };
      final query = q?.trim();
      if (query != null && query.isNotEmpty) params['q'] = query;
      if (status != null && status.isNotEmpty) params['status'] = status;
      if (supplierId != null && supplierId.isNotEmpty) {
        params['supplier_id'] = supplierId;
      }

      final res = await _api.dio.get(
        '/processing/orders',
        queryParameters: params,
      );
      final body = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      final list = body['data'] is List
          ? body['data'] as List
          : (res.data is List ? res.data as List : const []);
      final items = <ProcessingOrderSummary>[];
      for (final row in list) {
        if (row is Map) {
          items.add(
            ProcessingOrderSummary.fromJson(Map<String, dynamic>.from(row)),
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

  Future<ProcessingOrderDetail> getOrder(String id) async {
    try {
      final res = await _api.dio.get('/processing/orders/$id');
      final data = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      // Some APIs wrap in { data: ... }
      final order = data['data'] is Map
          ? Map<String, dynamic>.from(data['data'] as Map)
          : data;
      return ProcessingOrderDetail.fromJson(order);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  /// Dashboard KPIs — `GET /processing/dashboard/kpis`.
  Future<ProcessingKpis> kpis() async {
    try {
      final res = await _api.dio.get('/processing/dashboard/kpis');
      final data = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      return ProcessingKpis.fromJson(data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  /// Materials currently at vendors — `GET /processing/reports/materials-at-vendor`.
  Future<List<MaterialAtVendor>> materialsAtVendor() async {
    try {
      final res = await _api.dio.get('/processing/reports/materials-at-vendor');
      final list = res.data is List
          ? res.data as List
          : (res.data is Map && res.data['data'] is List
              ? res.data['data'] as List
              : const []);
      final out = <MaterialAtVendor>[];
      for (final row in list) {
        if (row is Map) {
          out.add(MaterialAtVendor.fromJson(Map<String, dynamic>.from(row)));
        }
      }
      return out;
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<List<ProcessingVendor>> listVendors() async {
    try {
      final res = await _api.dio.get('/suppliers/processing-vendors');
      final list = res.data is List
          ? res.data as List
          : (res.data is Map && res.data['data'] is List
              ? res.data['data'] as List
              : const []);
      final out = <ProcessingVendor>[];
      for (final row in list) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        final typeMap = j['supplierType'] is Map
            ? Map<String, dynamic>.from(j['supplierType'] as Map)
            : (j['supplier_type'] is Map
                ? Map<String, dynamic>.from(j['supplier_type'] as Map)
                : <String, dynamic>{});
        final id = pick(j, ['id']);
        final name = pick(j, ['supplier_name', 'name']);
        if (id.isEmpty || name.isEmpty) continue;
        final typeName = pick(typeMap, ['supplier_type', 'name']);
        out.add(
          ProcessingVendor(
            id: id,
            name: name,
            typeName: typeName.isEmpty ? null : typeName,
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

  Future<List<({String id, String name})>> listRepresentatives() async {
    try {
      final res = await _api.dio.get('/shippingcompanySelect');
      final list = res.data is List
          ? res.data as List
          : (res.data is Map && res.data['data'] is List
              ? res.data['data'] as List
              : const []);
      final out = <({String id, String name})>[];
      for (final row in list) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        if (pick(j, ['type']) != 'مندوب') continue;
        final id = pick(j, ['id']);
        final name = pick(j, ['name']);
        if (id.isNotEmpty && name.isNotEmpty) {
          out.add((id: id, name: name));
        }
      }
      out.sort((a, b) => a.name.compareTo(b.name));
      return out;
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<DispatchVoucherResult> submitDispatchVoucher({
    required String supplierId,
    required String dispatchDate,
    required String dispatchType,
    required List<Map<String, dynamic>> lines,
    String? notes,
    String? representativeType,
    String? shippingCompanyId,
    String? externalRepresentativeName,
    double? expectedServiceTotal,
  }) async {
    try {
      final body = <String, dynamic>{
        'supplier_id': int.tryParse(supplierId) ?? supplierId,
        'dispatch_date': dispatchDate,
        'dispatch_type': dispatchType,
        'lines': lines,
      };
      if (notes != null && notes.trim().isNotEmpty) {
        body['notes'] = notes.trim();
      }
      if (expectedServiceTotal != null) {
        body['expected_service_total'] = expectedServiceTotal;
      }
      if (representativeType != null && representativeType.isNotEmpty) {
        body['representative_type'] = representativeType;
        if (representativeType == 'internal' &&
            shippingCompanyId != null &&
            shippingCompanyId.isNotEmpty) {
          body['shipping_company_id'] =
              int.tryParse(shippingCompanyId) ?? shippingCompanyId;
        }
        if (representativeType == 'external' &&
            (externalRepresentativeName ?? '').trim().isNotEmpty) {
          body['external_representative_name'] =
              externalRepresentativeName!.trim();
        }
      }
      final res = await _api.dio.post('/processing/dispatches/voucher', data: body);
      final data = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      final order = data['order'] is Map
          ? Map<String, dynamic>.from(data['order'] as Map)
          : <String, dynamic>{};
      final dispatch = data['dispatch'] is Map
          ? Map<String, dynamic>.from(data['dispatch'] as Map)
          : <String, dynamic>{};
      return DispatchVoucherResult(
        orderId: pick(order, ['id'], fallback: '0'),
        dispatchNumber: pick(dispatch, ['dispatch_number']),
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<String> createAndPostReceipt({
    required String orderId,
    required String receiptDate,
    required List<Map<String, dynamic>> lines,
  }) async {
    try {
      final created = await _api.dio.post(
        '/processing/receipts',
        data: {
          'processing_order_id': int.tryParse(orderId) ?? orderId,
          'receipt_date': receiptDate,
          'lines': lines,
        },
      );
      final body = created.data is Map
          ? Map<String, dynamic>.from(created.data as Map)
          : <String, dynamic>{};
      final inner = body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : body;
      final id = pick(inner, ['id']);
      if (id.isEmpty || id == '0') {
        throw ApiException('تعذر إنشاء إذن الاستلام');
      }
      await _api.dio.post('/processing/receipts/$id/post');
      return id;
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

double? _nullable(dynamic v) {
  if (v == null) return null;
  if (v is num) return v.toDouble();
  return double.tryParse('$v');
}

String? _nullStr(String s) => s.isEmpty ? null : s;
