import 'package:dio/dio.dart';

import '../core/api_client.dart';
import 'catalog_api.dart';

class ManufactureProductOption {
  const ManufactureProductOption({
    required this.id,
    required this.name,
    this.cost = 0,
    this.quantity = 0,
    this.itemCode,
    this.warehouse,
    this.color,
  });

  final String id;
  final String name;
  final double cost;
  final double quantity;
  final String? itemCode;
  final String? warehouse;
  final String? color;

  factory ManufactureProductOption.fromJson(Map<String, dynamic> j) {
    return ManufactureProductOption(
      id: pick(j, ['id']),
      name: pick(j, ['category_name', 'name'], fallback: 'منتج'),
      cost: _toD(j['cost'] ?? j['category_price'] ?? j['unit_price']),
      quantity: _toD(j['quantity']),
      itemCode: _nullIfEmpty(pick(j, ['item_code', 'code'])),
      warehouse: _nullIfEmpty(pick(j, ['warehouse'])),
      color: _nullIfEmpty(pick(j, ['color'])),
    );
  }
}

class BomRow {
  const BomRow({
    required this.id,
    required this.productId,
    required this.productName,
    this.itemCode,
    this.color,
    this.warehouse,
    this.balance,
    this.minQty,
    required this.total,
  });

  final String id;
  final String productId;
  final String productName;
  final String? itemCode;
  final String? color;
  final String? warehouse;
  final String? balance;
  final String? minQty;
  final double total;

  factory BomRow.fromJson(Map<String, dynamic> j) {
    Map<String, dynamic> p = {};
    if (j['product'] is Map) p = Map<String, dynamic>.from(j['product'] as Map);
    return BomRow(
      id: pick(j, ['id']),
      productId: pick(j, ['product_id'], fallback: pick(p, ['id'])),
      productName: pick(p, ['category_name', 'name'], fallback: 'منتج'),
      itemCode: _nullIfEmpty(pick(p, ['item_code', 'code'])),
      color: _nullIfEmpty(pick(p, ['color'])),
      warehouse: _nullIfEmpty(pick(p, ['warehouse'])),
      balance: _nullIfEmpty(pick(p, ['initial_balance', 'quantity'])),
      minQty: _nullIfEmpty(pick(p, ['minimum_quantity'])),
      total: _toD(j['total']),
    );
  }
}

class ItemWithoutRecipe {
  const ItemWithoutRecipe({
    required this.id,
    required this.name,
    this.itemCode,
    this.warehouse,
    this.productTypeLabel,
    this.color,
    this.quantity = 0,
    this.price = 0,
  });

  final String id;
  final String name;
  final String? itemCode;
  final String? warehouse;
  final String? productTypeLabel;
  final String? color;
  final double quantity;
  final double price;

  factory ItemWithoutRecipe.fromJson(Map<String, dynamic> j) {
    return ItemWithoutRecipe(
      id: pick(j, ['id']),
      name: pick(j, ['category_name', 'name'], fallback: 'صنف'),
      itemCode: _nullIfEmpty(pick(j, ['item_code', 'code'])),
      warehouse: _nullIfEmpty(pick(j, ['warehouse'])),
      productTypeLabel: _nullIfEmpty(pick(j, ['product_type_label', 'product_type'])),
      color: _nullIfEmpty(pick(j, ['color'])),
      quantity: _toD(j['quantity']),
      price: _toD(j['category_price'] ?? j['unit_price']),
    );
  }
}

class ManufactureOrder {
  const ManufactureOrder({
    required this.id,
    required this.date,
    required this.status,
    required this.productName,
    required this.quantity,
    required this.total,
    this.userName,
    this.deletedAt,
    this.deletedByName,
  });

  final String id;
  final String date;
  final String status;
  final String productName;
  final double quantity;
  final double total;
  final String? userName;
  final String? deletedAt;
  final String? deletedByName;

  factory ManufactureOrder.fromJson(Map<String, dynamic> j) {
    Map<String, dynamic> product = {};
    Map<String, dynamic> user = {};
    Map<String, dynamic> deletedBy = {};
    if (j['product'] is Map) {
      product = Map<String, dynamic>.from(j['product'] as Map);
    }
    if (j['user'] is Map) user = Map<String, dynamic>.from(j['user'] as Map);
    final delRel = j['deletedByUser'] ?? j['deleted_by_user'];
    if (delRel is Map) deletedBy = Map<String, dynamic>.from(delRel);
    return ManufactureOrder(
      id: pick(j, ['id']),
      date: pick(j, ['date']),
      status: pick(j, ['status'], fallback: '-'),
      productName: pick(product, ['category_name', 'name'], fallback: 'منتج'),
      quantity: _toD(j['quantity']),
      total: _toD(j['total']),
      userName: _nullIfEmpty(pick(user, ['name'])),
      deletedAt: _nullIfEmpty(pick(j, ['deleted_at'])),
      deletedByName: _nullIfEmpty(pick(deletedBy, ['name'])),
    );
  }
}

class ConsumptionLine {
  ConsumptionLine({
    required this.lineKey,
    required this.bomItemId,
    required this.resolvedCategoryId,
    this.defaultResolvedCategoryId,
    required this.itemName,
    this.defaultItemName,
    this.warehouse,
    required this.bomUnitQty,
    required this.defaultQuantity,
    required this.quantity,
    required this.unitCost,
    required this.availableQuantity,
    required this.sufficient,
    this.isCustomized = false,
    this.isSubstituted = false,
  });

  final String lineKey;
  final int bomItemId;
  int resolvedCategoryId;
  final int? defaultResolvedCategoryId;
  String itemName;
  final String? defaultItemName;
  final String? warehouse;
  final double bomUnitQty;
  final double defaultQuantity;
  double quantity;
  double unitCost;
  double availableQuantity;
  bool sufficient;
  bool isCustomized;
  bool isSubstituted;

  double get lineCost => quantity * unitCost;

  factory ConsumptionLine.fromJson(Map<String, dynamic> j) {
    final resolved = _toInt(j['resolved_category_id']);
    final defaultResolved = j['default_resolved_category_id'] == null
        ? resolved
        : _toInt(j['default_resolved_category_id']);
    return ConsumptionLine(
      lineKey: pick(j, ['line_key'], fallback: '$resolved'),
      bomItemId: _toInt(j['bom_item_id']),
      resolvedCategoryId: resolved,
      defaultResolvedCategoryId: defaultResolved,
      itemName: pick(j, ['item_name'], fallback: 'مادة'),
      defaultItemName: _nullIfEmpty(pick(j, ['default_item_name'])),
      warehouse: _nullIfEmpty(pick(j, ['warehouse'])),
      bomUnitQty: _toD(j['bom_unit_qty']),
      defaultQuantity: _toD(j['default_quantity']),
      quantity: _toD(j['quantity']),
      unitCost: _toD(j['unit_cost']),
      availableQuantity: _toD(j['available_quantity']),
      sufficient: j['sufficient'] != false,
      isCustomized: j['is_customized'] == true,
      isSubstituted: j['is_substituted'] == true,
    );
  }

  Map<String, dynamic> toPayload() => {
        'bom_item_id': bomItemId,
        'resolved_category_id': resolvedCategoryId,
        'quantity': quantity,
      };
}

class ConsumptionPreview {
  const ConsumptionPreview({
    required this.applies,
    required this.lines,
    required this.totalCost,
    required this.allSufficient,
    this.message,
  });

  final bool applies;
  final List<ConsumptionLine> lines;
  final double totalCost;
  final bool allSufficient;
  final String? message;
}

class ConfirmResult {
  const ConfirmResult({
    this.newCategoryId,
    this.stayedUnderProcessing = false,
  });

  final String? newCategoryId;
  final bool stayedUnderProcessing;
}

class ManufactureAdditionItem {
  const ManufactureAdditionItem({
    required this.id,
    required this.name,
    required this.cost,
    this.unit,
  });

  final String id;
  final String name;
  final double cost;
  final String? unit;

  factory ManufactureAdditionItem.fromJson(Map<String, dynamic> j) {
    return ManufactureAdditionItem(
      id: pick(j, ['id']),
      name: pick(j, ['name'], fallback: 'إضافة'),
      cost: _toD(j['cost']),
      unit: _nullIfEmpty(pick(j, ['unit'])),
    );
  }
}

/// Manufacturing cycle APIs — same Laravel routes as Angular `ManufacturingService`.
class ManufacturingApi {
  ManufacturingApi._();
  static final ManufacturingApi instance = ManufacturingApi._();

  final _api = ApiClient.instance;

  Future<List<ManufactureProductOption>> productsByWarehouse(
    String warehouse, {
    bool allCategories = false,
  }) async {
    try {
      final params = <String, dynamic>{'warehouse': warehouse};
      if (allCategories) params['scope'] = 'all_categories';
      final res = await _api.dio.get(
        '/manufacture/manfucture_by_warhouse',
        queryParameters: params,
      );
      final list = res.data is List ? res.data as List : const [];
      final out = <ManufactureProductOption>[];
      for (final row in list) {
        if (row is Map) {
          out.add(
            ManufactureProductOption.fromJson(Map<String, dynamic>.from(row)),
          );
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

  Future<List<BomRow>> bomList() async {
    try {
      final res = await _api.dio.get('/manufacture');
      final list = res.data is List ? res.data as List : const [];
      final out = <BomRow>[];
      for (final row in list) {
        if (row is Map) {
          out.add(BomRow.fromJson(Map<String, dynamic>.from(row)));
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

  Future<({List<ItemWithoutRecipe> items, int total})> itemsWithoutRecipes({
    String? warehouse,
    String? productType,
    String? search,
  }) async {
    try {
      final params = <String, dynamic>{};
      if (warehouse != null && warehouse.isNotEmpty) params['warehouse'] = warehouse;
      if (productType != null && productType.isNotEmpty) {
        params['product_type'] = productType;
      }
      if (search != null && search.trim().isNotEmpty) {
        params['search'] = search.trim();
      }
      final res = await _api.dio.get(
        '/manufacture/items-without-recipes',
        queryParameters: params,
      );
      final data = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      final list = data['data'] is List ? data['data'] as List : const [];
      final items = <ItemWithoutRecipe>[];
      for (final row in list) {
        if (row is Map) {
          items.add(ItemWithoutRecipe.fromJson(Map<String, dynamic>.from(row)));
        }
      }
      final totals = data['totals'] is Map
          ? Map<String, dynamic>.from(data['totals'] as Map)
          : <String, dynamic>{};
      return (
        items: items,
        total: _toInt(totals['items_count'] ?? items.length),
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<ConsumptionPreview> previewConsumption({
    required String productId,
    required double quantity,
    required String status,
    required bool wipKeepUnderProcessing,
    List<Map<String, dynamic>>? consumptionLines,
  }) async {
    try {
      final body = <String, dynamic>{
        'product_id': int.tryParse(productId) ?? productId,
        'quantity': quantity,
        'status': status,
        'wip_keep_under_processing': wipKeepUnderProcessing,
        if (consumptionLines != null && consumptionLines.isNotEmpty)
          'consumption_lines': consumptionLines,
      };
      final res = await _api.dio.post('/manufacture/confirm/preview', data: body);
      final data = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      final lines = <ConsumptionLine>[];
      if (data['lines'] is List) {
        for (final row in data['lines'] as List) {
          if (row is Map) {
            lines.add(ConsumptionLine.fromJson(Map<String, dynamic>.from(row)));
          }
        }
      }
      return ConsumptionPreview(
        applies: data['applies'] == true,
        lines: lines,
        totalCost: _toD(data['total_cost']),
        allSufficient: data['all_sufficient'] != false,
        message: _nullIfEmpty(pick(data, ['message'])),
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> updateRecipeFromConsumption({
    required String productId,
    required double quantity,
    required List<Map<String, dynamic>> consumptionLines,
  }) async {
    try {
      await _api.dio.post(
        '/manufacture/update-recipe-from-consumption',
        data: {
          'product_id': int.tryParse(productId) ?? productId,
          'quantity': quantity,
          'consumption_lines': consumptionLines,
        },
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<ConfirmResult> confirm({
    required String productId,
    required double quantity,
    required double total,
    required String status,
    required String date,
    bool wipKeepUnderProcessing = false,
    String? wipMode,
    String? wipTargetProductId,
    List<Map<String, dynamic>>? consumptionLines,
    bool updateRecipe = false,
  }) async {
    try {
      final body = <String, dynamic>{
        'product_id': int.tryParse(productId) ?? productId,
        'quantity': quantity,
        'total': total,
        'status': status,
        'date': date,
        if (consumptionLines != null && consumptionLines.isNotEmpty)
          'consumption_lines': consumptionLines,
        if (updateRecipe) 'update_recipe': true,
      };
      if (wipKeepUnderProcessing) {
        body['wip_keep_under_processing'] = true;
      } else if (wipMode != null) {
        body['wip_mode'] = wipMode;
        if (wipTargetProductId != null) {
          body['wip_target_product_id'] =
              int.tryParse(wipTargetProductId) ?? wipTargetProductId;
        }
      }
      final res = await _api.dio.post('/manufacture/confirm', data: body);
      final data = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      String? newId;
      if (data['wip_completion'] is Map) {
        final wip = Map<String, dynamic>.from(data['wip_completion'] as Map);
        newId = _nullIfEmpty(pick(wip, ['new_category_id']));
      }
      return ConfirmResult(
        newCategoryId: newId,
        stayedUnderProcessing: data['wip_stayed_under_processing'] == true,
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<List<ManufactureOrder>> confirmed() async {
    try {
      final res = await _api.dio.get('/manufacture/confirmed');
      return _orders(res.data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<List<ManufactureOrder>> confirmedDeleted() async {
    try {
      final res = await _api.dio.get('/manufacture/confirmed/deleted');
      return _orders(res.data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> markDone(String id) async {
    try {
      await _api.dio.get('/manufacture/done/$id');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> deleteOrder(String id) async {
    try {
      await _api.dio.delete('/manufacture/confirmed/$id');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<List<ManufactureAdditionItem>> listAdditions() async {
    try {
      final res = await _api.dio.get('/manufacture/additions');
      final list = res.data is List ? res.data as List : const [];
      final out = <ManufactureAdditionItem>[];
      for (final row in list) {
        if (row is Map) {
          out.add(
            ManufactureAdditionItem.fromJson(Map<String, dynamic>.from(row)),
          );
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

  Future<void> createAddition({
    required String name,
    required double cost,
    String? unit,
  }) async {
    try {
      await _api.dio.post(
        '/manufacture/additions',
        data: {
          'name': name.trim(),
          'cost': cost,
          'unit': (unit ?? '').trim().isEmpty ? null : unit!.trim(),
        },
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> updateAddition({
    required String id,
    required String name,
    required double cost,
    String? unit,
  }) async {
    try {
      await _api.dio.put(
        '/manufacture/additions/$id',
        data: {
          'name': name.trim(),
          'cost': cost,
          'unit': (unit ?? '').trim().isEmpty ? null : unit!.trim(),
        },
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> deleteAddition(String id) async {
    try {
      await _api.dio.delete('/manufacture/additions/$id');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  List<ManufactureOrder> _orders(dynamic data) {
    final list = data is List ? data : const [];
    final out = <ManufactureOrder>[];
    for (final row in list) {
      if (row is Map) {
        out.add(ManufactureOrder.fromJson(Map<String, dynamic>.from(row)));
      }
    }
    return out;
  }
}

String? _nullIfEmpty(String s) => s.isEmpty ? null : s;

double _toD(dynamic v) {
  if (v == null) return 0;
  if (v is num) return v.toDouble();
  return double.tryParse('$v') ?? 0;
}

int _toInt(dynamic v) {
  if (v == null) return 0;
  if (v is int) return v;
  if (v is num) return v.toInt();
  return int.tryParse('$v') ?? 0;
}
