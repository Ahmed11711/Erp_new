import 'package:dio/dio.dart';

import '../core/api_client.dart';
import 'catalog_api.dart';

class VoucherParty {
  const VoucherParty({required this.id, required this.name});

  final int id;
  final String name;
}

class VoucherAccountOption {
  const VoucherAccountOption({required this.id, required this.name});

  final int id;
  final String name;
}

class VoucherPaymentSources {
  const VoucherPaymentSources({
    this.safes = const [],
    this.banks = const [],
    this.serviceAccounts = const [],
  });

  final List<VoucherAccountOption> safes;
  final List<VoucherAccountOption> banks;
  final List<VoucherAccountOption> serviceAccounts;
}

class VoucherItem {
  const VoucherItem({
    required this.id,
    required this.date,
    required this.type,
    required this.voucherType,
    required this.amount,
    this.accountId,
    this.clientId,
    this.supplierId,
    this.partyName,
    this.notes,
    this.referenceNumber,
    this.userName,
  });

  final int id;
  final String date;
  final String type;
  final String voucherType;
  final double amount;
  final int? accountId;
  final int? clientId;
  final int? supplierId;
  final String? partyName;
  final String? notes;
  final String? referenceNumber;
  final String? userName;

  bool get isReceipt => type == 'receipt';

  String get typeLabel => isReceipt ? 'قبض' : 'صرف';

  String get dateLabel {
    final raw = date.length >= 10 ? date.substring(0, 10) : date;
    final parts = raw.split('-');
    if (parts.length == 3) return '${parts[2]}/${parts[1]}/${parts[0]}';
    return raw;
  }

  factory VoucherItem.fromJson(Map<String, dynamic> j) {
    String? party = _nullStr(pick(j, ['client_or_supplier_name']));
    final client = j['client'];
    if (party == null && client is Map) {
      party = _nullStr(pick(Map<String, dynamic>.from(client), ['name', 'company_name']));
    }
    final supplier = j['supplier'];
    if (party == null && supplier is Map) {
      party = _nullStr(
        pick(Map<String, dynamic>.from(supplier), ['supplier_name', 'name']),
      );
    }
    String? userName;
    final user = j['user'];
    if (user is Map) {
      userName = _nullStr(pick(Map<String, dynamic>.from(user), ['name']));
    }
    return VoucherItem(
      id: int.tryParse('${j['id']}') ?? 0,
      date: pick(j, ['date']),
      type: pick(j, ['type'], fallback: 'receipt'),
      voucherType: pick(j, ['voucher_type'], fallback: 'client'),
      amount: _toD(j['amount']),
      accountId: int.tryParse('${j['account_id'] ?? ''}'),
      clientId: int.tryParse('${j['client_id'] ?? ''}'),
      supplierId: int.tryParse('${j['supplier_id'] ?? ''}'),
      partyName: party,
      notes: _nullStr(pick(j, ['notes'])),
      referenceNumber: _nullStr(pick(j, ['reference_number'])),
      userName: userName,
    );
  }
}

/// سندات العملاء/الموردين — same Laravel routes as Angular `VoucherService`.
class VouchersApi {
  VouchersApi._();
  static final VouchersApi instance = VouchersApi._();

  final _api = ApiClient.instance;

  Future<({List<VoucherItem> items, int total, int perPage})> list({
    required String voucherType,
    int page = 1,
    int perPage = 25,
  }) async {
    try {
      final res = await _api.dio.get(
        '/accounting/vouchers',
        queryParameters: {
          'voucher_type': voucherType,
          'page': page,
          'per_page': perPage,
        },
      );
      final list = _extractList(res.data);
      final items = <VoucherItem>[];
      for (final row in list) {
        if (row is Map) {
          items.add(VoucherItem.fromJson(Map<String, dynamic>.from(row)));
        }
      }
      return (
        items: items,
        total: _extractTotal(res.data, items.length),
        perPage: _extractInt(res.data, 'per_page', perPage),
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> save({int? id, required Map<String, dynamic> payload}) async {
    try {
      if (id == null) {
        await _api.dio.post('/accounting/vouchers', data: payload);
      } else {
        await _api.dio.put('/accounting/vouchers/$id', data: payload);
      }
    } on DioException catch (e) {
      throw ApiException(_voucherError(e), statusCode: e.response?.statusCode);
    }
  }

  Future<List<VoucherParty>> clients() async {
    try {
      final res = await _api.dio.get('/companies');
      return _parties(res.data, const ['name', 'company_name']);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<List<VoucherParty>> suppliers() async {
    try {
      final res = await _api.dio.get('/suppliers/supplier_names');
      final named = _parties(res.data, const ['supplier_name', 'name']);
      if (named.isNotEmpty) return named;
      final fallback = await _api.dio.get(
        '/suppliers',
        queryParameters: const {'itemsPerPage': 500, 'page': 1},
      );
      return _parties(fallback.data, const ['supplier_name', 'name']);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<List<VoucherAccountOption>> treeAccounts() async {
    try {
      final res = await _api.dio.get('/tree_accounts');
      final list = _extractList(res.data);
      final out = <VoucherAccountOption>[];
      void walk(List rows) {
        for (final row in rows) {
          if (row is! Map) continue;
          final j = Map<String, dynamic>.from(row);
          final id = int.tryParse('${j['id'] ?? ''}');
          final name = pick(j, ['name']);
          if (id != null && id > 0 && name.isNotEmpty) {
            out.add(VoucherAccountOption(id: id, name: name));
          }
          final kids = j['children'];
          if (kids is List) walk(kids);
        }
      }

      walk(list);
      return out;
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<VoucherPaymentSources> paymentSources() async {
    try {
      final res = await _api.dio.get('/accounting/payment-sources');
      final body = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      return VoucherPaymentSources(
        safes: _sourceAccounts(body['safes']),
        banks: _sourceAccounts(body['banks']),
        serviceAccounts: _sourceAccounts(body['service_accounts']),
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  List<VoucherAccountOption> _sourceAccounts(dynamic raw) {
    if (raw is! List) return const [];
    final out = <VoucherAccountOption>[];
    for (final row in raw) {
      if (row is! Map) continue;
      final j = Map<String, dynamic>.from(row);
      final accId = int.tryParse('${j['account_id'] ?? ''}');
      Map<String, dynamic>? acc;
      if (j['account'] is Map) {
        acc = Map<String, dynamic>.from(j['account'] as Map);
      }
      final id = accId ?? int.tryParse('${acc?['id'] ?? ''}');
      final name = pick(j, ['label', 'name']);
      if (id == null || id <= 0 || name.isEmpty) continue;
      out.add(VoucherAccountOption(id: id, name: name));
    }
    return out;
  }

  List<VoucherParty> _parties(dynamic data, List<String> nameKeys) {
    final list = _extractList(data);
    final out = <VoucherParty>[];
    for (final row in list) {
      if (row is! Map) continue;
      final j = Map<String, dynamic>.from(row);
      final id = int.tryParse('${j['id'] ?? ''}');
      final name = pick(j, nameKeys);
      if (id == null || id <= 0 || name.isEmpty) continue;
      out.add(VoucherParty(id: id, name: name));
    }
    return out;
  }

  String _voucherError(DioException e) {
    final data = e.response?.data;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map) {
        final parts = <String>[];
        errors.forEach((_, v) {
          if (v is List) {
            parts.addAll(v.map((x) => '$x'));
          } else if (v != null) {
            parts.add('$v');
          }
        });
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

  int _extractInt(dynamic data, String key, int fallback) {
    if (data is! Map) return fallback;
    final top = int.tryParse('${data[key] ?? ''}');
    if (top != null) return top;
    final nested = data['data'];
    if (nested is Map) {
      final n = int.tryParse('${nested[key] ?? ''}');
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

String? _nullStr(String s) => s.isEmpty || s == 'null' ? null : s;
