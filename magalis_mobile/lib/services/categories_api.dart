import 'package:dio/dio.dart';

import '../config/api_config.dart';
import '../core/api_client.dart';
import 'catalog_api.dart';
import 'inventory_api.dart';

class CategoryItem {
  CategoryItem({
    required this.id,
    required this.name,
    this.itemCode,
    this.warehouse,
    this.productionLine,
    this.classificationName,
    this.unit,
    this.price = 0,
    this.quantity = 0,
    this.totalPrice = 0,
    this.sellTotalPrice = 0,
    this.unitPrice = 0,
    this.image,
  });

  final String id;
  final String name;
  final String? itemCode;
  final String? warehouse;
  final String? productionLine;
  final String? classificationName;
  final String? unit;
  double price;
  double quantity;
  double totalPrice;
  double sellTotalPrice;
  double unitPrice;
  final String? image;

  double get averageUnitCost {
    if (quantity > 0.0000001) return totalPrice / quantity;
    if (unitPrice > 0.0000001) return unitPrice;
    return 0;
  }

  String? get imageUrl {
    final img = image?.trim();
    if (img == null || img.isEmpty) return null;
    if (img.startsWith('http')) return img;
    return '${ApiConfig.imgUrl}$img';
  }

  factory CategoryItem.fromJson(Map<String, dynamic> j) {
    final production = j['production'] is Map
        ? Map<String, dynamic>.from(j['production'] as Map)
        : <String, dynamic>{};
    final classification = j['item_classification'] is Map
        ? Map<String, dynamic>.from(j['item_classification'] as Map)
        : <String, dynamic>{};
    final measurement = j['measurement'] is Map
        ? Map<String, dynamic>.from(j['measurement'] as Map)
        : <String, dynamic>{};
    final stock = j['stock'] is Map
        ? Map<String, dynamic>.from(j['stock'] as Map)
        : <String, dynamic>{};

    return CategoryItem(
      id: pick(j, ['id'], fallback: '0'),
      name: pick(j, ['category_name', 'name'], fallback: 'صنف'),
      itemCode: _nullIfEmpty(pick(j, ['item_code', 'code'])),
      warehouse: _nullIfEmpty(pick(j, ['warehouse'])) ??
          _nullIfEmpty(pick(stock, ['name'])),
      productionLine: _nullIfEmpty(
        pick(production, ['production_line', 'name']),
      ),
      classificationName: _nullIfEmpty(
        pick(classification, ['classification_name', 'name']),
      ),
      unit: _nullIfEmpty(pick(measurement, ['unit'])),
      price: _toDouble(j['category_price'] ?? j['price']),
      quantity: _toDouble(j['quantity']),
      totalPrice: _toDouble(j['total_price']),
      sellTotalPrice: _toDouble(j['sell_total_price']),
      unitPrice: _toDouble(j['unit_price']),
      image: _nullIfEmpty(pick(j, ['category_image', 'image'])),
    );
  }

  static String? _nullIfEmpty(String s) => s.isEmpty ? null : s;

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    return double.tryParse('$v') ?? 0;
  }
}

class LookupOption {
  const LookupOption({
    required this.id,
    required this.label,
    this.warehouse,
  });

  final String id;
  final String label;
  final String? warehouse;
}

/// Categories module APIs — same Laravel routes as Angular `CategoryService`.
class CategoriesApi {
  CategoriesApi._();
  static final CategoriesApi instance = CategoriesApi._();

  final _api = ApiClient.instance;

  Future<InventoryPage<CategoryItem>> search({
    int page = 1,
    int itemsPerPage = 50,
    String? categoryName,
    String? warehouse,
    String? productionId,
    String? classificationId,
  }) async {
    try {
      final params = <String, dynamic>{
        'page': page,
        'itemsPerPage': itemsPerPage,
      };
      final name = categoryName?.trim();
      if (name != null && name.isNotEmpty) params['category_name'] = name;
      if (warehouse != null && warehouse.isNotEmpty) {
        params['warehouse'] = warehouse;
      }
      if (productionId != null && productionId.isNotEmpty) {
        params['production_id'] = productionId;
      }
      if (classificationId != null && classificationId.isNotEmpty) {
        params['item_classification_id'] = classificationId;
      }

      final res = await _api.dio.get(
        '/categories/search',
        queryParameters: params,
      );
      final list = _extractList(res.data);
      final items = <CategoryItem>[];
      for (final row in list) {
        if (row is Map) {
          items.add(CategoryItem.fromJson(Map<String, dynamic>.from(row)));
        }
      }
      final total = res.data is Map
          ? int.tryParse('${res.data['total'] ?? items.length}') ?? items.length
          : items.length;
      return InventoryPage(items: items, total: total);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<List<LookupOption>> productions() async {
    try {
      final res = await _api.dio.get('/productions');
      final list = res.data is List ? res.data as List : _extractList(res.data);
      final out = <LookupOption>[];
      for (final row in list) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        final id = pick(j, ['id']);
        final label = pick(j, ['production_line', 'name']);
        final wh = pick(j, ['warehouse']);
        if (id.isNotEmpty && label.isNotEmpty) {
          out.add(LookupOption(
            id: id,
            label: label,
            warehouse: wh.isEmpty ? null : wh,
          ));
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

  Future<List<LookupOption>> classifications() async {
    try {
      final res = await _api.dio.get('/item-classifications');
      final list = res.data is List ? res.data as List : _extractList(res.data);
      final out = <LookupOption>[];
      for (final row in list) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        final id = pick(j, ['id']);
        final name = pick(j, ['classification_name', 'name']);
        final wh = pick(j, ['warehouse']);
        if (id.isEmpty || name.isEmpty) continue;
        out.add(
          LookupOption(
            id: id,
            label: name,
            warehouse: wh.isEmpty ? null : wh,
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

  Future<List<LookupOption>> measurements() async {
    try {
      final res = await _api.dio.get('/measurements');
      final list = res.data is List ? res.data as List : _extractList(res.data);
      final out = <LookupOption>[];
      for (final row in list) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        final id = pick(j, ['id']);
        final label = pick(j, ['unit', 'name']);
        final wh = pick(j, ['warehouse']);
        if (id.isNotEmpty && label.isNotEmpty) {
          out.add(LookupOption(
            id: id,
            label: label,
            warehouse: wh.isEmpty ? null : wh,
          ));
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

  Future<void> createMeasurement({
    required String warehouse,
    required String unit,
  }) async {
    try {
      await _api.dio.post('/measurements', data: {
        'warehouse': warehouse,
        'unit': unit.trim(),
      });
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> deleteMeasurement(String id) async {
    try {
      await _api.dio.delete('/measurements/$id');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> createClassification({
    required String warehouse,
    required String classificationName,
  }) async {
    try {
      await _api.dio.post('/item-classifications', data: {
        'warehouse': warehouse,
        'classification_name': classificationName.trim(),
      });
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> deleteClassification(String id) async {
    try {
      await _api.dio.delete('/item-classifications/$id');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> createProduction({
    required String warehouse,
    required String productionLine,
  }) async {
    try {
      await _api.dio.post('/productions', data: {
        'warehouse': warehouse,
        'production_line': productionLine.trim(),
      });
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> deleteProduction(String id) async {
    try {
      await _api.dio.delete('/productions/$id');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<String?> nextItemCode(String warehouse) async {
    try {
      final res = await _api.dio.get(
        '/categories/next-item-code',
        queryParameters: {'warehouse': warehouse},
      );
      final data = res.data;
      if (data is Map && data['item_code'] != null) {
        final code = data['item_code'].toString().trim();
        return code.isEmpty ? null : code;
      }
    } on DioException {
      return null;
    }
    return null;
  }

  /// Create category — `POST /categories` (Angular إضافة صنف, multipart FormData).
  Future<void> addCategory({
    required String categoryName,
    required double categoryPrice,
    required double initialBalance,
    required double minimumQuantity,
    required String warehouse,
    required String measurementId,
    required String productionId,
    String? itemCode,
    String? color,
    String? itemClassificationId,
    int? stockId,
  }) async {
    try {
      final map = <String, dynamic>{
        'category_name': categoryName.trim(),
        'category_price': categoryPrice,
        'initial_balance': initialBalance,
        'minimum_quantity': minimumQuantity,
        'warehouse': warehouse,
        'measurement_id': measurementId,
        'production_id': productionId,
        'item_code': (itemCode ?? '').trim(),
        'color': (color ?? '').trim(),
        'allow_wip_sale': '0',
      };
      if (itemClassificationId != null && itemClassificationId.isNotEmpty) {
        map['item_classification_id'] = itemClassificationId;
      }
      if (stockId != null) map['stock_id'] = stockId;

      await _api.dio.post(
        '/categories',
        data: FormData.fromMap(map),
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> updateQuantity({
    required String id,
    required double quantity,
  }) async {
    try {
      final res = await _api.dio.patch(
        '/categories/$id/quantity',
        data: {'quantity': quantity},
      );
      final data = res.data;
      if (data is Map && data['success'] == false) {
        throw ApiException('${data['message'] ?? 'فشل تحديث الرصيد'}');
      }
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> updateAverageUnitCost({
    required String id,
    required double averageUnitCost,
  }) async {
    try {
      final res = await _api.dio.patch(
        '/categories/$id/average-unit-cost',
        data: {'average_unit_cost': averageUnitCost},
      );
      final data = res.data;
      if (data is Map && data['success'] == false) {
        throw ApiException('${data['message'] ?? 'فشل تحديث متوسط التكلفة'}');
      }
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> deleteCategory(String id) async {
    try {
      await _api.dio.delete('/deletecategory/$id');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  /// Per-item movement history — `GET /categories/categories_details/{id}`.
  Future<({String name, InventoryPage<WarehouseMovement> page})> categoryMovements({
    required String id,
    int page = 1,
    int itemsPerPage = 30,
    String? ref,
  }) async {
    try {
      final params = <String, dynamic>{
        'page': page,
        'itemsPerPage': itemsPerPage,
      };
      if (ref != null && ref.trim().isNotEmpty) params['ref'] = ref.trim();

      final res = await _api.dio.get(
        '/categories/categories_details/$id',
        queryParameters: params,
      );
      final data = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      final name = pick(data, ['name'], fallback: 'صنف');
      final details = data['details'];
      final list = _extractList(details ?? data);
      final items = <WarehouseMovement>[];
      for (final row in list) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        final rawDate = j['movement_date'] ?? j['created_at'];
        DateTime? dt;
        if (rawDate != null) {
          dt = DateTime.tryParse('$rawDate'.replaceFirst(' ', 'T'));
        }
        items.add(
          WarehouseMovement(
            id: pick(j, ['id'], fallback: '${items.length}'),
            categoryName: pick(j, ['category_name', 'name'], fallback: name),
            type: pick(j, ['type'], fallback: ''),
            quantity: _toD(j['quantity']),
            balanceBefore: _nullable(j['balance_before']),
            balanceAfter: _nullable(j['balance_after']),
            price: _nullable(j['price']),
            totalPrice: _nullable(j['total_price']),
            movementDate: dt,
            partyName: _nullStr(pick(j, ['party_name'])),
            invoiceNumber: _nullStr(pick(j, ['invoice_number'])),
            ref: _nullStr(pick(j, ['ref'])),
            status: _nullStr(pick(j, ['status'])),
            by: _nullStr(pick(j, ['by'])),
          ),
        );
      }
      final total = details is Map
          ? int.tryParse('${details['total'] ?? items.length}') ?? items.length
          : items.length;
      return (
        name: name,
        page: InventoryPage(items: items, total: total),
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  /// أصناف مخزن معيّن — `GET /categories/categoryByWarehouse` (Angular getCatBywarehouse).
  Future<List<CategoryItem>> byWarehouse(String warehouse) async {
    try {
      final res = await _api.dio.get(
        '/categories/categoryByWarehouse',
        queryParameters: {'warehouse': warehouse},
      );
      final list = res.data is List ? res.data as List : _extractList(res.data);
      final out = <CategoryItem>[];
      for (final row in list) {
        if (row is Map) {
          out.add(CategoryItem.fromJson(Map<String, dynamic>.from(row)));
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

  Future<List<CategoryItem>> allCategories() async {
    try {
      final res = await _api.dio.get('/allcategories');
      final list = res.data is List ? res.data as List : _extractList(res.data);
      final out = <CategoryItem>[];
      for (final row in list) {
        if (row is Map) {
          out.add(CategoryItem.fromJson(Map<String, dynamic>.from(row)));
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

  List _extractList(dynamic data) {
    if (data is List) return data;
    if (data is! Map) return const [];
    final candidates = [data['data'], data['items'], data['details']];
    for (final c in candidates) {
      if (c is List) return c;
      if (c is Map && c['data'] is List) return c['data'] as List;
    }
    return const [];
  }

  static double _toD(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    return double.tryParse('$v') ?? 0;
  }

  static double? _nullable(dynamic v) {
    if (v == null) return null;
    if (v is num) return v.toDouble();
    return double.tryParse('$v');
  }

  static String? _nullStr(String s) => s.isEmpty ? null : s;
}
