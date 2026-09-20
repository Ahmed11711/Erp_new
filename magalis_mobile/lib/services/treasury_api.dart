import 'package:dio/dio.dart';

import '../core/api_client.dart';
import 'accounting_tree_api.dart';
import 'catalog_api.dart';

class AccountOption {
  const AccountOption({required this.id, required this.label});

  final int id;
  final String label;
}

class DirectoryUser {
  const DirectoryUser({required this.id, required this.name, this.department});

  final int id;
  final String name;
  final String? department;
}

class BankItem {
  BankItem({
    required this.id,
    required this.name,
    required this.balance,
    this.usage,
    this.type,
    this.assetId,
    this.assetName,
    this.assignedUsers = const [],
    this.isRestricted = false,
  });

  final int id;
  final String name;
  final double balance;
  final String? usage;
  final String? type;
  final int? assetId;
  final String? assetName;
  final List<DirectoryUser> assignedUsers;
  final bool isRestricted;

  String get assignedLabel {
    if (assignedUsers.isEmpty) return 'الجميع';
    return assignedUsers.map((u) => u.name).join('، ');
  }

  factory BankItem.fromJson(Map<String, dynamic> j) {
    final assigned = <DirectoryUser>[];
    final raw = j['assigned_users'] ?? j['assignedUsers'];
    if (raw is List) {
      for (final u in raw) {
        if (u is Map) {
          final m = Map<String, dynamic>.from(u);
          final id = int.tryParse('${m['id']}') ?? 0;
          if (id > 0) {
            assigned.add(
              DirectoryUser(
                id: id,
                name: pick(m, ['name'], fallback: 'مستخدم'),
                department: pick(m, ['department']).isEmpty
                    ? null
                    : pick(m, ['department']),
              ),
            );
          }
        }
      }
    }
    String? assetName;
    int? assetId = j['asset_id'] == null ? null : int.tryParse('${j['asset_id']}');
    final asset = j['asset'];
    if (asset is Map) {
      final am = Map<String, dynamic>.from(asset);
      assetName = pick(am, ['name']).isEmpty ? null : pick(am, ['name']);
      assetId ??= int.tryParse('${am['id']}');
    }
    return BankItem(
      id: int.tryParse('${j['id']}') ?? 0,
      name: pick(j, ['name'], fallback: 'بنك'),
      balance: _num(j['balance']),
      usage: pick(j, ['usage']).isEmpty ? null : pick(j, ['usage']),
      type: pick(j, ['type']).isEmpty ? null : pick(j, ['type']),
      assetId: assetId,
      assetName: assetName,
      assignedUsers: assigned,
      isRestricted: j['is_restricted'] == true ||
          j['is_restricted'] == 1 ||
          assigned.isNotEmpty,
    );
  }
}

class SafeItem {
  SafeItem({
    required this.id,
    required this.name,
    required this.balance,
    required this.type,
    this.branchName,
    this.isInsideBranch = false,
    this.accountId,
    this.accountName,
    this.accountCode,
    this.parentAccountName,
  });

  final int id;
  final String name;
  final double balance;
  final String type;
  final String? branchName;
  final bool isInsideBranch;
  final int? accountId;
  final String? accountName;
  final String? accountCode;
  final String? parentAccountName;

  bool get isMain => type == 'main';

  factory SafeItem.fromJson(Map<String, dynamic> j) {
    String? accName;
    String? accCode;
    String? parentName;
    int? accId = j['account_id'] == null ? null : int.tryParse('${j['account_id']}');
    final acc = j['account'];
    if (acc is Map) {
      final am = Map<String, dynamic>.from(acc);
      accName = pick(am, ['name']).isEmpty ? null : pick(am, ['name']);
      accCode = pick(am, ['code']).isEmpty ? null : pick(am, ['code']);
      accId ??= int.tryParse('${am['id']}');
      final parent = am['parent'];
      if (parent is Map) {
        parentName = pick(Map<String, dynamic>.from(parent), ['name']);
        if (parentName.isEmpty) parentName = null;
      }
    }
    return SafeItem(
      id: int.tryParse('${j['id']}') ?? 0,
      name: pick(j, ['name'], fallback: 'خزينة'),
      balance: _num(j['balance']),
      type: pick(j, ['type'], fallback: 'main'),
      branchName:
          pick(j, ['branch_name']).isEmpty ? null : pick(j, ['branch_name']),
      isInsideBranch:
          j['is_inside_branch'] == true || j['is_inside_branch'] == 1,
      accountId: accId,
      accountName: accName,
      accountCode: accCode,
      parentAccountName: parentName,
    );
  }
}

class ServiceAccountItem {
  ServiceAccountItem({
    required this.id,
    required this.name,
    required this.balance,
    this.accountNumber,
    this.description,
    this.img,
    this.accountId,
  });

  final int id;
  final String name;
  final double balance;
  final String? accountNumber;
  final String? description;
  final String? img;
  final int? accountId;

  factory ServiceAccountItem.fromJson(Map<String, dynamic> j) {
    int? accId = j['account_id'] == null ? null : int.tryParse('${j['account_id']}');
    final acc = j['account'];
    if (acc is Map && accId == null) {
      accId = int.tryParse('${acc['id']}');
    }
    return ServiceAccountItem(
      id: int.tryParse('${j['id']}') ?? 0,
      name: pick(j, ['name'], fallback: 'حساب خدمي'),
      balance: _num(j['balance']),
      accountNumber: pick(j, ['account_number']).isEmpty
          ? null
          : pick(j, ['account_number']),
      description:
          pick(j, ['description']).isEmpty ? null : pick(j, ['description']),
      img: pick(j, ['img']).isEmpty ? null : pick(j, ['img']),
      accountId: accId,
    );
  }
}

class DirectTxnItem {
  DirectTxnItem({
    required this.id,
    required this.type,
    required this.amount,
    required this.date,
    required this.editable,
    this.bankId,
    this.safeId,
    this.bankName,
    this.safeName,
    this.counterAccountId,
    this.counterAccountName,
    this.counterAccountCode,
    this.notes,
  });

  final int id;
  final String type;
  final double amount;
  final String date;
  final bool editable;
  final int? bankId;
  final int? safeId;
  final String? bankName;
  final String? safeName;
  final int? counterAccountId;
  final String? counterAccountName;
  final String? counterAccountCode;
  final String? notes;

  bool get isReceipt => type == 'receipt';

  factory DirectTxnItem.fromJson(Map<String, dynamic> j) {
    return DirectTxnItem(
      id: int.tryParse('${j['id']}') ?? 0,
      type: pick(j, ['type'], fallback: 'receipt'),
      amount: _num(j['amount']),
      date: pick(j, ['date']),
      editable: j['editable'] == true || j['editable'] == 1,
      bankId: j['bank_id'] == null ? null : int.tryParse('${j['bank_id']}'),
      safeId: j['safe_id'] == null ? null : int.tryParse('${j['safe_id']}'),
      bankName: pick(j, ['bank_name']).isEmpty ? null : pick(j, ['bank_name']),
      safeName: pick(j, ['safe_name']).isEmpty ? null : pick(j, ['safe_name']),
      counterAccountId: j['counter_account_id'] == null
          ? null
          : int.tryParse('${j['counter_account_id']}'),
      counterAccountName: pick(j, ['counter_account_name']).isEmpty
          ? null
          : pick(j, ['counter_account_name']),
      counterAccountCode: pick(j, ['counter_account_code']).isEmpty
          ? null
          : pick(j, ['counter_account_code']),
      notes: pick(j, ['notes']).isEmpty ? null : pick(j, ['notes']),
    );
  }
}

class PendingBankItem {
  PendingBankItem({
    required this.id,
    required this.type,
    required this.status,
    required this.amount,
    this.details,
    this.ref,
    this.bankId,
    this.bankName,
    this.userName,
    this.createdAt,
  });

  final int id;
  final String type;
  final String status;
  final double amount;
  final String? details;
  final String? ref;
  final int? bankId;
  final String? bankName;
  final String? userName;
  final String? createdAt;

  bool get isPending => status == 'pending' || status == 'قيد الانتظار';

  String get statusLabel {
    switch (status) {
      case 'approved':
        return 'تم الموافقة';
      case 'rejected':
        return 'مرفوضه';
      case 'pending':
        return 'قيد الانتظار';
      default:
        return status;
    }
  }

  factory PendingBankItem.fromJson(Map<String, dynamic> j) {
    String? bankName;
    int? bankId = j['bank_id'] == null ? null : int.tryParse('${j['bank_id']}');
    final bank = j['bank'];
    if (bank is Map) {
      bankName = pick(Map<String, dynamic>.from(bank), ['name']);
      bankId ??= int.tryParse('${bank['id']}');
    }
    String userNameStr = '';
    final user = j['user'];
    if (user is Map) {
      userNameStr = pick(Map<String, dynamic>.from(user), ['name']);
    } else {
      userNameStr = pick(j, ['user']);
    }
    return PendingBankItem(
      id: int.tryParse('${j['id']}') ?? 0,
      type: pick(j, ['type']),
      status: pick(j, ['status']),
      amount: _num(j['amount']),
      details: pick(j, ['details']).isEmpty ? null : pick(j, ['details']),
      ref: pick(j, ['ref']).isEmpty ? null : pick(j, ['ref']),
      bankId: bankId,
      bankName: (bankName == null || bankName.isEmpty) ? null : bankName,
      userName: userNameStr.isEmpty ? null : userNameStr,
      createdAt: pick(j, ['created_at']).isEmpty ? null : pick(j, ['created_at']),
    );
  }
}

double _num(dynamic v) {
  if (v == null) return 0;
  if (v is num) return v.toDouble();
  return double.tryParse('$v') ?? 0;
}

/// Banks, safes, service accounts, pending — same Laravel routes as Angular.
class TreasuryApi {
  TreasuryApi._();
  static final TreasuryApi instance = TreasuryApi._();

  final _api = ApiClient.instance;

  Future<List<AccountOption>> fetchAccountOptions() async {
    final nodes = await AccountingTreeApi.instance.fetchAll();
    final out = nodes
        .map(
          (n) => AccountOption(
            id: n.id,
            label: n.code.isEmpty ? n.name : '${n.name} — ${n.code}',
          ),
        )
        .toList();
    out.sort((a, b) => a.label.compareTo(b.label));
    return out;
  }

  Future<List<BankItem>> fetchBanks() async {
    try {
      final res = await _api.dio.get(
        '/accounting/banks',
        queryParameters: const {'per_page': 200},
      );
      return _maps(res.data).map(BankItem.fromJson).toList();
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<List<SafeItem>> fetchSafes() async {
    try {
      final res = await _api.dio.get(
        '/accounting/safes',
        queryParameters: const {'per_page': 200},
      );
      return _maps(res.data).map(SafeItem.fromJson).toList();
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> createBank(Map<String, dynamic> body) async {
    try {
      await _api.dio.post('/accounting/banks', data: body);
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> updateBank(int id, Map<String, dynamic> body) async {
    try {
      await _api.dio.put('/accounting/banks/$id', data: body);
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> deleteBank(int id) async {
    try {
      await _api.dio.delete('/accounting/banks/$id');
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> createSafe(Map<String, dynamic> body) async {
    try {
      await _api.dio.post('/accounting/safes', data: body);
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> updateSafe(int id, Map<String, dynamic> body) async {
    try {
      await _api.dio.put('/accounting/safes/$id', data: body);
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> deleteSafe(int id) async {
    try {
      await _api.dio.delete('/accounting/safes/$id');
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> bankTransfer(Map<String, dynamic> body) async {
    try {
      await _api.dio.post('/accounting/banks/transfer', data: body);
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> safeTransfer({
    required int fromSafeId,
    required int toSafeId,
    required double amount,
    String? notes,
  }) async {
    try {
      await _api.dio.post(
        '/accounting/safes/transfer',
        data: {
          'from_safe_id': fromSafeId,
          'to_safe_id': toSafeId,
          'amount': amount,
          if (notes != null && notes.isNotEmpty) 'notes': notes,
        },
      );
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> bankDirectTxn(Map<String, dynamic> body, {int? editId}) async {
    try {
      if (editId != null) {
        await _api.dio.put(
          '/accounting/banks/direct-transactions/$editId',
          data: body,
        );
      } else {
        await _api.dio.post('/accounting/banks/direct-transaction', data: body);
      }
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> safeDirectTxn(Map<String, dynamic> body) async {
    try {
      await _api.dio.post('/accounting/safes/direct-transaction', data: body);
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<List<DirectTxnItem>> bankDirectHistory({int? bankId}) async {
    try {
      final params = <String, dynamic>{'per_page': 50};
      if (bankId != null) params['bank_id'] = bankId;
      final res = await _api.dio.get(
        '/accounting/banks/direct-transactions',
        queryParameters: params,
      );
      return _maps(res.data).map(DirectTxnItem.fromJson).toList();
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<List<DirectoryUser>> compactDirectory() async {
    try {
      final res = await _api.dio.get('/users/compact-directory');
      return _maps(res.data)
          .map((m) {
            final id = int.tryParse('${m['id']}') ?? 0;
            return DirectoryUser(
              id: id,
              name: pick(m, ['name'], fallback: 'مستخدم'),
              department: pick(m, ['department']).isEmpty
                  ? null
                  : pick(m, ['department']),
            );
          })
          .where((u) => u.id > 0)
          .toList();
    } on DioException {
      return const [];
    }
  }

  Future<void> syncBankUsers(int bankId, List<int> userIds) async {
    try {
      await _api.dio.put(
        '/accounting/banks/$bankId/users',
        data: {'user_ids': userIds},
      );
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<List<ServiceAccountItem>> fetchServiceAccounts() async {
    try {
      final res = await _api.dio.get('/accounting/service-accounts');
      return _maps(res.data).map(ServiceAccountItem.fromJson).toList();
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> createServiceAccount(Map<String, dynamic> body) async {
    try {
      await _api.dio.post('/accounting/service-accounts', data: body);
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> updateServiceAccount(int id, Map<String, dynamic> body) async {
    try {
      await _api.dio.put('/accounting/service-accounts/$id', data: body);
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> deleteServiceAccount(int id) async {
    try {
      await _api.dio.delete('/accounting/service-accounts/$id');
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> transferServiceAccount({
    required int fromId,
    required int toId,
    required double amount,
    String? notes,
  }) async {
    try {
      await _api.dio.post(
        '/accounting/service-accounts/transfer',
        data: {
          'from_account_id': fromId,
          'to_account_id': toId,
          'amount': amount,
          if (notes != null && notes.isNotEmpty) 'notes': notes,
        },
      );
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<List<BankItem>> fetchBankSelect() async {
    try {
      final res = await _api.dio.get('/bankSelect');
      return _maps(res.data).map(BankItem.fromJson).toList();
    } on DioException {
      return fetchBanks();
    }
  }

  Future<({List<PendingBankItem> items, int total})> fetchPending({
    int page = 1,
    int perPage = 30,
    String? status,
  }) async {
    try {
      final params = <String, dynamic>{
        'itemsPerPage': perPage,
        'page': page,
      };
      if (status != null && status.isNotEmpty) params['status'] = status;
      final res = await _api.dio.get('/pendingBanks', queryParameters: params);
      final data = res.data;
      final items = _maps(data).map(PendingBankItem.fromJson).toList();
      var total = items.length;
      if (data is Map) {
        total = int.tryParse('${data['total'] ?? total}') ?? total;
      }
      return (items: items, total: total);
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  Future<void> setPendingStatus({
    required int id,
    required String status,
    int? bankId,
  }) async {
    try {
      final body = <String, dynamic>{'id': id, 'status': status};
      if (bankId != null) body['bank_id'] = bankId;
      await _api.dio.post('/pendingBanks', data: body);
    } on DioException catch (e) {
      throw ApiException(_err(e), statusCode: e.response?.statusCode);
    }
  }

  static List<BankItem> filterBanks(List<BankItem> items, String query) {
    final q = query.trim().toLowerCase();
    if (q.isEmpty) return items;
    return items.where((b) {
      return b.name.toLowerCase().contains(q) ||
          (b.usage?.toLowerCase().contains(q) ?? false) ||
          (b.assetName?.toLowerCase().contains(q) ?? false);
    }).toList();
  }

  static List<SafeItem> filterSafes(List<SafeItem> items, String query) {
    final q = query.trim().toLowerCase();
    if (q.isEmpty) return items;
    return items.where((s) {
      return s.name.toLowerCase().contains(q) ||
          (s.branchName?.toLowerCase().contains(q) ?? false) ||
          (s.accountName?.toLowerCase().contains(q) ?? false);
    }).toList();
  }

  List<Map<String, dynamic>> _maps(dynamic data) {
    return _extractList(data)
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
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

  String _err(DioException e) {
    final data = e.response?.data;
    if (data is Map) {
      final msg = data['message'];
      if (msg is String && msg.isNotEmpty) return msg;
      final errors = data['errors'];
      if (errors is Map) {
        final parts = <String>[];
        errors.forEach((_, v) {
          if (v is List) {
            parts.addAll(v.map((x) => x.toString()));
          } else if (v != null) {
            parts.add(v.toString());
          }
        });
        if (parts.isNotEmpty) return parts.join('\n');
      }
    }
    return ApiClient.messageFrom(e);
  }
}
