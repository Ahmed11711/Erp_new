import 'package:dio/dio.dart';

import '../core/api_client.dart';
import 'catalog_api.dart';

class WarehouseRow {
  const WarehouseRow({
    required this.name,
    this.keyEn,
    this.balance = 0,
    this.quantityTotal = 0,
    this.id,
  });

  final String name;
  final String? keyEn;
  final double balance;
  final double quantityTotal;
  final int? id;

  bool get isFinished => name == 'مخزن منتج تام';
  bool get isMaintenance => name == 'مخزن صيانة';
}

class MonthlyInventoryLine {
  const MonthlyInventoryLine({
    required this.id,
    required this.categoryName,
    required this.unit,
    required this.quantity,
    required this.totalPrice,
    required this.sellTotalPrice,
    required this.by,
    this.sellPrice,
  });

  final String id;
  final String categoryName;
  final String unit;
  final double quantity;
  final double totalPrice;
  final double sellTotalPrice;
  final String by;
  final double? sellPrice;
}

class WarehouseCategoryLine {
  const WarehouseCategoryLine({
    required this.id,
    required this.categoryName,
    required this.unit,
    required this.quantity,
    required this.totalPrice,
    required this.sellTotalPrice,
    this.sellPrice,
  });

  final String id;
  final String categoryName;
  final String unit;
  final double quantity;
  final double totalPrice;
  final double sellTotalPrice;
  final double? sellPrice;
}

class SnapshotResult {
  const SnapshotResult({
    required this.success,
    required this.month,
    required this.warehouse,
    required this.lines,
  });

  final bool success;
  final String month;
  final String warehouse;
  final int lines;
}

class WarehouseMovement {
  const WarehouseMovement({
    required this.id,
    required this.categoryName,
    required this.type,
    required this.quantity,
    this.balanceBefore,
    this.balanceAfter,
    this.price,
    this.totalPrice,
    this.movementDate,
    this.partyName,
    this.invoiceNumber,
    this.ref,
    this.status,
    this.by,
  });

  final String id;
  final String categoryName;
  final String type;
  final double quantity;
  final double? balanceBefore;
  final double? balanceAfter;
  final double? price;
  final double? totalPrice;
  final DateTime? movementDate;
  final String? partyName;
  final String? invoiceNumber;
  final String? ref;
  final String? status;
  final String? by;

  bool get isMaintenance => type == 'صيانة';
}

class InventoryPage<T> {
  const InventoryPage({required this.items, required this.total});

  final List<T> items;
  final int total;
}

/// Row from `GET /reports/warehouse-inventory` (Angular تقرير المخزون).
class WarehouseInventoryLine {
  const WarehouseInventoryLine({
    required this.categoryId,
    required this.categoryName,
    this.categoryImage,
    required this.openingQty,
    required this.quantity,
    required this.periodInQty,
    required this.periodOutQty,
    required this.periodNetQty,
    required this.unitCost,
    required this.totalValue,
    required this.sellValue,
    required this.measurementUnit,
  });

  final int categoryId;
  final String categoryName;
  final String? categoryImage;
  final double openingQty;
  final double quantity;
  final double periodInQty;
  final double periodOutQty;
  final double periodNetQty;
  final double unitCost;
  final double totalValue;
  final double sellValue;
  final String measurementUnit;
}

class WarehouseInventoryTotals {
  const WarehouseInventoryTotals({
    this.itemsCount = 0,
    this.totalQuantity = 0,
    this.totalValue = 0,
    this.totalSellValue = 0,
    this.periodInTotal = 0,
    this.periodOutTotal = 0,
    this.isFinishedWarehouse = false,
    this.warehouse = '',
    this.dateFrom,
    this.dateTo,
  });

  final int itemsCount;
  final double totalQuantity;
  final double totalValue;
  final double totalSellValue;
  final double periodInTotal;
  final double periodOutTotal;
  final bool isFinishedWarehouse;
  final String warehouse;
  final String? dateFrom;
  final String? dateTo;

  factory WarehouseInventoryTotals.fromJson(Map<String, dynamic>? j) {
    if (j == null) return const WarehouseInventoryTotals();
    return WarehouseInventoryTotals(
      itemsCount: int.tryParse('${j['items_count'] ?? 0}') ?? 0,
      totalQuantity: InventoryApi._toDouble(j['total_quantity']),
      totalValue: InventoryApi._toDouble(j['total_value']),
      totalSellValue: InventoryApi._toDouble(j['total_sell_value']),
      periodInTotal: InventoryApi._toDouble(j['period_in_total']),
      periodOutTotal: InventoryApi._toDouble(j['period_out_total']),
      isFinishedWarehouse: j['is_finished_warehouse'] == true ||
          j['is_finished_warehouse'] == 1,
      warehouse: pick(j, ['warehouse']),
      dateFrom: j['date_from']?.toString(),
      dateTo: j['date_to']?.toString(),
    );
  }
}

class WarehouseInventoryReport {
  const WarehouseInventoryReport({
    required this.items,
    required this.total,
    required this.totals,
  });

  final List<WarehouseInventoryLine> items;
  final int total;
  final WarehouseInventoryTotals totals;
}

/// Display labels for warehouse filter (same as Angular storage report).
const kWarehouseReportOptions = <({String value, String label})>[
  (value: 'مخزن مواد خام', label: 'مخزن المواد الخام'),
  (value: 'مخزن منتج تحت التشغيل', label: 'مخزن منتج التشغيل'),
  (value: 'مخزن منتج تام', label: 'مخزن المنتج التام'),
  (value: 'مستلزمات تشغيل وأدوات تشغيل', label: 'مستلزمات تشغيل وأدوات تشغيل'),
  (value: 'مخزن صيانة', label: 'مخزن الصيانة'),
  (value: 'مخزن تالف', label: 'مخزن التالف'),
];

/// Standard Magalis warehouses (same as Angular `WAREHOUSE_STOCK_ROWS`).
const kStandardWarehouses = <({String nameAr, String keyEn})>[
  (nameAr: 'مخزن مواد خام', keyEn: 'Raw'),
  (nameAr: 'مخزن منتج تحت التشغيل', keyEn: 'In_Process'),
  (nameAr: 'مخزن منتج تام', keyEn: 'Finished'),
  (nameAr: 'مستلزمات تشغيل وأدوات تشغيل', keyEn: 'Operating_Supplies'),
  (nameAr: 'مخزن صيانة', keyEn: 'Maintenance'),
  (nameAr: 'مخزن تالف', keyEn: 'Defective'),
];

/// Warehouse / monthly inventory APIs (same Laravel routes as Angular).
class InventoryApi {
  InventoryApi._();
  static final InventoryApi instance = InventoryApi._();

  final _api = ApiClient.instance;

  Future<List<WarehouseRow>> listWarehouses() async {
    try {
      final results = await Future.wait([
        _api.dio.get('/categories/warehouse_balance'),
        _api.dio.get('/stocks', queryParameters: {
          'page': 1,
          'itemsPerPage': 100,
        }),
      ]);

      final balances = results[0].data is Map
          ? Map<String, dynamic>.from(results[0].data as Map)
          : <String, dynamic>{};
      final qtyTotals = balances['quantity_totals'] is Map
          ? Map<String, dynamic>.from(balances['quantity_totals'] as Map)
          : <String, dynamic>{};

      final stockList = _extractList(results[1].data);
      final byName = <String, Map<String, dynamic>>{};
      for (final row in stockList) {
        if (row is Map) {
          final m = Map<String, dynamic>.from(row);
          final name = pick(m, ['name', 'stock_name']);
          if (name.isNotEmpty) byName[name] = m;
        }
      }

      final standardNames = kStandardWarehouses.map((w) => w.nameAr).toSet();
      final rows = <WarehouseRow>[];

      for (final w in kStandardWarehouses) {
        final stock = byName[w.nameAr];
        rows.add(
          WarehouseRow(
            name: w.nameAr,
            keyEn: w.keyEn,
            balance: _toDouble(balances[w.keyEn]),
            quantityTotal: _toDouble(qtyTotals[w.keyEn]),
            id: stock == null ? null : int.tryParse('${stock['id']}'),
          ),
        );
      }

      for (final entry in byName.entries) {
        if (standardNames.contains(entry.key)) continue;
        final s = entry.value;
        rows.add(
          WarehouseRow(
            name: entry.key,
            balance: _toDouble(s['balance']),
            id: int.tryParse('${s['id']}'),
          ),
        );
      }

      return rows;
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<InventoryPage<MonthlyInventoryLine>> monthlyDetails({
    required String warehouse,
    required String month,
    int page = 1,
    int itemsPerPage = 40,
    String? name,
    bool sort = false,
  }) async {
    try {
      final params = <String, dynamic>{
        'warehouse': warehouse,
        'month': month,
        'page': page,
        'itemsPerPage': itemsPerPage,
      };
      final q = name?.trim();
      if (q != null && q.isNotEmpty) params['name'] = q;
      if (sort) params['sort'] = true;

      final res = await _api.dio.get(
        '/categories/monthlyInventoryDetailsByWherehouse',
        queryParameters: params,
      );
      final list = _extractList(res.data);
      final items = <MonthlyInventoryLine>[];
      for (final row in list) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        final cat = j['category'] is Map
            ? Map<String, dynamic>.from(j['category'] as Map)
            : <String, dynamic>{};
        final meas = cat['measurement'] is Map
            ? Map<String, dynamic>.from(cat['measurement'] as Map)
            : <String, dynamic>{};
        items.add(
          MonthlyInventoryLine(
            id: pick(j, ['id'], fallback: '${items.length}'),
            categoryName: pick(cat, ['category_name', 'name'], fallback: 'صنف'),
            unit: pick(meas, ['unit'], fallback: '-'),
            quantity: _toDouble(j['quantity']),
            totalPrice: _toDouble(j['total_price']),
            sellTotalPrice: _toDouble(j['sell_total_price']),
            by: pick(j, ['by'], fallback: '-'),
            sellPrice: _nullableDouble(cat['category_price']),
          ),
        );
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

  Future<InventoryPage<WarehouseCategoryLine>> categoriesByWarehouse({
    required String warehouse,
    int page = 1,
    int itemsPerPage = 40,
    String? name,
    bool sort = false,
  }) async {
    try {
      final params = <String, dynamic>{
        'warehouse': warehouse,
        'page': page,
        'itemsPerPage': itemsPerPage,
      };
      final q = name?.trim();
      if (q != null && q.isNotEmpty) params['name'] = q;
      if (sort) params['sort'] = true;

      final res = await _api.dio.get(
        '/categories/categoryDetailsByWherehouse',
        queryParameters: params,
      );
      final list = _extractList(res.data);
      final items = <WarehouseCategoryLine>[];
      for (final row in list) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        final meas = j['measurement'] is Map
            ? Map<String, dynamic>.from(j['measurement'] as Map)
            : <String, dynamic>{};
        items.add(
          WarehouseCategoryLine(
            id: pick(j, ['id'], fallback: '${items.length}'),
            categoryName: pick(j, ['category_name', 'name'], fallback: 'صنف'),
            unit: pick(meas, ['unit'], fallback: '-'),
            quantity: _toDouble(j['quantity']),
            totalPrice: _toDouble(j['total_price']),
            sellTotalPrice: _toDouble(j['sell_total_price']),
            sellPrice: _nullableDouble(j['category_price']),
          ),
        );
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

  /// Create/update month-end snapshot (`GET /categories/monthlyinventory`).
  Future<SnapshotResult> recordSnapshot({
    required String warehouse,
    String? month,
  }) async {
    try {
      final params = <String, dynamic>{'warehouse': warehouse};
      if (month != null && month.isNotEmpty) params['month'] = month;

      final res = await _api.dio.get(
        '/categories/monthlyinventory',
        queryParameters: params,
      );
      final data = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      return SnapshotResult(
        success: data['success'] == true || data['success'] == 1,
        month: pick(data, ['month'], fallback: month ?? ''),
        warehouse: pick(data, ['warehouse'], fallback: warehouse),
        lines: int.tryParse('${data['lines'] ?? 0}') ?? 0,
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  /// Adjust quantity (`GET /categoryquantity?id=&status=&quantity=&movement_date=`).
  Future<void> changeQuantity({
    required String id,
    required String status,
    required double quantity,
    String? movementDate,
  }) async {
    try {
      final params = <String, dynamic>{
        'id': id,
        'status': status,
        'quantity': quantity,
      };
      if (movementDate != null && movementDate.isNotEmpty) {
        params['movement_date'] = movementDate;
      }
      await _api.dio.get(
        '/categoryquantity',
        queryParameters: params,
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  /// Movement ledger — `GET /categories/warehousedetails` (Angular تتبع).
  Future<InventoryPage<WarehouseMovement>> warehouseMovements({
    required String warehouse,
    int page = 1,
    int itemsPerPage = 30,
    String? date,
  }) async {
    try {
      final params = <String, dynamic>{
        'warehouse': warehouse,
        'page': page,
        'itemsPerPage': itemsPerPage,
      };
      if (date != null && date.isNotEmpty) params['date'] = date;

      final res = await _api.dio.get(
        '/categories/warehousedetails',
        queryParameters: params,
      );
      final list = _extractList(res.data);
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
            categoryName: pick(j, ['category_name', 'name'], fallback: 'صنف'),
            type: pick(j, ['type'], fallback: ''),
            quantity: _toDouble(j['quantity']),
            balanceBefore: _nullableDouble(j['balance_before']),
            balanceAfter: _nullableDouble(j['balance_after']),
            price: _nullableDouble(j['price']),
            totalPrice: _nullableDouble(j['total_price']),
            movementDate: dt,
            partyName: pick(j, ['party_name']).isEmpty
                ? null
                : pick(j, ['party_name']),
            invoiceNumber: pick(j, ['invoice_number']).isEmpty
                ? null
                : pick(j, ['invoice_number']),
            ref: pick(j, ['ref']).isEmpty ? null : pick(j, ['ref']),
            status: pick(j, ['status']).isEmpty ? null : pick(j, ['status']),
            by: pick(j, ['by']).isEmpty ? null : pick(j, ['by']),
          ),
        );
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

  Future<void> deleteStock(int id) async {
    try {
      await _api.dio.delete('/stocks/$id');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  /// Storage report — `GET /reports/warehouse-inventory` (Angular تقرير المخزون).
  Future<WarehouseInventoryReport> warehouseInventoryReport({
    required String warehouse,
    String? dateFrom,
    String? dateTo,
    String sort = 'total_value',
    String? search,
    int page = 1,
    int itemsPerPage = 15,
  }) async {
    try {
      final params = <String, dynamic>{
        'warehouse': warehouse,
        'page': page,
        'itemsPerPage': itemsPerPage,
        'sort': sort,
      };
      if (dateFrom != null && dateFrom.isNotEmpty) {
        params['date_from'] = dateFrom;
      }
      if (dateTo != null && dateTo.isNotEmpty) {
        params['date_to'] = dateTo;
      }
      final q = search?.trim();
      if (q != null && q.isNotEmpty) params['search'] = q;

      final res = await _api.dio.get(
        '/reports/warehouse-inventory',
        queryParameters: params,
      );
      final body = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      final list = _extractList(body);
      final items = <WarehouseInventoryLine>[];
      for (final row in list) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        final img = pick(j, ['category_image']);
        items.add(
          WarehouseInventoryLine(
            categoryId: int.tryParse('${j['category_id'] ?? 0}') ?? 0,
            categoryName:
                pick(j, ['category_name', 'name'], fallback: 'صنف'),
            categoryImage: img.isEmpty ? null : img,
            openingQty: _toDouble(j['opening_qty']),
            quantity: _toDouble(j['quantity']),
            periodInQty: _toDouble(j['period_in_qty']),
            periodOutQty: _toDouble(j['period_out_qty']),
            periodNetQty: _toDouble(j['period_net_qty']),
            unitCost: _toDouble(j['unit_cost']),
            totalValue: _toDouble(j['total_value']),
            sellValue: _toDouble(j['sell_value']),
            measurementUnit:
                pick(j, ['measurement_unit'], fallback: '-'),
          ),
        );
      }
      final totalsMap = body['totals'] is Map
          ? Map<String, dynamic>.from(body['totals'] as Map)
          : null;
      return WarehouseInventoryReport(
        items: items,
        total: int.tryParse('${body['total'] ?? items.length}') ?? items.length,
        totals: WarehouseInventoryTotals.fromJson(totalsMap),
      );
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
    final candidates = [data['data'], data['items']];
    for (final c in candidates) {
      if (c is List) return c;
      if (c is Map && c['data'] is List) return c['data'] as List;
    }
    return const [];
  }

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    return double.tryParse('$v') ?? 0;
  }

  static double? _nullableDouble(dynamic v) {
    if (v == null) return null;
    if (v is num) return v.toDouble();
    return double.tryParse('$v');
  }

  /// Previous calendar month as `YYYY-MM` (default snapshot month on web).
  static String previousMonthValue([DateTime? now]) {
    final d = now ?? DateTime.now();
    var y = d.year;
    var m = d.month - 1;
    if (m == 0) {
      m = 12;
      y--;
    }
    return '$y-${m.toString().padLeft(2, '0')}';
  }
}
