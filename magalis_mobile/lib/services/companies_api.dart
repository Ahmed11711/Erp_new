import 'package:dio/dio.dart';

import '../core/api_client.dart';
import 'catalog_api.dart';

class CustomerCompany {
  const CustomerCompany({
    required this.id,
    required this.name,
    this.phone1,
    this.phone2,
    this.phone3,
    this.phone4,
    this.tel,
    this.governorate,
    this.city,
    this.address,
    this.numberOfOrders = 0,
    this.balance = 0,
    this.treeAccountId,
    this.treeAccountCode,
    this.treeAccountName,
  });

  final int id;
  final String name;
  final String? phone1;
  final String? phone2;
  final String? phone3;
  final String? phone4;
  final String? tel;
  final String? governorate;
  final String? city;
  final String? address;
  final int numberOfOrders;
  final double balance;
  final int? treeAccountId;
  final String? treeAccountCode;
  final String? treeAccountName;

  bool get linked => treeAccountId != null && treeAccountId! > 0;

  String get accountLabel {
    if (!linked) return 'غير مربوط';
    final code = treeAccountCode ?? '';
    final n = treeAccountName ?? '';
    if (code.isNotEmpty && n.isNotEmpty) return '$code — $n';
    if (n.isNotEmpty) return n;
    if (code.isNotEmpty) return code;
    return 'مرتبط';
  }

  factory CustomerCompany.fromJson(Map<String, dynamic> j) {
    Map<String, dynamic> tree = {};
    final rel = j['tree_account'];
    if (rel is Map) tree = Map<String, dynamic>.from(rel);
    final treeId = int.tryParse('${j['tree_account_id'] ?? tree['id'] ?? ''}');
    return CustomerCompany(
      id: int.tryParse('${j['id']}') ?? 0,
      name: pick(j, ['name', 'company_name'], fallback: 'شركة'),
      phone1: _nullStr(pick(j, ['phone1', 'phone'])),
      phone2: _nullStr(pick(j, ['phone2'])),
      phone3: _nullStr(pick(j, ['phone3'])),
      phone4: _nullStr(pick(j, ['phone4'])),
      tel: _nullStr(pick(j, ['tel'])),
      governorate: _nullStr(pick(j, ['governorate'])),
      city: _nullStr(pick(j, ['city'])),
      address: _nullStr(pick(j, ['address'])),
      numberOfOrders: _toI(j['number_of_orders'] ?? j['orders_count']),
      balance: _toD(j['balance']),
      treeAccountId: (treeId != null && treeId > 0) ? treeId : null,
      treeAccountCode: _nullStr(pick(tree, ['code'])),
      treeAccountName: _nullStr(pick(tree, ['name'])),
    );
  }
}

class CompanyBalanceRow {
  const CompanyBalanceRow({
    this.ref,
    this.details,
    this.type,
    this.amount = 0,
    this.balanceBefore = 0,
    this.balanceAfter = 0,
    this.date,
    this.byName,
  });

  final String? ref;
  final String? details;
  final String? type;
  final double amount;
  final double balanceBefore;
  final double balanceAfter;
  final String? date;
  final String? byName;

  bool get isOrderRef {
    final r = ref ?? '';
    return r.isNotEmpty && RegExp(r'^\d').hasMatch(r) && type != 'عروض أسعار';
  }

  factory CompanyBalanceRow.fromJson(Map<String, dynamic> j) {
    return CompanyBalanceRow(
      ref: _nullStr(pick(j, ['ref'])),
      details: _nullStr(pick(j, ['details'])),
      type: _nullStr(pick(j, ['type'])),
      amount: _toD(j['amount']),
      balanceBefore: _toD(j['balance_before']),
      balanceAfter: _toD(j['balance_after']),
      date: _nullStr(pick(j, ['date'])),
      byName: _nullStr(pick(j, ['name'])),
    );
  }
}

class CompanyBalancePage {
  const CompanyBalancePage({
    required this.name,
    required this.balance,
    required this.rows,
    required this.total,
    this.perPage = 15,
  });

  final String name;
  final double balance;
  final List<CompanyBalanceRow> rows;
  final int total;
  final int perPage;
}

/// Customer companies — same Laravel routes as Angular `CompaniesService`.
class CompaniesApi {
  CompaniesApi._();
  static final CompaniesApi instance = CompaniesApi._();

  final _api = ApiClient.instance;

  Future<({List<CustomerCompany> items, int total, int perPage})> search({
    int page = 1,
    int itemsPerPage = 15,
    String? name,
    String? phone,
    bool unlinkedOnly = false,
  }) async {
    try {
      final params = <String, dynamic>{
        'page': page,
        'itemsPerPage': itemsPerPage,
      };
      final n = name?.trim();
      if (n != null && n.isNotEmpty) params['name'] = n;
      final p = phone?.trim();
      if (p != null && p.isNotEmpty) params['phone'] = p;
      if (unlinkedOnly) params['unlinked_only'] = '1';

      final res = await _api.dio.get('/companies/search', queryParameters: params);
      final list = _extractList(res.data);
      final items = <CustomerCompany>[];
      for (final row in list) {
        if (row is Map) {
          items.add(CustomerCompany.fromJson(Map<String, dynamic>.from(row)));
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

  Future<int> unlinkedCount() async {
    try {
      final res = await _api.dio.get('/companies/unlinked-summary');
      final body = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      return _toI(body['unlinked_count']);
    } on DioException {
      return 0;
    }
  }

  Future<String> linkUnlinked() async {
    try {
      final res = await _api.dio.post('/companies/link-unlinked', data: {});
      final body = res.data is Map ? res.data as Map : {};
      final msg = '${body['message'] ?? ''}';
      return msg.isEmpty ? 'تم الربط بنجاح' : msg;
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> linkAccount(int id) async {
    try {
      await _api.dio.post('/companies/$id/link-account', data: {});
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> save({
    int? id,
    required Map<String, dynamic> payload,
  }) async {
    try {
      final res = id == null
          ? await _api.dio.post('/companies', data: payload)
          : await _api.dio.put('/companies/$id', data: payload);
      final data = res.data;
      if (data is Map) {
        final msg = '${data['message'] ?? ''}';
        if (msg.isNotEmpty && msg != 'success') {
          throw ApiException(msg);
        }
      }
    } on DioException catch (e) {
      throw ApiException(
        _companyError(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> collect({
    required int id,
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
      final res = await _api.dio.post('/companies/companycollect/$id', data: body);
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

  Future<CompanyBalancePage> balance({
    required int id,
    int page = 1,
    int itemsPerPage = 15,
  }) async {
    try {
      final res = await _api.dio.get(
        '/companies/$id',
        queryParameters: {'itemsPerPage': itemsPerPage, 'page': page},
      );
      final body = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      final name = pick(body, ['name'], fallback: '');
      final balance = _toD(body['balance']);
      final dataBlock = body['data'];
      final list = dataBlock is Map && dataBlock['data'] is List
          ? dataBlock['data'] as List
          : (dataBlock is List ? dataBlock : const []);
      final rows = <CompanyBalanceRow>[];
      for (final row in list) {
        if (row is Map) {
          rows.add(CompanyBalanceRow.fromJson(Map<String, dynamic>.from(row)));
        }
      }
      final total = dataBlock is Map
          ? _toI(dataBlock['total'] ?? rows.length)
          : rows.length;
      final perPage = dataBlock is Map
          ? _toI(dataBlock['per_page'] ?? itemsPerPage)
          : itemsPerPage;
      return CompanyBalancePage(
        name: name,
        balance: balance,
        rows: rows,
        total: total,
        perPage: perPage > 0 ? perPage : itemsPerPage,
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  String _companyError(DioException e) {
    final data = e.response?.data;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map) {
        final parts = <String>[];
        if (errors['name'] != null) parts.add('الشركة موجودة بالفعل');
        if (errors['phone1'] != null) parts.add('الرقم مستخدم من قبل شركة');
        if (parts.isNotEmpty) return parts.join('\n');
      }
      final msg = data['message'];
      if (msg is String && msg.isNotEmpty) return msg;
    }
    return ApiClient.messageFrom(e);
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

String? _nullStr(String s) => s.isEmpty || s == 'null' ? null : s;
