import 'package:dio/dio.dart';

import '../core/api_client.dart';
import 'catalog_api.dart';

const kAccountTypeLabels = <String, String>{
  'asset': 'أصول',
  'liability': 'خصوم',
  'equity': 'حقوق ملكية',
  'revenue': 'إيرادات',
  'expense': 'مصروفات',
  'settlement': 'تسوية',
};

class TreeAccountNode {
  TreeAccountNode({
    required this.id,
    required this.name,
    required this.code,
    required this.type,
    required this.balance,
    required this.level,
    this.nameEn,
    this.parentId,
    this.isTradingAccount = false,
    this.children = const [],
  });

  final int id;
  final String name;
  final String code;
  final String type;
  final double balance;
  final int level;
  final String? nameEn;
  final int? parentId;
  final bool isTradingAccount;
  final List<TreeAccountNode> children;

  String get typeLabel => kAccountTypeLabels[type] ?? type;

  /// Natural display balance (same as Angular getDisplayBalance).
  double get displayBalance {
    if (type == 'liability' ||
        type == 'equity' ||
        type == 'revenue' ||
        type == 'settlement') {
      return -balance;
    }
    return balance;
  }

  bool get hasChildren => children.isNotEmpty;

  TreeAccountNode copyWith({List<TreeAccountNode>? children}) {
    return TreeAccountNode(
      id: id,
      name: name,
      code: code,
      type: type,
      balance: balance,
      level: level,
      nameEn: nameEn,
      parentId: parentId,
      isTradingAccount: isTradingAccount,
      children: children ?? this.children,
    );
  }

  factory TreeAccountNode.fromJson(Map<String, dynamic> j) {
    final kids = <TreeAccountNode>[];
    final rawKids = j['children'];
    if (rawKids is List) {
      for (final c in rawKids) {
        if (c is Map) {
          kids.add(TreeAccountNode.fromJson(Map<String, dynamic>.from(c)));
        }
      }
    }
    return TreeAccountNode(
      id: int.tryParse('${j['id']}') ?? 0,
      name: pick(j, ['name'], fallback: 'حساب'),
      code: pick(j, ['code']),
      type: pick(j, ['type'], fallback: 'asset'),
      balance: _toDouble(j['balance']),
      level: int.tryParse('${j['level'] ?? 1}') ?? 1,
      nameEn: pick(j, ['name_en']).isEmpty ? null : pick(j, ['name_en']),
      parentId: j['parent_id'] == null ? null : int.tryParse('${j['parent_id']}'),
      isTradingAccount: j['is_trading_account'] == true ||
          j['is_trading_account'] == 1,
      children: kids,
    );
  }

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    return double.tryParse('$v') ?? 0;
  }
}

/// Chart of accounts APIs — same Laravel routes as Angular.
class AccountingTreeApi {
  AccountingTreeApi._();
  static final AccountingTreeApi instance = AccountingTreeApi._();

  final _api = ApiClient.instance;

  Future<List<TreeAccountNode>> fetchTree() async {
    try {
      final res = await _api.dio.get('/accounting/reports/accounting-tree');
      return _parseTree(res.data);
    } on DioException catch (e) {
      // Fallback to flat/list endpoint when report tree fails.
      if (e.response?.statusCode == 404 || e.response?.statusCode == 403) {
        return _fetchFlatFallback();
      }
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  /// Flat chart of accounts — Angular `GET /tree_accounts` (دليل الحسابات).
  Future<List<TreeAccountNode>> fetchAll() async {
    try {
      final res = await _api.dio.get('/tree_accounts');
      return flattenAccounts(_parseTree(res.data));
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<List<TreeAccountNode>> _fetchFlatFallback() async {
    try {
      final res = await _api.dio.get('/tree_accounts');
      return _parseTree(res.data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<TreeAccountNode> create({
    required String name,
    required String type,
    String? nameEn,
    int? parentId,
    bool isTradingAccount = false,
  }) async {
    try {
      final body = <String, dynamic>{
        'name': name,
        'type': type,
        'is_trading_account': isTradingAccount,
        'balance': 0,
        'debit_balance': 0,
        'credit_balance': 0,
      };
      if (nameEn != null && nameEn.trim().isNotEmpty) {
        body['name_en'] = nameEn.trim();
      }
      if (parentId != null) body['parent_id'] = parentId;

      final res = await _api.dio.post('/tree_accounts', data: body);
      final data = _unwrapData(res.data);
      if (data is Map) {
        return TreeAccountNode.fromJson(Map<String, dynamic>.from(data));
      }
      throw ApiException('تعذر إنشاء الحساب');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<TreeAccountNode> update({
    required int id,
    required String name,
    required String type,
    String? nameEn,
    bool isTradingAccount = false,
  }) async {
    try {
      final body = <String, dynamic>{
        'name': name,
        'type': type,
        'is_trading_account': isTradingAccount,
      };
      if (nameEn != null) body['name_en'] = nameEn.trim();

      final res = await _api.dio.put('/tree_accounts/$id', data: body);
      final data = _unwrapData(res.data);
      if (data is Map) {
        return TreeAccountNode.fromJson(Map<String, dynamic>.from(data));
      }
      throw ApiException('تعذر تحديث الحساب');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> softDelete(int id) async {
    try {
      await _api.dio.delete('/tree_accounts/$id');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  List<TreeAccountNode> _parseTree(dynamic data) {
    final list = _extractList(data);
    return list
        .whereType<Map>()
        .map((e) => TreeAccountNode.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  dynamic _unwrapData(dynamic data) {
    if (data is Map && data['data'] != null) return data['data'];
    return data;
  }

  List _extractList(dynamic data) {
    if (data is List) return data;
    if (data is! Map) return const [];
    final candidates = [data['data'], data['items'], data['accounts']];
    for (final c in candidates) {
      if (c is List) return c;
      if (c is Map && c['data'] is List) return c['data'] as List;
    }
    return const [];
  }

  /// Deduplicate nested/flat API payloads into a code-sorted directory list.
  static List<TreeAccountNode> flattenAccounts(List<TreeAccountNode> nodes) {
    final seen = <int>{};
    final out = <TreeAccountNode>[];
    void walk(List<TreeAccountNode> list) {
      for (final n in list) {
        if (seen.add(n.id)) out.add(n);
        if (n.hasChildren) walk(n.children);
      }
    }

    walk(nodes);
    out.sort((a, b) => a.code.compareTo(b.code));
    return out;
  }

  /// جذر / مجموعة / تفصيلي — same as Angular `getHierarchyRole`.
  static String hierarchyRole(
    TreeAccountNode account,
    List<TreeAccountNode> all,
  ) {
    if (account.parentId == null) return 'جذر';
    if (all.any((a) => a.parentId == account.id)) return 'مجموعة';
    return 'تفصيلي';
  }

  static List<TreeAccountNode> filterAccounts({
    required List<TreeAccountNode> accounts,
    String query = '',
    String type = '',
  }) {
    final q = query.trim().toLowerCase();
    return accounts.where((a) {
      final matchQuery = q.isEmpty ||
          a.name.toLowerCase().contains(q) ||
          a.code.toLowerCase().contains(q) ||
          (a.nameEn?.toLowerCase().contains(q) ?? false);
      final matchType = type.isEmpty || a.type == type;
      return matchQuery && matchType;
    }).toList();
  }

  /// Filter nested tree by code/name/name_en (keeps matching ancestors).
  static List<TreeAccountNode> filterTree(
    List<TreeAccountNode> roots,
    String query,
  ) {
    final q = query.trim().toLowerCase();
    if (q.isEmpty) return roots;

    List<TreeAccountNode>? filterNode(TreeAccountNode node) {
      final selfMatch = node.name.toLowerCase().contains(q) ||
          node.code.toLowerCase().contains(q) ||
          (node.nameEn?.toLowerCase().contains(q) ?? false);
      final filteredKids = <TreeAccountNode>[];
      for (final c in node.children) {
        final f = filterNode(c);
        if (f != null) filteredKids.addAll(f);
      }
      if (selfMatch || filteredKids.isNotEmpty) {
        return [node.copyWith(children: filteredKids)];
      }
      return null;
    }

    final out = <TreeAccountNode>[];
    for (final r in roots) {
      final f = filterNode(r);
      if (f != null) out.addAll(f);
    }
    return out;
  }
}
