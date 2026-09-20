import 'package:dio/dio.dart';

import '../core/api_client.dart';
import '../models/offer.dart';

class PaginatedOffers {
  const PaginatedOffers({
    required this.items,
    required this.total,
    required this.currentPage,
    required this.lastPage,
  });

  final List<Offer> items;
  final int total;
  final int currentPage;
  final int lastPage;
}

class OffersApi {
  OffersApi._();
  static final OffersApi instance = OffersApi._();

  final _api = ApiClient.instance;

  Future<PaginatedOffers> list({
    int page = 1,
    int itemsPerPage = 20,
    String? quote,
    String? companyName,
  }) async {
    try {
      final params = <String, dynamic>{
        'page': page,
        'itemsPerPage': itemsPerPage,
      };
      final q = quote?.trim();
      if (q != null && q.isNotEmpty) params['quote'] = q;
      final c = companyName?.trim();
      if (c != null && c.isNotEmpty) params['company_name'] = c;

      final res = await _api.dio.get('/offer', queryParameters: params);
      return _parsePage(res.data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<Offer> getById(String id) async {
    try {
      final res = await _api.dio.get('/offer/$id');
      final data = res.data;
      if (data is! Map) throw ApiException('استجابة غير متوقعة');
      return Offer.fromJson(Map<String, dynamic>.from(data));
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  PaginatedOffers _parsePage(dynamic data) {
    if (data is! Map) {
      return const PaginatedOffers(
        items: [],
        total: 0,
        currentPage: 1,
        lastPage: 1,
      );
    }
    final list = data['data'];
    final items = <Offer>[];
    if (list is List) {
      for (final row in list) {
        if (row is Map) {
          items.add(Offer.fromJson(Map<String, dynamic>.from(row)));
        }
      }
    }
    return PaginatedOffers(
      items: items,
      total: int.tryParse('${data['total'] ?? items.length}') ?? items.length,
      currentPage: int.tryParse('${data['current_page'] ?? 1}') ?? 1,
      lastPage: int.tryParse('${data['last_page'] ?? 1}') ?? 1,
    );
  }
}
