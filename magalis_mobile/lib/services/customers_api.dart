import 'package:dio/dio.dart';

import '../core/api_client.dart';
import 'catalog_api.dart';

class CustomerAccountRow {
  const CustomerAccountRow({
    required this.name,
    required this.phone,
    this.ordersCount = 0,
    this.totalCredit = 0,
    this.totalDebit = 0,
    this.customerType,
    this.treeAccountCode,
    this.treeAccountName,
    this.linked = false,
  });

  final String name;
  final String phone;
  final int ordersCount;
  final double totalCredit;
  final double totalDebit;
  final String? customerType;
  final String? treeAccountCode;
  final String? treeAccountName;
  final bool linked;

  double get due => totalDebit - totalCredit;

  factory CustomerAccountRow.fromJson(Map<String, dynamic> j) {
    Map<String, dynamic> tree = {};
    final rel = j['tree_account'];
    if (rel is Map) tree = Map<String, dynamic>.from(rel);
    final code = pick(tree, ['code']);
    final accName = pick(tree, ['name']);
    return CustomerAccountRow(
      name: pick(j, ['customer_name', 'name'], fallback: 'عميل'),
      phone: pick(j, ['customer_phone_1', 'phone']),
      ordersCount: _toI(j['orders_count'] ?? j['order_count']),
      totalCredit: _toD(j['total_credit']),
      totalDebit: _toD(j['total_debit']),
      customerType: _nullStr(pick(j, ['customer_type'])),
      treeAccountCode: code.isEmpty ? null : code,
      treeAccountName: accName.isEmpty ? null : accName,
      linked: tree.isNotEmpty || j['tree_account_id'] != null,
    );
  }
}

class CustomerAccountsPage {
  const CustomerAccountsPage({
    required this.items,
    required this.total,
    this.perPage = 15,
  });

  final List<CustomerAccountRow> items;
  final int total;
  final int perPage;
}

class CustomerLedgerRow {
  const CustomerLedgerRow({
    required this.orderId,
    this.createdAt,
    this.credit = 0,
    this.debit = 0,
  });

  final String orderId;
  final DateTime? createdAt;
  final double credit;
  final double debit;

  factory CustomerLedgerRow.fromJson(Map<String, dynamic> j) {
    return CustomerLedgerRow(
      orderId: pick(j, ['order_id', 'id'], fallback: '—'),
      createdAt: DateTime.tryParse('${j['created_at'] ?? ''}'),
      credit: _toD(j['total_credit'] ?? j['prepaid_amount']),
      debit: _toD(j['total_debit'] ?? j['net_total']),
    );
  }
}

class SupplierAccountRow {
  const SupplierAccountRow({
    required this.supplierId,
    required this.name,
    this.phone,
    this.orderCount = 0,
    this.credit = 0,
    this.debit = 0,
  });

  final String supplierId;
  final String name;
  final String? phone;
  final int orderCount;
  final double credit;
  final double debit;

  factory SupplierAccountRow.fromJson(Map<String, dynamic> j) {
    return SupplierAccountRow(
      supplierId: pick(j, ['supplier_id', 'id'], fallback: '0'),
      name: pick(j, ['supplier_name', 'name'], fallback: 'مورد'),
      phone: _nullStr(pick(j, ['supplier_phone', 'phone'])),
      orderCount: _toI(j['order_count'] ?? j['orders_count']),
      credit: _toD(j['credit'] ?? j['total_credit']),
      debit: _toD(j['debit'] ?? j['total_debit']),
    );
  }
}

class OrderReportRow {
  const OrderReportRow({
    this.orderId,
    this.customerName,
    this.entryBatchCode,
    this.totalCredit = 0,
    this.totalDebit = 0,
  });

  final String? orderId;
  final String? customerName;
  final String? entryBatchCode;
  final double totalCredit;
  final double totalDebit;

  String get key => '${orderId ?? ''}\u0001${entryBatchCode ?? ''}\u0001${customerName ?? ''}';

  factory OrderReportRow.fromJson(Map<String, dynamic> j) {
    return OrderReportRow(
      orderId: _nullStr(pick(j, ['order_id'])),
      customerName: _nullStr(pick(j, ['customer_name'])),
      entryBatchCode: _nullStr(pick(j, ['entry_batch_code'])),
      totalCredit: _toD(j['total_credit']),
      totalDebit: _toD(j['total_debit']),
    );
  }
}

class OrderReportDetailRow {
  const OrderReportDetailRow({
    this.entryBatchCode,
    this.orderId,
    this.assetName,
    this.debit = 0,
    this.credit = 0,
    this.customerPhone,
  });

  final String? entryBatchCode;
  final String? orderId;
  final String? assetName;
  final double debit;
  final double credit;
  final String? customerPhone;

  factory OrderReportDetailRow.fromJson(Map<String, dynamic> j) {
    String? assetName;
    final assets = j['assets'];
    if (assets is Map) {
      assetName = _nullStr(pick(Map<String, dynamic>.from(assets), ['name']));
    }
    return OrderReportDetailRow(
      entryBatchCode: _nullStr(pick(j, ['entry_batch_code'])),
      orderId: _nullStr(pick(j, ['order_id'])),
      assetName: assetName,
      debit: _toD(j['debit'] ?? j['total_debit']),
      credit: _toD(j['credit'] ?? j['total_credit']),
      customerPhone: _nullStr(pick(j, ['customer_phone_1'])),
    );
  }
}

/// Customer / supplier account reports — same Laravel routes as Angular `TransactionService`.
class CustomersApi {
  CustomersApi._();
  static final CustomersApi instance = CustomersApi._();

  final _api = ApiClient.instance;

  Future<CustomerAccountsPage> searchCustomers({
    int page = 1,
    int itemsPerPage = 15,
    String? search,
  }) async {
    try {
      final params = <String, dynamic>{
        'page': page,
        'itemsPerPage': itemsPerPage,
      };
      final q = search?.trim();
      if (q != null && q.isNotEmpty) params['search'] = q;
      final res = await _api.dio.get(
        '/transactions/by-customer-order/search',
        queryParameters: params,
      );
      return _parseCustomerPage(res.data, itemsPerPage);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<({List<CustomerLedgerRow> items, int total, int perPage})> customerDetails({
    required String customer,
    int page = 1,
    int itemsPerPage = 15,
  }) async {
    try {
      final res = await _api.dio.get(
        '/transactions/by-customer-order/detailed',
        queryParameters: {
          'page': page,
          'itemsPerPage': itemsPerPage,
          'customer': customer,
        },
      );
      final list = _extractList(res.data);
      final items = <CustomerLedgerRow>[];
      for (final row in list) {
        if (row is Map) {
          items.add(CustomerLedgerRow.fromJson(Map<String, dynamic>.from(row)));
        }
      }
      return (
        items: items,
        total: _extractTotal(res.data, items.length),
        perPage: _extractPerPage(res.data, itemsPerPage),
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<({List<SupplierAccountRow> items, int total, int perPage})> searchSuppliers({
    int page = 1,
    int itemsPerPage = 15,
    String? name,
    String? phone,
    String? status,
  }) async {
    try {
      final params = <String, dynamic>{
        'page': page,
        'itemsPerPage': itemsPerPage,
      };
      final n = name?.trim();
      if (n != null && n.isNotEmpty) params['supplier_name'] = n;
      final p = phone?.trim();
      if (p != null && p.isNotEmpty) params['supplier_phone'] = p;
      if (status == 'want' || status == 'own') params['status'] = status;

      final res = await _api.dio.get(
        '/transactions/by-supplier-order/search',
        queryParameters: params,
      );
      final list = _extractList(res.data);
      final items = <SupplierAccountRow>[];
      for (final row in list) {
        if (row is Map) {
          items.add(SupplierAccountRow.fromJson(Map<String, dynamic>.from(row)));
        }
      }
      return (
        items: items,
        total: _extractTotal(res.data, items.length),
        perPage: _extractPerPage(res.data, itemsPerPage),
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<List<OrderReportRow>> orderReport() async {
    try {
      final res = await _api.dio.get('/report/order');
      final payload = res.data is Map ? (res.data as Map)['data'] : res.data;
      final list = payload is List
          ? payload
          : (res.data is List ? res.data as List : const []);
      final items = <OrderReportRow>[];
      for (final row in list) {
        if (row is Map) {
          items.add(OrderReportRow.fromJson(Map<String, dynamic>.from(row)));
        }
      }
      return items;
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<List<OrderReportDetailRow>> orderReportDetails({String? orderId}) async {
    try {
      final params = <String, dynamic>{};
      if (orderId != null && orderId.isNotEmpty) params['order_id'] = orderId;
      final res = await _api.dio.get(
        '/report/getByOrderId',
        queryParameters: params,
      );
      final payload = res.data is Map ? (res.data as Map)['data'] : res.data;
      final list = payload is List ? payload : const [];
      final items = <OrderReportDetailRow>[];
      for (final row in list) {
        if (row is Map) {
          items.add(OrderReportDetailRow.fromJson(Map<String, dynamic>.from(row)));
        }
      }
      return items;
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  CustomerAccountsPage _parseCustomerPage(dynamic data, int fallbackPerPage) {
    final list = _extractList(data);
    final items = <CustomerAccountRow>[];
    for (final row in list) {
      if (row is Map) {
        items.add(CustomerAccountRow.fromJson(Map<String, dynamic>.from(row)));
      }
    }
    return CustomerAccountsPage(
      items: items,
      total: _extractTotal(data, items.length),
      perPage: _extractPerPage(data, fallbackPerPage),
    );
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

  int _extractTotal(dynamic data, int fallback) {
    if (data is! Map) return fallback;
    final top = int.tryParse('${data['total'] ?? ''}');
    if (top != null) return top;
    final nested = data['data'];
    if (nested is Map) {
      final n = int.tryParse('${nested['total'] ?? ''}');
      if (n != null) return n;
    }
    return fallback;
  }

  int _extractPerPage(dynamic data, int fallback) {
    if (data is! Map) return fallback;
    final top = int.tryParse('${data['per_page'] ?? ''}');
    if (top != null) return top;
    final nested = data['data'];
    if (nested is Map) {
      final n = int.tryParse('${nested['per_page'] ?? ''}');
      if (n != null) return n;
    }
    return fallback;
  }
}

double _toD(dynamic v) {
  if (v == null) return 0;
  if (v is num) return v.toDouble();
  return double.tryParse('$v'.replaceAll(',', '')) ?? 0;
}

int _toI(dynamic v) {
  if (v == null) return 0;
  if (v is int) return v;
  if (v is num) return v.toInt();
  return int.tryParse('$v') ?? 0;
}

String? _nullStr(String s) => s.isEmpty ? null : s;
