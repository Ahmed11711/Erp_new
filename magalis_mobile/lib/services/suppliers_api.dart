import 'package:dio/dio.dart';

import '../core/api_client.dart';
import 'catalog_api.dart';

class SupplierTypeOption {
  const SupplierTypeOption({required this.id, required this.name});

  final String id;
  final String name;
}

class SupplierItem {
  const SupplierItem({
    required this.id,
    required this.name,
    this.phone,
    this.address,
    this.typeId,
    this.typeName,
    this.balance = 0,
    this.supplierRate,
    this.priceRate,
  });

  final String id;
  final String name;
  final String? phone;
  final String? address;
  final String? typeId;
  final String? typeName;
  final double balance;
  final double? supplierRate;
  final double? priceRate;

  factory SupplierItem.fromJson(Map<String, dynamic> j) {
    Map<String, dynamic> typeMap = {};
    final rel = j['supplierType'] ?? j['supplier_type'];
    if (rel is Map) {
      typeMap = Map<String, dynamic>.from(rel);
    }
    final typeIdRaw = typeMap.isNotEmpty
        ? pick(typeMap, ['id'])
        : pick(j, ['supplier_type']);
    return SupplierItem(
      id: pick(j, ['id'], fallback: '0'),
      name: pick(j, ['supplier_name', 'name'], fallback: 'مورد'),
      phone: _nullStr(pick(j, ['supplier_phone', 'phone'])),
      address: _nullStr(pick(j, ['supplier_address', 'address'])),
      typeId: typeIdRaw.isEmpty ? null : typeIdRaw,
      typeName: _nullStr(pick(typeMap, ['supplier_type', 'name'])),
      balance: _toD(j['balance']),
      supplierRate: _nullable(j['supplier_rate']),
      priceRate: _nullable(j['price_rate']),
    );
  }
}

class SupplierSearchPage {
  const SupplierSearchPage({
    required this.items,
    required this.total,
    this.sumOfBalance = 0,
  });

  final List<SupplierItem> items;
  final int total;
  final double sumOfBalance;
}

class SupplierLedgerRow {
  const SupplierLedgerRow({
    required this.id,
    required this.details,
    required this.documentType,
    required this.entryKind,
    required this.totalPrice,
    required this.paidAmount,
    required this.dueAmount,
    this.balanceAfter,
    this.receiptDate,
    this.invoiceNumber,
    this.isPayment = false,
  });

  final String id;
  final String details;
  final String documentType;
  final String entryKind;
  final double totalPrice;
  final double paidAmount;
  final double dueAmount;
  final double? balanceAfter;
  final String? receiptDate;
  final String? invoiceNumber;
  final bool isPayment;

  factory SupplierLedgerRow.fromJson(Map<String, dynamic> j) {
    return SupplierLedgerRow(
      id: pick(j, ['invoice_id', 'id'], fallback: '0'),
      details: pick(j, ['details'], fallback: '—'),
      documentType: pick(j, ['document_type'], fallback: ''),
      entryKind: pick(j, ['entry_kind'], fallback: ''),
      totalPrice: _toD(j['total_price']),
      paidAmount: _toD(j['paid_amount']),
      dueAmount: _toD(j['due_amount']),
      balanceAfter: _nullable(j['balance_after']),
      receiptDate: _nullStr(pick(j, ['receipt_date'])),
      invoiceNumber: _nullStr(pick(j, ['invoice_number'])),
      isPayment: j['is_payment'] == true || j['is_payment'] == 1,
    );
  }
}

class SupplierDetailsPage {
  const SupplierDetailsPage({
    required this.supplier,
    required this.rows,
    required this.total,
  });

  final SupplierItem supplier;
  final List<SupplierLedgerRow> rows;
  final int total;
}

class PaymentSourceItem {
  const PaymentSourceItem({
    required this.id,
    required this.name,
    this.balance = 0,
  });

  final String id;
  final String name;
  final double balance;
}

class PaymentSources {
  const PaymentSources({
    this.safes = const [],
    this.banks = const [],
    this.serviceAccounts = const [],
  });

  final List<PaymentSourceItem> safes;
  final List<PaymentSourceItem> banks;
  final List<PaymentSourceItem> serviceAccounts;
}

/// Suppliers APIs — same Laravel routes as Angular `SuppliersService`.
class SuppliersApi {
  SuppliersApi._();
  static final SuppliersApi instance = SuppliersApi._();

  final _api = ApiClient.instance;

  /// Lightweight names for selects — `GET /suppliers/supplier_names`.
  Future<List<SupplierTypeOption>> supplierNames() async {
    try {
      final res = await _api.dio.get('/suppliers/supplier_names');
      final raw = res.data is Map && res.data['data'] != null
          ? res.data['data']
          : res.data;
      final list = raw is List ? raw : _extractList(raw);
      final out = <SupplierTypeOption>[];
      for (final row in list) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        final id = pick(j, ['id']);
        final name = pick(j, ['supplier_name', 'name']);
        if (id.isNotEmpty && name.isNotEmpty) {
          out.add(SupplierTypeOption(id: id, name: name));
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

  Future<List<SupplierTypeOption>> types() async {
    try {
      final res = await _api.dio.get('/suppliers/getAllSupplierTypes');
      final list = res.data is List ? res.data as List : _extractList(res.data);
      final out = <SupplierTypeOption>[];
      for (final row in list) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        final id = pick(j, ['id']);
        final name = pick(j, ['supplier_type', 'name']);
        if (id.isNotEmpty && name.isNotEmpty) {
          out.add(SupplierTypeOption(id: id, name: name));
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

  Future<void> createType(String name) async {
    try {
      await _api.dio.post(
        '/suppliers/StoreSupplierType',
        data: {'supplier_type': name.trim()},
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> deleteType(String id) async {
    try {
      await _api.dio.delete('/suppliers/deleteType/$id');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<SupplierSearchPage> search({
    int page = 1,
    int itemsPerPage = 50,
    String? name,
    String? phone,
    String? typeId,
    String? status,
  }) async {
    try {
      final params = <String, dynamic>{
        'page': page,
        'itemsPerPage': itemsPerPage,
      };
      final n = name?.trim();
      final p = phone?.trim();
      if (n != null && n.isNotEmpty) params['supplier_name'] = n;
      if (p != null && p.isNotEmpty) params['supplier_phone'] = p;
      if (typeId != null && typeId.isNotEmpty) params['supplier_type'] = typeId;
      if (status != null && status.isNotEmpty) params['status'] = status;

      final res = await _api.dio.get(
        '/suppliers/search',
        queryParameters: params,
      );
      final body = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      final suppliersBlock = body['suppliers'] is Map
          ? Map<String, dynamic>.from(body['suppliers'] as Map)
          : body;
      final list = suppliersBlock['data'] is List
          ? suppliersBlock['data'] as List
          : _extractList(suppliersBlock);
      final items = <SupplierItem>[];
      for (final row in list) {
        if (row is Map) {
          items.add(SupplierItem.fromJson(Map<String, dynamic>.from(row)));
        }
      }
      final total = int.tryParse(
            '${suppliersBlock['total'] ?? items.length}',
          ) ??
          items.length;
      return SupplierSearchPage(
        items: items,
        total: total,
        sumOfBalance: _toD(body['sum_of_balance']),
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<SupplierItem> getSupplier(String id) async {
    try {
      final res = await _api.dio.get('/suppliers/$id');
      final data = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      final row = data['data'] is Map
          ? Map<String, dynamic>.from(data['data'] as Map)
          : data;
      return SupplierItem.fromJson(row);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> addSupplier({
    required String name,
    String? phone,
    String? address,
    String? typeId,
    double? supplierRate,
    double? priceRate,
  }) async {
    try {
      final body = <String, dynamic>{
        'supplier_name': name.trim(),
        'supplier_phone': (phone ?? '').trim(),
        'supplier_address': (address ?? '').trim(),
      };
      if (typeId != null && typeId.isNotEmpty) {
        body['supplier_type'] = typeId;
      }
      if (supplierRate != null) body['supplier_rate'] = supplierRate;
      if (priceRate != null) body['price_rate'] = priceRate;

      final res = await _api.dio.post('/suppliers', data: body);
      final data = res.data;
      if (data is Map && data['success'] == false) {
        throw ApiException('${data['message'] ?? 'فشل إضافة المورد'}');
      }
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> updateSupplier({
    required String id,
    required String name,
    String? phone,
    String? address,
    String? typeId,
    double? supplierRate,
    double? priceRate,
  }) async {
    try {
      final body = <String, dynamic>{
        'supplier_name': name.trim(),
        'supplier_phone': (phone ?? '').trim(),
        'supplier_address': (address ?? '').trim(),
      };
      if (typeId != null && typeId.isNotEmpty) {
        body['supplier_type'] = typeId;
      }
      if (supplierRate != null) body['supplier_rate'] = supplierRate;
      if (priceRate != null) body['price_rate'] = priceRate;

      await _api.dio.put('/suppliers/$id', data: body);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> deleteSupplier(String id) async {
    try {
      await _api.dio.delete('/suppliers/$id');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<SupplierDetailsPage> details({
    required String id,
    int page = 1,
    int itemsPerPage = 40,
  }) async {
    try {
      final res = await _api.dio.get(
        '/suppliers/supplierDetails/$id',
        queryParameters: {
          'page': page,
          'itemsPerPage': itemsPerPage,
        },
      );
      final body = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      final supplierMap = body['supplier'] is Map
          ? Map<String, dynamic>.from(body['supplier'] as Map)
          : <String, dynamic>{
              'id': id,
              'supplier_name': pick(body, ['name'], fallback: 'مورد'),
            };
      final dataBlock = body['data'];
      final list = dataBlock is Map && dataBlock['data'] is List
          ? dataBlock['data'] as List
          : (dataBlock is List ? dataBlock : const []);
      final rows = <SupplierLedgerRow>[];
      for (final row in list) {
        if (row is Map) {
          rows.add(SupplierLedgerRow.fromJson(Map<String, dynamic>.from(row)));
        }
      }
      final total = dataBlock is Map
          ? int.tryParse('${dataBlock['total'] ?? rows.length}') ?? rows.length
          : rows.length;
      return SupplierDetailsPage(
        supplier: SupplierItem.fromJson(supplierMap),
        rows: rows,
        total: total,
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<PaymentSources> paymentSources() async {
    try {
      final res = await _api.dio.get('/accounting/payment-sources');
      final body = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      return PaymentSources(
        safes: _parseSources(body['safes']),
        banks: _parseSources(body['banks']),
        serviceAccounts: _parseSources(body['service_accounts']),
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> paySupplier({
    required String id,
    required double amount,
    required String paymentType,
    String? bankId,
    String? safeId,
    String? serviceAccountId,
  }) async {
    try {
      final body = <String, dynamic>{
        'amount': amount,
        'payment_type': paymentType,
      };
      if (paymentType == 'safe' && safeId != null) {
        body['safe_id'] = safeId;
      } else if (paymentType == 'service_account' && serviceAccountId != null) {
        body['service_account_id'] = serviceAccountId;
      } else if (bankId != null) {
        body['bank_id'] = bankId;
      }

      final res = await _api.dio.post('/suppliers/supplierPay/$id', data: body);
      final data = res.data;
      if (data is Map) {
        final msg = '${data['message'] ?? ''}';
        if (msg.isNotEmpty && msg != 'success') {
          throw ApiException(msg);
        }
      }
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  List<PaymentSourceItem> _parseSources(dynamic raw) {
    if (raw is! List) return const [];
    final out = <PaymentSourceItem>[];
    for (final row in raw) {
      if (row is! Map) continue;
      final j = Map<String, dynamic>.from(row);
      final id = pick(j, ['id']);
      final name = pick(j, ['label', 'name']);
      if (id.isEmpty || name.isEmpty) continue;
      out.add(
        PaymentSourceItem(
          id: id,
          name: name,
          balance: _toD(j['balance']),
        ),
      );
    }
    return out;
  }

  List _extractList(dynamic data) {
    if (data is List) return data;
    if (data is! Map) return const [];
    final candidates = [data['data'], data['items']];
    for (final c in candidates) {
      if (c is List) return c;
      if (c is Map && c['data'] is List) return c['data'] as List;
    }
    return const [];
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
