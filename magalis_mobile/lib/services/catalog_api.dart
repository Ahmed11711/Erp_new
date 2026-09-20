import 'package:dio/dio.dart';

import '../core/api_client.dart';

class CatalogRow {
  const CatalogRow({
    required this.id,
    required this.title,
    this.subtitle,
    this.trailing,
    this.phone,
    this.isArchived = false,
    this.awaitingReply = false,
  });

  final String id;
  final String title;
  final String? subtitle;
  final String? trailing;
  /// Optional contact phone (WhatsApp conversations).
  final String? phone;
  final bool isArchived;
  final bool awaitingReply;
}

class CatalogPage {
  const CatalogPage({
    required this.items,
    required this.total,
  });

  final List<CatalogRow> items;
  final int total;
}

typedef RowMapper = CatalogRow? Function(Map<String, dynamic> json);

/// Shared list fetchers for Magalis modules (same Laravel routes as Angular).
class CatalogApi {
  CatalogApi._();
  static final CatalogApi instance = CatalogApi._();

  final _api = ApiClient.instance;

  Future<CatalogPage> fetch({
    required String path,
    required RowMapper mapRow,
    int page = 1,
    int itemsPerPage = 40,
    String? search,
    String searchParam = 'search',
    Map<String, dynamic>? extraParams,
  }) async {
    try {
      final params = <String, dynamic>{
        'page': page,
        'itemsPerPage': itemsPerPage,
        'per_page': itemsPerPage,
        ...?extraParams,
      };
      final q = search?.trim();
      if (q != null && q.isNotEmpty) {
        params[searchParam] = q;
      }

      final res = await _api.dio.get(path, queryParameters: params);
      return _parse(res.data, mapRow);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  CatalogPage _parse(dynamic data, RowMapper mapRow) {
    final list = _extractList(data);
    final items = <CatalogRow>[];
    for (final row in list) {
      if (row is Map) {
        final mapped = mapRow(Map<String, dynamic>.from(row));
        if (mapped != null) items.add(mapped);
      }
    }
    final total = _extractTotal(data, items.length);
    return CatalogPage(items: items, total: total);
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

  List _extractList(dynamic data) {
    if (data is List) return data;
    if (data is! Map) return const [];
    // Laravel paginator / JsonResource shapes
    final candidates = [data['data'], data['items'], data['customers']];
    for (final c in candidates) {
      if (c is List) return c;
      if (c is Map && c['data'] is List) return c['data'] as List;
    }
    return const [];
  }
}

/// Field helpers shared by mappers.
String pick(Map<String, dynamic> json, List<String> keys, {String fallback = ''}) {
  for (final k in keys) {
    final v = json[k];
    if (v == null) continue;
    final s = '$v'.trim();
    if (s.isNotEmpty && s != 'null') return s;
  }
  return fallback;
}

String? moneyish(dynamic v) {
  if (v == null) return null;
  final n = v is num ? v.toDouble() : double.tryParse('$v');
  if (n == null) return '$v';
  return n.toStringAsFixed(n.truncateToDouble() == n ? 0 : 2);
}
