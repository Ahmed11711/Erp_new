import 'dart:convert';

import 'package:dio/dio.dart';

import '../core/api_client.dart';
import '../models/order.dart';
import '../models/order_filters.dart';

class PaginatedOrders {
  const PaginatedOrders({
    required this.items,
    required this.total,
    required this.currentPage,
    required this.lastPage,
  });

  final List<Order> items;
  final int total;
  final int currentPage;
  final int lastPage;
}

class LookupItem {
  const LookupItem({required this.id, required this.name});

  final String id;
  final String name;
}

class OrdersApi {
  OrdersApi._();
  static final OrdersApi instance = OrdersApi._();

  final _api = ApiClient.instance;

  Future<PaginatedOrders> search({
    OrderFilters filters = OrderFilters.empty,
    int page = 1,
    int itemsPerPage = 20,
  }) async {
    try {
      final params = <String, dynamic>{
        'page': page,
        'itemsPerPage': itemsPerPage,
        ...filters.toApiParams(),
      };
      final res = await _api.dio.get('/orders/search', queryParameters: params);
      return _parsePage(res.data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<Order> getById(String id) async {
    try {
      final res = await _api.dio.get('/orders/$id');
      final data = res.data;
      if (data is! Map) {
        throw ApiException('استجابة غير متوقعة');
      }
      return Order.fromJson(Map<String, dynamic>.from(data));
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<int> count({Map<String, dynamic> params = const {}}) async {
    try {
      final res = await _api.dio.get(
        '/orders/search',
        queryParameters: {
          'page': 1,
          'itemsPerPage': 1,
          ...params,
        },
      );
      final data = res.data;
      if (data is Map && data['total'] != null) {
        return int.tryParse('${data['total']}') ?? 0;
      }
      return 0;
    } on DioException {
      return 0;
    }
  }

  Future<void> confirm({
    required String id,
    required String date,
    String? lineId,
    String note = '',
    String maintenReason = '',
  }) async {
    await _post('/confirm/$id', {
      'date': date,
      'line_id': lineId,
      'note': note,
      'maintenReason': maintenReason,
    });
  }

  Future<void> ship({
    required String id,
    required String date,
    required String companyId,
    required List<Map<String, dynamic>> productsToShip,
    String shipmentNumber = '',
    String? paymentWay,
  }) async {
    try {
      final form = FormData.fromMap({
        'date': date,
        'company_id': companyId,
        'shippment_number': shipmentNumber,
        'productsToShip': jsonEncode(productsToShip),
        if (paymentWay != null && paymentWay.isNotEmpty) 'payment_way': paymentWay,
      });
      final res = await _api.dio.post('/shiporder/$id', data: form);
      _ensureSuccess(res.data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> deliver({
    required String id,
    String note = '',
  }) async {
    await _post('/order/$id/deliver', {'note': note});
  }

  Future<void> collect({
    required String id,
    required String paymentType,
    String? bankId,
    String? safeId,
    String note = '',
  }) async {
    try {
      final form = FormData.fromMap({
        'payment_type': paymentType,
        'note': note,
        if (bankId != null && bankId.isNotEmpty) 'bank_id': bankId,
        if (safeId != null && safeId.isNotEmpty) 'safe_id': safeId,
      });
      final res = await _api.dio.post('/collectorder/$id', data: form);
      _ensureSuccess(res.data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> changeStatus({
    required String id,
    required String status,
    String note = '',
  }) async {
    try {
      final res = await _api.dio.get(
        '/changestatus/$id',
        queryParameters: {
          'status': status,
          'note': note,
          'amount': 0,
          'bank': 0,
          'receviedOrder': 0,
        },
      );
      _ensureSuccess(res.data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<List<LookupItem>> shippingLines() async {
    return _lookupList('/shippinglines', nameKeys: ['name', 'line_name']);
  }

  Future<List<LookupItem>> shippingCompanies() async {
    return _lookupList(
      '/shippingcompanySelect',
      nameKeys: ['name', 'company_name'],
    );
  }

  Future<List<LookupItem>> banks() async {
    return _lookupList('/banks', nameKeys: ['name', 'bank_name']);
  }

  Future<List<LookupItem>> _lookupList(
    String path, {
    required List<String> nameKeys,
  }) async {
    try {
      final res = await _api.dio.get(path);
      final raw = res.data;
      List list;
      if (raw is List) {
        list = raw;
      } else if (raw is Map) {
        final data = raw['data'];
        if (data is List) {
          list = data;
        } else if (data is Map && data['data'] is List) {
          list = data['data'] as List;
        } else {
          list = const [];
        }
      } else {
        list = const [];
      }

      final items = <LookupItem>[];
      for (final row in list) {
        if (row is! Map) continue;
        final map = Map<String, dynamic>.from(row);
        final id = '${map['id'] ?? ''}'.trim();
        if (id.isEmpty) continue;
        String name = '';
        for (final k in nameKeys) {
          final v = '${map[k] ?? ''}'.trim();
          if (v.isNotEmpty) {
            name = v;
            break;
          }
        }
        items.add(LookupItem(id: id, name: name.isEmpty ? '#$id' : name));
      }
      return items;
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> _post(String path, Map<String, dynamic> body) async {
    try {
      final res = await _api.dio.post(path, data: body);
      _ensureSuccess(res.data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  void _ensureSuccess(dynamic data) {
    if (data is Map) {
      final msg = '${data['message'] ?? ''}'.toLowerCase();
      if (msg.contains('success') || msg.contains('تم') || msg.isEmpty) {
        return;
      }
      // Some endpoints return success without "success" string.
      if (data['error'] == null && data['errors'] == null) return;
      throw ApiException('${data['message'] ?? 'فشل تنفيذ الإجراء'}');
    }
  }

  PaginatedOrders _parsePage(dynamic data) {
    if (data is! Map) {
      return const PaginatedOrders(
        items: [],
        total: 0,
        currentPage: 1,
        lastPage: 1,
      );
    }
    final list = data['data'];
    final items = <Order>[];
    if (list is List) {
      for (final row in list) {
        if (row is Map) {
          items.add(Order.fromJson(Map<String, dynamic>.from(row)));
        }
      }
    }
    return PaginatedOrders(
      items: items,
      total: int.tryParse('${data['total'] ?? items.length}') ?? items.length,
      currentPage: int.tryParse('${data['current_page'] ?? 1}') ?? 1,
      lastPage: int.tryParse('${data['last_page'] ?? 1}') ?? 1,
    );
  }
}
