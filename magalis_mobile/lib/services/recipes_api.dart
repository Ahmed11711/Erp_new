import 'package:dio/dio.dart';

import '../core/api_client.dart';
import 'catalog_api.dart';

class RecipeListItem {
  const RecipeListItem({
    required this.id,
    required this.name,
    this.description,
    this.ingredientsCount = 0,
    this.extraCostsCount = 0,
    this.outputItemName,
    this.outputItemCode,
  });

  final String id;
  final String name;
  final String? description;
  final int ingredientsCount;
  final int extraCostsCount;
  final String? outputItemName;
  final String? outputItemCode;

  factory RecipeListItem.fromJson(Map<String, dynamic> j) {
    Map<String, dynamic> output = {};
    final rel = j['output_item'] ?? j['outputItem'];
    if (rel is Map) output = Map<String, dynamic>.from(rel);
    return RecipeListItem(
      id: pick(j, ['id']),
      name: pick(j, ['recipe_name', 'name'], fallback: 'وصفة'),
      description: _nullIfEmpty(pick(j, ['description'])),
      ingredientsCount: _toInt(j['ingredients_count'] ?? j['ingredientsCount']),
      extraCostsCount: _toInt(j['extra_costs_count'] ?? j['extraCostsCount']),
      outputItemName: _nullIfEmpty(pick(output, ['category_name', 'name'])),
      outputItemCode: _nullIfEmpty(pick(output, ['item_code', 'code'])),
    );
  }
}

class RecipeIngredientRow {
  const RecipeIngredientRow({
    required this.itemId,
    required this.name,
    this.itemCode,
    this.warehouse,
    this.unit,
    this.image,
    required this.quantity,
    required this.unitCost,
  });

  final String itemId;
  final String name;
  final String? itemCode;
  final String? warehouse;
  final String? unit;
  final String? image;
  final double quantity;
  final double unitCost;

  double get total => quantity * unitCost;
}

class RecipeExtraCostRow {
  RecipeExtraCostRow({
    required this.id,
    required this.name,
    required this.type,
    required this.value,
  });

  final int id;
  String name;
  String type;
  double value;

  bool get isPercentage => type == 'percentage';
}

class RecipeCostBreakdown {
  const RecipeCostBreakdown({
    required this.materialsCost,
    required this.fixedCosts,
    required this.percentageCosts,
    required this.finalCost,
    this.marginPercent,
  });

  final double materialsCost;
  final double fixedCosts;
  final double percentageCosts;
  final double finalCost;
  final double? marginPercent;

  factory RecipeCostBreakdown.fromJson(Map<String, dynamic>? j) {
    if (j == null) {
      return const RecipeCostBreakdown(
        materialsCost: 0,
        fixedCosts: 0,
        percentageCosts: 0,
        finalCost: 0,
      );
    }
    return RecipeCostBreakdown(
      materialsCost: _toD(j['materials_cost']),
      fixedCosts: _toD(j['fixed_costs']),
      percentageCosts: _toD(j['percentage_costs']),
      finalCost: _toD(j['final_cost']),
      marginPercent: j['margin_percent'] == null ? null : _toD(j['margin_percent']),
    );
  }
}

class RecipeDetail {
  const RecipeDetail({
    required this.id,
    required this.name,
    this.description,
    this.outputItemId,
    this.outputWarehouse,
    this.outputName,
    this.outputCode,
    this.bomLocked = false,
    required this.ingredients,
    required this.extraCosts,
    this.breakdown,
  });

  final String id;
  final String name;
  final String? description;
  final String? outputItemId;
  final String? outputWarehouse;
  final String? outputName;
  final String? outputCode;
  final bool bomLocked;
  final List<RecipeIngredientRow> ingredients;
  final List<RecipeExtraCostRow> extraCosts;
  final RecipeCostBreakdown? breakdown;
}

class RecipeImportIngredient {
  const RecipeImportIngredient({
    required this.itemName,
    required this.quantity,
    this.unit,
    this.color,
    required this.itemExists,
    this.existingItemName,
    this.existingItemPrice,
  });

  final String itemName;
  final double quantity;
  final String? unit;
  final String? color;
  final bool itemExists;
  final String? existingItemName;
  final double? existingItemPrice;
}

class RecipeImportRecipe {
  const RecipeImportRecipe({
    required this.recipeName,
    required this.normalizedName,
    required this.exists,
    required this.ingredientsCount,
    required this.ingredients,
  });

  final String recipeName;
  final String normalizedName;
  final bool exists;
  final int ingredientsCount;
  final List<RecipeImportIngredient> ingredients;
}

class RecipeImportPreview {
  const RecipeImportPreview({
    required this.importToken,
    required this.message,
    required this.missingItems,
    required this.existingRecipes,
    required this.recipes,
    required this.recipesTotal,
    required this.missingItemsTotal,
    required this.existingRecipesTotal,
    this.sheetsParsed,
  });

  final String importToken;
  final String message;
  final List<String> missingItems;
  final List<String> existingRecipes;
  final List<RecipeImportRecipe> recipes;
  final int recipesTotal;
  final int missingItemsTotal;
  final int existingRecipesTotal;
  final int? sheetsParsed;
}

class RecipeImportConfirmResult {
  const RecipeImportConfirmResult({
    required this.message,
    required this.recipesCreated,
    required this.recipesUpdated,
    required this.recipesSkipped,
    required this.itemsCreated,
  });

  final String message;
  final int recipesCreated;
  final int recipesUpdated;
  final int recipesSkipped;
  final int itemsCreated;
}

/// Manufacturing recipes — same Laravel routes as Angular `ManufacturingService`.
class RecipesApi {
  RecipesApi._();
  static final RecipesApi instance = RecipesApi._();

  final _api = ApiClient.instance;

  Future<List<RecipeListItem>> list() async {
    try {
      final res = await _api.dio.get('/recipes');
      final list = res.data is List ? res.data as List : _extractList(res.data);
      final out = <RecipeListItem>[];
      for (final row in list) {
        if (row is Map) {
          out.add(RecipeListItem.fromJson(Map<String, dynamic>.from(row)));
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

  Future<RecipeDetail> show(String id) async {
    try {
      final res = await _api.dio.get('/recipes/$id');
      final data = res.data is Map ? Map<String, dynamic>.from(res.data as Map) : <String, dynamic>{};
      final recipeMap = data['recipe'] is Map
          ? Map<String, dynamic>.from(data['recipe'] as Map)
          : data;
      return _detailFrom(recipeMap, data['breakdown']);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  /// Create via POST `/manufacture` — same payload as Angular `addRecipe`.
  Future<void> add({
    required String productId,
    required double total,
    required List<Map<String, dynamic>> products,
    List<Map<String, dynamic>>? extraCosts,
  }) async {
    try {
      final body = <String, dynamic>{
        'product_id': int.tryParse(productId) ?? productId,
        'total': total,
        'products': products,
      };
      if (extraCosts != null && extraCosts.isNotEmpty) {
        body['extra_costs'] = extraCosts;
      }
      await _api.dio.post('/manufacture', data: body);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> update({
    required String id,
    String? recipeName,
    String? description,
    String? outputItemId,
    List<Map<String, dynamic>>? ingredients,
    List<Map<String, dynamic>>? extraCosts,
  }) async {
    try {
      final body = <String, dynamic>{
        'recipe_name': ?recipeName,
        'description': description,
      };
      if (outputItemId != null) {
        body['output_item_id'] = int.tryParse(outputItemId) ?? outputItemId;
      }
      if (ingredients != null) body['ingredients'] = ingredients;
      if (extraCosts != null) body['extra_costs'] = extraCosts;
      await _api.dio.put('/recipes/$id', data: body);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<void> delete(String id) async {
    try {
      await _api.dio.delete('/recipes/$id');
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<RecipeImportPreview> previewImport({
    required String filename,
    required List<int> bytes,
  }) async {
    try {
      final form = FormData.fromMap({
        'file': MultipartFile.fromBytes(bytes, filename: filename),
      });
      final res = await _api.dio.post('/recipes/import', data: form);
      final data = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      return _previewFrom(data);
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<RecipeImportConfirmResult> confirmImport({
    required String importToken,
    required bool createMissingItems,
    Map<String, String>? recipeActions,
  }) async {
    try {
      final res = await _api.dio.post(
        '/recipes/import/confirm',
        data: {
          'import_token': importToken,
          'create_missing_items': createMissingItems,
          if (recipeActions != null && recipeActions.isNotEmpty)
            'recipe_actions': recipeActions,
        },
      );
      final data = res.data is Map
          ? Map<String, dynamic>.from(res.data as Map)
          : <String, dynamic>{};
      final result = data['result'] is Map
          ? Map<String, dynamic>.from(data['result'] as Map)
          : <String, dynamic>{};
      return RecipeImportConfirmResult(
        message: pick(data, ['message'], fallback: 'تم الاستيراد'),
        recipesCreated: _toInt(result['recipes_created']),
        recipesUpdated: _toInt(result['recipes_updated']),
        recipesSkipped: _toInt(result['recipes_skipped']),
        itemsCreated: _toInt(result['items_created']),
      );
    } on DioException catch (e) {
      throw ApiException(
        ApiClient.messageFrom(e),
        statusCode: e.response?.statusCode,
      );
    }
  }

  RecipeDetail _detailFrom(Map<String, dynamic> recipe, dynamic breakdownRaw) {
    Map<String, dynamic> output = {};
    final rel = recipe['output_item'] ?? recipe['outputItem'];
    if (rel is Map) output = Map<String, dynamic>.from(rel);

    final ingredients = <RecipeIngredientRow>[];
    final ingRaw = recipe['ingredients'];
    if (ingRaw is List) {
      for (final row in ingRaw) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        Map<String, dynamic> item = {};
        if (j['item'] is Map) item = Map<String, dynamic>.from(j['item'] as Map);
        ingredients.add(
          RecipeIngredientRow(
            itemId: pick(item, ['id'], fallback: pick(j, ['item_id'])),
            name: pick(item, ['category_name', 'name'], fallback: 'صنف'),
            itemCode: _nullIfEmpty(pick(item, ['item_code', 'code'])),
            warehouse: _nullIfEmpty(pick(item, ['warehouse'])),
            unit: _nullIfEmpty(pick(item, ['unit'])) ??
                _measurementUnit(item),
            image: _nullIfEmpty(pick(item, ['category_image', 'image'])),
            quantity: _toD(j['quantity']),
            unitCost: _toD(j['unit_cost'] ?? item['category_price'] ?? item['unit_price']),
          ),
        );
      }
    }

    final extra = <RecipeExtraCostRow>[];
    final extraRaw = recipe['extra_costs'] ?? recipe['extraCosts'];
    if (extraRaw is List) {
      for (final row in extraRaw) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        extra.add(
          RecipeExtraCostRow(
            id: _toInt(j['id']),
            name: pick(j, ['name'], fallback: 'تكلفة'),
            type: pick(j, ['type'], fallback: 'fixed') == 'percentage'
                ? 'percentage'
                : 'fixed',
            value: _toD(j['value']),
          ),
        );
      }
    }

    Map<String, dynamic>? breakdownMap;
    if (breakdownRaw is Map) {
      breakdownMap = Map<String, dynamic>.from(breakdownRaw);
    }

    return RecipeDetail(
      id: pick(recipe, ['id']),
      name: pick(recipe, ['recipe_name', 'name'], fallback: 'وصفة'),
      description: _nullIfEmpty(pick(recipe, ['description'])),
      outputItemId: _nullIfEmpty(pick(output, ['id'], fallback: pick(recipe, ['output_item_id']))),
      outputWarehouse: _nullIfEmpty(pick(output, ['warehouse'])),
      outputName: _nullIfEmpty(pick(output, ['category_name', 'name'])),
      outputCode: _nullIfEmpty(pick(output, ['item_code', 'code'])),
      bomLocked: recipe['bom_locked'] == true || recipe['bom_locked'] == 1,
      ingredients: ingredients,
      extraCosts: extra,
      breakdown: breakdownMap == null
          ? null
          : RecipeCostBreakdown.fromJson(breakdownMap),
    );
  }

  RecipeImportPreview _previewFrom(Map<String, dynamic> data) {
    final summary = data['summary'] is Map
        ? Map<String, dynamic>.from(data['summary'] as Map)
        : <String, dynamic>{};
    final missing = <String>[];
    if (data['missing_items'] is List) {
      for (final x in data['missing_items'] as List) {
        missing.add('$x');
      }
    }
    final existing = <String>[];
    if (data['existing_recipes'] is List) {
      for (final x in data['existing_recipes'] as List) {
        existing.add('$x');
      }
    }
    final recipes = <RecipeImportRecipe>[];
    if (data['recipes'] is List) {
      for (final row in data['recipes'] as List) {
        if (row is! Map) continue;
        final j = Map<String, dynamic>.from(row);
        final ings = <RecipeImportIngredient>[];
        if (j['ingredients'] is List) {
          for (final ing in j['ingredients'] as List) {
            if (ing is! Map) continue;
            final g = Map<String, dynamic>.from(ing);
            ings.add(
              RecipeImportIngredient(
                itemName: pick(g, ['item_name', 'name']),
                quantity: _toD(g['quantity']),
                unit: _nullIfEmpty(pick(g, ['unit'])),
                color: _nullIfEmpty(pick(g, ['color'])),
                itemExists: g['item_exists'] == true || g['item_exists'] == 1,
                existingItemName: _nullIfEmpty(pick(g, ['existing_item_name'])),
                existingItemPrice: g['existing_item_price'] == null
                    ? null
                    : _toD(g['existing_item_price']),
              ),
            );
          }
        }
        recipes.add(
          RecipeImportRecipe(
            recipeName: pick(j, ['recipe_name', 'name']),
            normalizedName: pick(j, ['normalized_name']),
            exists: j['exists'] == true || j['exists'] == 1,
            ingredientsCount: _toInt(j['ingredients_count'] ?? ings.length),
            ingredients: ings,
          ),
        );
      }
    }
    return RecipeImportPreview(
      importToken: pick(data, ['import_token']),
      message: pick(data, ['message']),
      missingItems: missing,
      existingRecipes: existing,
      recipes: recipes,
      recipesTotal: _toInt(summary['recipes_total'] ?? recipes.length),
      missingItemsTotal: _toInt(summary['missing_items_total'] ?? missing.length),
      existingRecipesTotal:
          _toInt(summary['existing_recipes_total'] ?? existing.length),
      sheetsParsed: summary['sheets_parsed'] == null
          ? null
          : _toInt(summary['sheets_parsed']),
    );
  }

  List _extractList(dynamic data) {
    if (data is List) return data;
    if (data is! Map) return const [];
    for (final c in [data['data'], data['items'], data['recipes']]) {
      if (c is List) return c;
    }
    return const [];
  }

  String? _measurementUnit(Map<String, dynamic> item) {
    final m = item['measurement'];
    if (m is Map) {
      return _nullIfEmpty(pick(Map<String, dynamic>.from(m), ['unit']));
    }
    return null;
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
