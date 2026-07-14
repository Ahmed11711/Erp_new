<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\BanksController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\Inventory\InventoryExcelImportController;
use App\Http\Controllers\Inventory\StockCountImportController;
use App\Http\Controllers\Manufacturing\ProductionOrderController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\MeasurementController;
use App\Http\Controllers\ItemClassificationController;
use App\Http\Middleware\MeasurementApiAccess;
use App\Http\Middleware\CategoriesApiAccess;
use App\Http\Middleware\InventoryGlSyncApiAccess;
use App\Http\Middleware\WarehouseCategoryBalanceApiAccess;
use App\Http\Middleware\SuppliersPurchasesApiAccess;
use App\Http\Middleware\FinanceOperationsLegacyApiAccess;
use App\Http\Middleware\RecipeManufacturingApiAccess;
use App\Http\Middleware\ManufacturingModuleApiAccess;
use App\Http\Middleware\EmployeesHrApiAccess;
use App\Http\Middleware\CorporateSalesApiAccess;
use App\Http\Middleware\SettingsUserManagementApiAccess;
use App\Http\Middleware\SystemAdminToolsApiAccess;
use App\Http\Middleware\CustomerCompanyIndexApiAccess;
use App\Http\Controllers\OrderSourceController;
use App\Http\Controllers\ShippingLineController;
use App\Http\Controllers\V2\stock\stockController;
use App\Http\Middleware\WarehouseStockApiAccess;
use App\Http\Controllers\CustomerCompanyController;
use App\Http\Controllers\ShippingCompanyController;
use App\Http\Controllers\CollectionCompanyController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\OrderFulfillmentController;
use App\Http\Controllers\ShippingMethodsController;
use App\Http\Controllers\CorporateSalesLeadController;
use App\Http\Controllers\PendingBankBalanceController;
use App\Http\Controllers\V2\Report\ReportOrder\ReportOrderController;
use App\Http\Controllers\V2\Transaction\TransactionController;
use App\Http\Controllers\V2\TreeAccount\TreeAccountController;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/meta-test', function () {
    return app(\App\Services\MetaWhatsAppService::class)
        ->sendMessage('201068704455', 'Test from server');
});

Route::get('/test', function () {
    return response()->json(['status' => 'ok']);
});


// Route::get('/meta/webhook', function () {
//     $verify_token = 'K9xT2pLm8QwZ4rNs7VbY1cHd6EfG3uJk';
//     if (request()->get('hub_verify_token') === $verify_token) {
//         return response(request()->get('hub_challenge'), 200);
//     }
//     return response('Error, wrong token', 403);
// });

Route::get('/meta/webhook', [\App\Http\Controllers\MetaWebhookController::class, 'verify']);
Route::post('/meta/webhook', [\App\Http\Controllers\MetaWebhookController::class, 'handle']);

Route::get('/meta/webhook-health', function () {
    $hasMediaId = \Illuminate\Support\Facades\Schema::hasColumn('messages', 'media_id');
    $hasType = \Illuminate\Support\Facades\Schema::hasColumn('messages', 'type');
    $hasPhoneNumberId = \Illuminate\Support\Facades\Schema::hasColumn('messages', 'phone_number_id');
    $latestInbound = \App\Models\Message::where('direction', 'inbound')
        ->orderByDesc('id')
        ->first(['id', 'type', 'media_id', 'content', 'created_at']);
    $totalMedia = \App\Models\Message::whereNotNull('media_id')
        ->where('media_id', '!=', '')
        ->count();
    return response()->json([
        'columns_ok' => $hasMediaId && $hasType && $hasPhoneNumberId,
        'has_media_id_column' => $hasMediaId,
        'has_type_column' => $hasType,
        'has_phone_number_id_column' => $hasPhoneNumberId,
        'total_media_messages' => $totalMedia,
        'latest_inbound' => $latestInbound,
    ]);
});

Route::get('/shopify/webhook', [\App\Http\Controllers\ShopifyWebhookController::class, 'ping']);
Route::post('/shopify/webhook', [\App\Http\Controllers\ShopifyWebhookController::class, 'handle']);
Route::post('/webhooks/shipping/update', [\App\Http\Controllers\ShippingPartnerWebhookController::class, 'update']);

// Shopify Admin API — صلاحيات منفصلة: إعدادات المزامنة ↔ لوحة الشحن (مع الإبقاء على nav.shopify كاملة)
Route::middleware(['auth', 'permission:nav.shopify.settings|nav.shopify|system.rbac'])->group(function () {
    Route::get('shopify/status', [\App\Http\Controllers\ShopifyIntegrationController::class, 'status'])
        ->name('shopify.status');
    Route::get('shopify/integration-settings', [\App\Http\Controllers\ShopifyIntegrationController::class, 'integrationSettings'])
        ->name('shopify.integration-settings');
    Route::post('shopify/integration-settings', [\App\Http\Controllers\ShopifyIntegrationController::class, 'updateIntegrationSettings'])
        ->name('shopify.update-integration-settings');
    Route::post('shopify/sync-product-mappings', [\App\Http\Controllers\ShopifyIntegrationController::class, 'syncProductMappings'])
        ->name('shopify.sync-product-mappings');
    Route::post('shopify/sync-orders', [\App\Http\Controllers\ShopifyIntegrationController::class, 'syncOrders'])
        ->name('shopify.sync-orders');
    Route::post('shopify/preview-order-sync', [\App\Http\Controllers\ShopifyIntegrationController::class, 'previewOrderSync'])
        ->name('shopify.preview-order-sync');
    Route::get('shopify/product-mappings', [\App\Http\Controllers\ShopifyIntegrationController::class, 'productMappingsIndex'])
        ->name('shopify.product-mappings');
    Route::patch('shopify/product-mappings/{id}', [\App\Http\Controllers\ShopifyIntegrationController::class, 'updateMappingCategory'])
        ->name('shopify.update-mapping');
    Route::post('shopify/create-unmatched-categories', [\App\Http\Controllers\ShopifyIntegrationController::class, 'createUnmatchedCategories'])
        ->name('shopify.create-unmatched');
    Route::post('shopify/update-prices', [\App\Http\Controllers\ShopifyIntegrationController::class, 'updatePrices'])
        ->name('shopify.update-prices');
});

Route::middleware(['auth', 'permission:nav.shopify.dashboard|nav.shopify|system.rbac'])->group(function () {
    Route::get('shopify/integration/orders', [\App\Http\Controllers\ShopifyShippingIntegrationController::class, 'integrationOrders']);
    Route::get('shopify/integration/products', [\App\Http\Controllers\ShopifyShippingIntegrationController::class, 'integrationProducts']);
    Route::patch('shopify/integration/products/{id}', [\App\Http\Controllers\ShopifyShippingIntegrationController::class, 'updateIntegrationProduct']);
    Route::get('shopify/integration/failed-jobs', [\App\Http\Controllers\ShopifyShippingIntegrationController::class, 'failedJobs']);
});


Route::group(['middleware' => 'api', 'prefix' => 'auth'], function ($router) {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);
    // Route::post('logout', 'AuthController@logout');
    // Route::post('refresh', 'AuthController@refresh');
    // Route::post('me', 'AuthController@me');
});

Route::post('/whatsapp/webhook', [OrdersController::class, 'whatsapp']);

Route::middleware('auth')->group(function () {
    Route::get('usersnotification', [App\Http\Controllers\UserController::class, 'usersForNotification']);

    Route::post('notification', [App\Http\Controllers\NotificationController::class, 'sendNotification']);
    Route::get('notification', [App\Http\Controllers\NotificationController::class, 'getById']);
    Route::get('recievednotification', [App\Http\Controllers\NotificationController::class, 'recievedNotifiy']);
    Route::get('sentnotification', [App\Http\Controllers\NotificationController::class, 'sentNotifiy']);
    Route::get('notification/{id}', [App\Http\Controllers\NotificationController::class, 'readNotify']);
    Route::post('notification/{id}', [App\Http\Controllers\NotificationController::class, 'readOrderNotify']);

    // WhatsApp-Web style chat API (cursor pagination + media proxy)
    Route::get('conversations/{conversation_id}/messages', [App\Http\Controllers\ConversationController::class, 'messages'])
        ->whereNumber('conversation_id');
    Route::get('media/{id}', [App\Http\Controllers\ConversationController::class, 'media'])
        ->whereNumber('id');

    // WhatsApp Messaging Routes
    Route::prefix('whatsapp/')->group(function () {
        Route::post('send', [App\Http\Controllers\WhatsAppMessageController::class, 'sendMessage']);
        Route::post('send-template', [App\Http\Controllers\WhatsAppMessageController::class, 'sendTemplateMessage']);
        Route::post('send-from-order', [App\Http\Controllers\WhatsAppMessageController::class, 'sendMessageFromOrder']);
        Route::get('meta-templates', [App\Http\Controllers\WhatsAppMessageController::class, 'getMetaTemplatesList']);
        Route::post('send-meta-template-from-order', [App\Http\Controllers\WhatsAppMessageController::class, 'sendMetaTemplateFromOrder']);
        Route::get('chat/{customerId}', [App\Http\Controllers\WhatsAppMessageController::class, 'getChatMessages']);
        Route::get('customers/by-phone', [App\Http\Controllers\WhatsAppMessageController::class, 'findCustomerByPhone']);
        Route::get('customers/whatsapp-snippet', [App\Http\Controllers\WhatsAppMessageController::class, 'getWhatsAppSnippet']);
        Route::get('customers', [App\Http\Controllers\WhatsAppMessageController::class, 'getCustomers']);
        Route::get('templates', [App\Http\Controllers\WhatsAppMessageController::class, 'getTemplates']);
        Route::post('templates', [App\Http\Controllers\WhatsAppMessageController::class, 'createTemplate']);
        
        // WhatsApp Number Assignment Routes
        Route::middleware(['permission:whatsapp.assign_numbers|system.rbac'])->get(
            'assignable-users',
            [App\Http\Controllers\UserController::class, 'whatsappAssignmentPicker']
        );
        Route::get('phone-numbers', [App\Http\Controllers\WhatsAppMessageController::class, 'getAvailablePhoneNumbers']);
        Route::get('assignments', [App\Http\Controllers\WhatsAppMessageController::class, 'getAllAssignments']);
        Route::get('user-phone-numbers', [App\Http\Controllers\WhatsAppMessageController::class, 'getUserPhoneNumbers']);
        Route::post('assign-users', [App\Http\Controllers\WhatsAppMessageController::class, 'assignUsersToPhoneNumber']);
        Route::delete('remove-assignment', [App\Http\Controllers\WhatsAppMessageController::class, 'removeUserAssignment']);
    });

    Route::get('order_source', [OrderSourceController::class, 'index']);
    Route::get('shipping_methods', [ShippingMethodsController::class, 'index']);
    Route::get('shippinglines', [ShippingLineController::class, 'index']);
    Route::get('orders/search', [OrdersController::class, 'search']);
    Route::get('orders/visible-statuses', [OrdersController::class, 'visibleStatuses']);
    Route::post('orders/print', [OrdersController::class, 'printOrders']);
    Route::post('orders/{id}/shopify-review', [OrdersController::class, 'shopifyReview'])
        ->middleware('permission:orders.shopify.review|nav.shopify.dashboard|nav.shopify|system.rbac')
        ->whereNumber('id');
    Route::get('orders/{id}', [OrdersController::class, 'show']);
    Route::post('orders/{id}/prepaid-adjustment', [OrdersController::class, 'adjustPrepaid'])
        ->whereNumber('id');

    Route::get('productions', [ProductionController::class, 'index']);
    Route::get('shippingcompanySelect', [ShippingCompanyController::class, 'shippingcompanySelect']);
    Route::get('bankSelect', [BanksController::class, 'bankSelect']);
    Route::get('categories/cat_orders', [CategoriesController::class, 'categories_for_orders']);

    // Reports and charts
    Route::get('reports/categoriesSellReports', [App\Http\Controllers\CategoriesController::class, 'categoriesSellReports']);
    Route::get('reports/warehouse-inventory', [App\Http\Controllers\CategoriesController::class, 'warehouseInventoryReport']);
    Route::get('reports/shipping-companies', [ShippingCompanyController::class, 'shippingCompaniesReport']);

    // Shipping accounting reports (ذمم الشحن والتحصيل — RBAC)
    Route::middleware(['permission:nav.shipping.accounts_report|system.rbac'])->group(function () {
        Route::get('reports/shipping-accounts', [App\Http\Controllers\ShippingAccountingReportController::class, 'accountsSummary']);
        Route::get('reports/shipping-accounts/{id}/statement', [App\Http\Controllers\ShippingAccountingReportController::class, 'companyStatement']);
        Route::get('reports/shipping-accounts/pending-orders', [App\Http\Controllers\ShippingAccountingReportController::class, 'pendingOrders']);
        Route::get('reports/shipping-accounts/settlement-summary', [App\Http\Controllers\ShippingAccountingReportController::class, 'settlementSummary']);
        Route::get('reports/collection-accounts', [App\Http\Controllers\CollectionAccountingReportController::class, 'accountsSummary']);
        Route::get('reports/collection-accounts/pending-orders', [App\Http\Controllers\CollectionAccountingReportController::class, 'pendingOrders']);
        Route::get('reports/collection-accounts/{id}/statement', [App\Http\Controllers\CollectionAccountingReportController::class, 'companyStatement']);
    });

    Route::middleware([InventoryGlSyncApiAccess::class])->group(function () {
        Route::get('categories/inventory-gl-sync-preview', [CategoriesController::class, 'previewInventoryGlSyncFromCategories']);
        Route::post('categories/inventory-gl-sync', [CategoriesController::class, 'syncInventoryAccountsFromActualCosts']);
    });

    Route::middleware([WarehouseCategoryBalanceApiAccess::class])->group(function () {
        Route::get('categories/warehouse_balance', [CategoriesController::class, 'warehouse_balance']);
        Route::get('categories/categories_details/{id}', [CategoriesController::class, 'categories_details']);
        Route::get('categories/warehousedetails', [CategoriesController::class, 'warehouseDetails']);
        Route::get('categories/categoryDetailsByWherehouse', [CategoriesController::class, 'categoryDetailsByWherehouse']);
        Route::get('categoryquantity', [CategoriesController::class, 'changeCategoryQuantity']);
        Route::get('categories/monthlyinventory', [CategoriesController::class, 'monthlyInventory']);
        Route::get('categories/monthlyInventoryDetailsByWherehouse', [CategoriesController::class, 'monthlyInventoryDetailsByWherehouse']);
        Route::get('categories/categoryByWarehouse', [CategoriesController::class, 'categoryByWarehouse']);
    });

    Route::middleware([SuppliersPurchasesApiAccess::class])->group(function () {
        Route::get('transactions/by-supplier-order/search', [SupplierController::class, 'supplierAccountsAggregated']);
        Route::get('suppliers/search', [SupplierController::class, 'search']);
        Route::post('suppliers/StoreSupplierType', [SupplierController::class, 'StoreSupplierType']);
        Route::delete('suppliers/deleteType/{id}', [SupplierController::class, 'deleteType']);
        Route::post('suppliers/supplierPay/{id}', [SupplierController::class, 'supplierPay']);
        Route::get('suppliers/getAllSupplierTypes', [SupplierController::class, 'getAllSupplierTypes']);
        Route::get('suppliers/supplierDetails/{id}', [SupplierController::class, 'supplier_details']);
        Route::get('suppliers/supplier_names', [SupplierController::class, 'supplier_names']);
        Route::get('suppliers/processing-vendors', [SupplierController::class, 'processingVendors']);
        Route::get('suppliers/purge-preview', [SupplierController::class, 'purgePreview']);
        Route::post('suppliers/purge-all', [SupplierController::class, 'purgeAll']);
        Route::post('suppliers/bulk-delete', [SupplierController::class, 'destroyMany']);
        Route::apiResource('suppliers', App\Http\Controllers\SupplierController::class);

        Route::get('purchases/search', [App\Http\Controllers\PurchasesController::class, 'search']);
        Route::post('purchases/bulk-delete', [App\Http\Controllers\PurchasesController::class, 'destroyMany']);
        Route::get('purchases/{id}', [App\Http\Controllers\PurchasesController::class, 'show']);
        Route::patch('purchases/{id}/print-status', [App\Http\Controllers\PurchasesController::class, 'updatePrintable']);
        Route::apiResource('purchases', App\Http\Controllers\PurchasesController::class);
    });

    Route::middleware([FinanceOperationsLegacyApiAccess::class])->group(function () {
        Route::post('inventory/import/items', [InventoryExcelImportController::class, 'importItems']);
        Route::post('inventory/import/recipe-sheet-items', [InventoryExcelImportController::class, 'importRecipeSheetItems']);
        Route::post('inventory/import/opening-balances', [InventoryExcelImportController::class, 'importOpeningBalances']);
        Route::post('inventory/import/adjustments', [InventoryExcelImportController::class, 'importAdjustments']);

        Route::post('inventory/stock-count/preview', [StockCountImportController::class, 'preview']);
        Route::post('inventory/stock-count/confirm', [StockCountImportController::class, 'confirm']);
        Route::post('inventory/stock-count/cancel', [StockCountImportController::class, 'cancel']);
        Route::get('inventory/stock-count/history', [StockCountImportController::class, 'history']);
        Route::get('inventory/stock-count/{id}', [StockCountImportController::class, 'show']);

        Route::get('banks', [BanksController::class, 'index']);
        Route::apiResource('FactoryBankMovements', App\Http\Controllers\FactoryBankMovementsController::class);
        Route::apiResource('FactoryBankMovementsDetails', App\Http\Controllers\FactoryBankMovementsDetailsController::class);
        Route::apiResource('FactoryBankMovementsCustody', App\Http\Controllers\FactoryBankMovementsCustodyController::class);
        Route::apiResource('incomelist', App\Http\Controllers\IncomeListController::class);
        Route::get('banks/{id}', [BanksController::class, 'show']);
        Route::post('banks', [BanksController::class, 'store']);

        Route::get('banks/depositbank/{id}', [BanksController::class, 'depositBank']);
        Route::get('banks/editBankBalance/{id}', [BanksController::class, 'editBankBalance']);
        Route::get('banks/withDrawBank/{id}', [BanksController::class, 'withDrawBank']);
        Route::get('bank/transfermoney', [BanksController::class, 'transferMoney']);

        Route::get('expense/search', [App\Http\Controllers\ExpenseController::class, 'search']);
        Route::get('expense/purge-preview', [App\Http\Controllers\ExpenseController::class, 'purgePreview']);
        Route::post('expense/purge-all', [App\Http\Controllers\ExpenseController::class, 'purgeAll']);
        Route::post('editexpense/{id}', [App\Http\Controllers\ExpenseController::class, 'editExpense']);
        Route::post('deleteexpense/{id}', [App\Http\Controllers\ExpenseController::class, 'deleteExpense']);
        Route::apiResource('expense', App\Http\Controllers\ExpenseController::class);
        Route::get('expense_kind/search', [App\Http\Controllers\ExpenseKindController::class, 'search']);
        Route::get('expense_kind/ledger-accounts', [App\Http\Controllers\ExpenseKindController::class, 'ledgerAccounts']);
        Route::apiResource('expense_kind', App\Http\Controllers\ExpenseKindController::class);

        Route::apiResource('assets', App\Http\Controllers\AssetController::class);
        Route::post('assets/run-depreciation', [App\Http\Controllers\DepreciationController::class, 'runDepreciation']); // New Route
        Route::post('cimmitments/{id}/pay', [App\Http\Controllers\CimmitmentController::class, 'pay']);
        Route::apiResource('cimmitments', App\Http\Controllers\CimmitmentController::class);
        Route::get('covenants', [App\Http\Controllers\CovenantController::class, 'index']);
        Route::post('covenants', [App\Http\Controllers\CovenantController::class, 'store']);
        Route::apiResource('incomes', App\Http\Controllers\IncomeController::class);
    });

    /** كان stocks تحت Admin فقط؛ يُوسَّع ليطابق وصول المخازن (قسم أو RBAC). */
    Route::middleware([WarehouseStockApiAccess::class])->group(function () {
        Route::apiResource('stocks', stockController::class)->names('stock');
        Route::get('stock-transaction-types', [\App\Http\Controllers\Stock\StockTransactionController::class, 'typesIndex']);
        Route::get('stock-transactions', [\App\Http\Controllers\Stock\StockTransactionController::class, 'index']);
        Route::post('stock-transactions', [\App\Http\Controllers\Stock\StockTransactionController::class, 'store']);
        Route::get('stock-transactions/{id}', [\App\Http\Controllers\Stock\StockTransactionController::class, 'show']);
    });

    /** وحدات القياس — مواءمة مع حارس الواجهة (categories.view / categories.manage). */
    Route::middleware([MeasurementApiAccess::class])->group(function () {
        Route::get('measurements', [MeasurementController::class, 'index']);
        Route::get('measurements/{measurement}', [MeasurementController::class, 'show']);
        Route::post('measurements', [MeasurementController::class, 'store']);
        Route::put('measurements/{measurement}', [MeasurementController::class, 'update']);
        Route::delete('measurements/{measurement}', [MeasurementController::class, 'destroy']);
    });

    Route::middleware([MeasurementApiAccess::class])->group(function () {
        Route::get('item-classifications', [ItemClassificationController::class, 'index']);
        Route::post('item-classifications', [ItemClassificationController::class, 'store']);
        Route::delete('item-classifications/{id}', [ItemClassificationController::class, 'destroy']);
    });

    /** مسارات الأصناف — RBAC categories.view / categories.manage مع الأقسام السابقة */
    Route::middleware([CategoriesApiAccess::class])->group(function () {
        Route::get('categories/search', [CategoriesController::class, 'search']);
        Route::get('allcategories', [CategoriesController::class, 'allCategories']);
        Route::get('categories', [CategoriesController::class, 'index']);
        Route::get('getCategoryByStockId', [CategoriesController::class, 'getCategoryByStockId']);

        Route::post('categories', [CategoriesController::class, 'store']);
        Route::get('category/{id}', [CategoriesController::class, 'getCategoryById']);
        Route::post('editcategory/{id}', [CategoriesController::class, 'editCategory']);
        Route::post('categories/{id}/promote-to-finished', [CategoriesController::class, 'promoteToFinished']);
        Route::delete('deletecategory/{id}', [CategoriesController::class, 'deleteCategory']);
        Route::get('categories/{id}/links', [CategoriesController::class, 'categoryLinks']);
        Route::post('categories/merge-preview', [CategoriesController::class, 'mergeCategoryPreview']);
        Route::post('categories/merge', [CategoriesController::class, 'mergeCategory']);
        Route::get('categories/duplicate-groups', [CategoriesController::class, 'duplicateCategoryGroups']);
        Route::post('categories/merge-bulk', [CategoriesController::class, 'mergeCategoriesBulk']);
        Route::post('categories/force-delete-preview', [CategoriesController::class, 'forceDeleteCategoriesPreview']);
        Route::post('categories/force-delete-bulk', [CategoriesController::class, 'forceDeleteCategoriesBulk']);
        Route::post('categories/{id}/revision-roll-forward', [\App\Http\Controllers\ItemRecipeRevisionController::class, 'rollForward']);
        Route::get('categories/{id}/revision-lineage', [\App\Http\Controllers\ItemRecipeRevisionController::class, 'lineage']);
        Route::patch('/categories/{id}/quantity', [CategoriesController::class, 'changeCategoryQuantityss']);
        Route::patch('/categories/{id}/average-unit-cost', [CategoriesController::class, 'changeCategoryAverageUnitCost']);
        Route::put('categories/{category}', [CategoriesController::class, 'update']);
        Route::delete('categories/{category}', [CategoriesController::class, 'destroy']);
        Route::get('categories/{category}', [CategoriesController::class, 'show']);
    });

    Route::middleware([RecipeManufacturingApiAccess::class])->group(function () {
        Route::get('productions/{production}', [ProductionController::class, 'show']);

        Route::get('recipes', [\App\Http\Controllers\RecipeController::class, 'index']);
        Route::post('recipes', [\App\Http\Controllers\RecipeController::class, 'store']);
        Route::post('recipes/bulk-delete', [\App\Http\Controllers\RecipeController::class, 'bulkDestroy']);

        // Interactive Excel import (literal paths — must be before {id} wildcard)
        Route::post('recipes/import', [\App\Http\Controllers\RecipeImportController::class, 'preview']);
        Route::post('recipes/import/confirm', [\App\Http\Controllers\RecipeImportController::class, 'confirm']);
        Route::post('recipes/import/cancel', [\App\Http\Controllers\RecipeImportController::class, 'cancel']);

        // Recipe detail, CRUD, execution, stock check, movements
        Route::get('recipes/{id}', [\App\Http\Controllers\RecipeController::class, 'show'])->whereNumber('id');
        Route::put('recipes/{id}', [\App\Http\Controllers\RecipeController::class, 'update'])->whereNumber('id');
        Route::delete('recipes/{id}', [\App\Http\Controllers\RecipeController::class, 'destroy'])->whereNumber('id');
        Route::get('recipes/{id}/check-stock', [\App\Http\Controllers\RecipeController::class, 'checkStock'])->whereNumber('id');
        Route::post('recipes/{id}/execute', [\App\Http\Controllers\RecipeController::class, 'execute'])->whereNumber('id');
        Route::get('recipes/{id}/movements', [\App\Http\Controllers\RecipeController::class, 'movements'])->whereNumber('id');

        // Recipe extra costs (dynamic cost lines: machine, labor, overhead, etc.)
        Route::get('recipes/{recipeId}/extra-costs', [\App\Http\Controllers\RecipeExtraCostController::class, 'index'])->whereNumber('recipeId');
        Route::post('recipes/{recipeId}/extra-costs', [\App\Http\Controllers\RecipeExtraCostController::class, 'store'])->whereNumber('recipeId');
        Route::put('recipes/{recipeId}/extra-costs/{extraCostId}', [\App\Http\Controllers\RecipeExtraCostController::class, 'update'])->whereNumber(['recipeId', 'extraCostId']);
        Route::delete('recipes/{recipeId}/extra-costs/{extraCostId}', [\App\Http\Controllers\RecipeExtraCostController::class, 'destroy'])->whereNumber(['recipeId', 'extraCostId']);
        Route::get('recipes/{recipeId}/breakdown', [\App\Http\Controllers\RecipeExtraCostController::class, 'breakdown'])->whereNumber('recipeId');

        Route::post('productions', [ProductionController::class, 'store']);
        Route::put('productions/{production}', [ProductionController::class, 'update']);
        Route::delete('productions/{production}', [ProductionController::class, 'destroy']);
    });

    Route::middleware([ManufacturingModuleApiAccess::class])->group(function () {
        Route::post('manufacture', [App\Http\Controllers\ManufactureController::class, 'store']);
        Route::get('manufacture', [App\Http\Controllers\ManufactureController::class, 'index']);
        Route::get('manufacture/items-without-recipes', [App\Http\Controllers\ManufactureController::class, 'itemsWithoutRecipes']);
        Route::get('manufacture/recipe-product/{id}', [App\Http\Controllers\ManufactureController::class, 'recipeProduct'])->whereNumber('id');
        Route::get('manufacture/manfucture_by_warhouse', [App\Http\Controllers\ManufactureController::class, 'manfucture_by_warhouse']);
        Route::post('manufacture/confirm', [App\Http\Controllers\ManufactureController::class, 'confirm']);
        Route::post('manufacture/update-recipe-from-consumption', [App\Http\Controllers\ManufactureController::class, 'updateRecipeFromConsumption']);
        Route::post('manufacture/confirm/preview', [App\Http\Controllers\ManufactureController::class, 'previewConsumption']);
        Route::get('manufacture/confirmed', [App\Http\Controllers\ManufactureController::class, 'confirmed']);
        Route::get('manufacture/confirmed/deleted', [App\Http\Controllers\ManufactureController::class, 'confirmedDeleted']);
        Route::get('manufacture/done/{id}', [App\Http\Controllers\ManufactureController::class, 'done']);
        Route::delete('manufacture/confirmed/{id}', [App\Http\Controllers\ManufactureController::class, 'destroy'])->whereNumber('id');

        Route::get('manufacturing/production-orders', [ProductionOrderController::class, 'index']);
        Route::post('manufacturing/production-orders', [ProductionOrderController::class, 'store']);
        Route::get('manufacturing/production-orders/{id}', [ProductionOrderController::class, 'show'])->whereNumber('id');
        Route::post('manufacturing/production-orders/{id}/start', [ProductionOrderController::class, 'start'])->whereNumber('id');
        Route::post('manufacturing/production-orders/{id}/complete', [ProductionOrderController::class, 'complete'])->whereNumber('id');
        Route::post('manufacturing/production-orders/{id}/cancel', [ProductionOrderController::class, 'cancel'])->whereNumber('id');
    });

    Route::middleware([\App\Http\Middleware\ProcessingModuleApiAccess::class])->prefix('processing')->group(function () {
        Route::get('meta', [\App\Http\Controllers\Processing\ProcessingOrderController::class, 'meta']);
        Route::get('vendors', [\App\Http\Controllers\Processing\ProcessingOrderController::class, 'vendors']);
        Route::get('dashboard/kpis', [\App\Http\Controllers\Processing\ProcessingDashboardController::class, 'kpis']);
        Route::get('reports/materials-at-vendor', [\App\Http\Controllers\Processing\ProcessingDashboardController::class, 'materialsAtVendor']);
        Route::get('reports/vendor-balances', [\App\Http\Controllers\Processing\ProcessingDashboardController::class, 'vendorBalances']);
        Route::get('reports/aging', [\App\Http\Controllers\Processing\ProcessingDashboardController::class, 'aging']);

        Route::get('orders', [\App\Http\Controllers\Processing\ProcessingOrderController::class, 'index']);
        Route::post('orders', [\App\Http\Controllers\Processing\ProcessingOrderController::class, 'store']);
        Route::get('orders/{id}', [\App\Http\Controllers\Processing\ProcessingOrderController::class, 'show'])->whereNumber('id');
        Route::put('orders/{id}', [\App\Http\Controllers\Processing\ProcessingOrderController::class, 'update'])->whereNumber('id');
        Route::post('orders/{id}/approve', [\App\Http\Controllers\Processing\ProcessingOrderController::class, 'approve'])->whereNumber('id');

        Route::get('dispatches', [\App\Http\Controllers\Processing\ProcessingDispatchController::class, 'index']);
        Route::post('dispatches', [\App\Http\Controllers\Processing\ProcessingDispatchController::class, 'store']);
        Route::post('dispatches/voucher', [\App\Http\Controllers\Processing\ProcessingDispatchController::class, 'submitVoucher']);
        Route::get('dispatches/{id}', [\App\Http\Controllers\Processing\ProcessingDispatchController::class, 'show'])->whereNumber('id');
        Route::post('dispatches/{id}/post', [\App\Http\Controllers\Processing\ProcessingDispatchController::class, 'post'])->whereNumber('id');

        Route::get('receipts', [\App\Http\Controllers\Processing\ProcessingReceiptController::class, 'index']);
        Route::post('receipts', [\App\Http\Controllers\Processing\ProcessingReceiptController::class, 'store']);
        Route::get('receipts/{id}', [\App\Http\Controllers\Processing\ProcessingReceiptController::class, 'show'])->whereNumber('id');
        Route::post('receipts/{id}/post', [\App\Http\Controllers\Processing\ProcessingReceiptController::class, 'post'])->whereNumber('id');

        Route::get('invoices', [\App\Http\Controllers\Processing\ProcessingInvoiceController::class, 'index']);
        Route::post('invoices', [\App\Http\Controllers\Processing\ProcessingInvoiceController::class, 'store']);
        Route::get('invoices/{id}', [\App\Http\Controllers\Processing\ProcessingInvoiceController::class, 'show'])->whereNumber('id');
        Route::post('invoices/{id}/post', [\App\Http\Controllers\Processing\ProcessingInvoiceController::class, 'post'])->whereNumber('id');
    });

    Route::middleware([SettingsUserManagementApiAccess::class])->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::prefix('rbac')->group(function () {
            Route::get('roles', [\App\Http\Controllers\Rbac\RbacRoleController::class, 'index']);
        });
    });

    Route::middleware([SystemAdminToolsApiAccess::class])->group(function () {
        Route::get('users/compact-directory', [App\Http\Controllers\UserController::class, 'compactUserDirectory']);
        Route::get('tracking', [OrdersController::class, 'getTrackings']);
        Route::post('tracking/undo', [OrdersController::class, 'undo']);
        Route::get('getActions', [OrdersController::class, 'getActions']);
    });

    Route::middleware(['permission:system.activity_log'])->group(function () {
        Route::get('activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index']);
        Route::get('activity-logs/modules', [\App\Http\Controllers\ActivityLogController::class, 'modules']);
    });

    Route::middleware(['department.access:Admin'])->group(function () {
        Route::get('allnotification', [App\Http\Controllers\NotificationController::class, 'allNotifiy']);
        Route::delete('notification/delete/{id}', [App\Http\Controllers\NotificationController::class, 'destroy']);

        Route::get('pendingBanks', [PendingBankBalanceController::class, 'pendingBanks']);
        Route::post('pendingBanks', [PendingBankBalanceController::class, 'pendingBanksStatus']);


        Route::get('users', [App\Http\Controllers\UserController::class, 'index']);
        Route::delete('user/delete/{id}', [App\Http\Controllers\AuthController::class, 'destroy']);

        Route::apiResource('approvals', App\Http\Controllers\ApprovalsController::class);

        Route::post('order_source', [OrderSourceController::class, 'store']);
        Route::post('revieworder', [OrdersController::class, 'revieworder']);
        Route::get('readtempreview/{id}', [OrdersController::class, 'readTempReviewOrder']);

        Route::get('shippinglines/{shippingline}', [ShippingLineController::class, 'show']);
        Route::post('shippinglines', [ShippingLineController::class, 'store']);
        Route::put('shippinglines/{shippingline}', [ShippingLineController::class, 'update']);
        Route::delete('shippinglines/{shippingline}', [ShippingLineController::class, 'destroy']);

        Route::post('orders/accounting/reconcile/preview', [\App\Http\Controllers\OrderAccountingReconcileController::class, 'preview']);
        Route::post('orders/accounting/reconcile/run', [\App\Http\Controllers\OrderAccountingReconcileController::class, 'run']);

        // V2
        //  Route::apiResource('tree_accounts', TreeAccountController::class)->names('tree_account');

        Route::prefix('rbac')->group(function () {
            Route::get('permissions', [\App\Http\Controllers\Rbac\RbacPermissionController::class, 'index']);
            Route::post('permissions', [\App\Http\Controllers\Rbac\RbacPermissionController::class, 'store']);
            Route::put('permissions/{permission}', [\App\Http\Controllers\Rbac\RbacPermissionController::class, 'update']);
            Route::delete('permissions/{permission}', [\App\Http\Controllers\Rbac\RbacPermissionController::class, 'destroy']);
            Route::post('permissions/bulk-roles', [\App\Http\Controllers\Rbac\RbacPermissionController::class, 'bulkAssignRoles']);

            Route::get('roles/{role}', [\App\Http\Controllers\Rbac\RbacRoleController::class, 'show']);
            Route::post('roles', [\App\Http\Controllers\Rbac\RbacRoleController::class, 'store']);
            Route::put('roles/{role}', [\App\Http\Controllers\Rbac\RbacRoleController::class, 'update']);
            Route::delete('roles/{role}', [\App\Http\Controllers\Rbac\RbacRoleController::class, 'destroy']);
            Route::post('roles/{role}/clone', [\App\Http\Controllers\Rbac\RbacRoleController::class, 'clone']);
            Route::put('roles/{role}/permissions', [\App\Http\Controllers\Rbac\RbacRoleController::class, 'syncPermissions']);

            Route::get('users/{user}/matrix', [\App\Http\Controllers\Rbac\RbacUserAccessController::class, 'matrix']);
            Route::put('users/{user}/access', [\App\Http\Controllers\Rbac\RbacUserAccessController::class, 'update']);

            Route::get('department-templates', [\App\Http\Controllers\Rbac\RbacDepartmentTemplateController::class, 'show']);
            Route::get('department-suggestions', [\App\Http\Controllers\Rbac\RbacDepartmentTemplateController::class, 'suggest']);
            Route::post('department-templates', [\App\Http\Controllers\Rbac\RbacDepartmentTemplateController::class, 'store']);
            Route::post('department-templates/sample', [\App\Http\Controllers\Rbac\RbacDepartmentTemplateController::class, 'touchSample']);
            Route::delete('department-templates', [\App\Http\Controllers\Rbac\RbacDepartmentTemplateController::class, 'destroy']);
        });

        Route::post('give_permission/{id}', [App\Http\Controllers\UserController::class, 'give_permission']);
        Route::post('revoke_permssion/{id}', [App\Http\Controllers\UserController::class, 'revoke_permssion']);
        Route::get('user_permission/{id}', [App\Http\Controllers\UserController::class, 'user_permission']);

    });

    Route::middleware(['order.profile:ship_collect'])->group(function () {
        Route::post('shiporder/{id}', [OrdersController::class, 'ship_order']);
        Route::get('order/{id}/delivery-transfer-check', [OrdersController::class, 'delivery_transfer_check']);
        Route::post('order/{id}/deliver', [OrdersController::class, 'deliver_order']);
        Route::post('orders/bulk-deliver', [OrdersController::class, 'bulk_deliver_orders']);
        Route::post('collectorder/{id}', [OrdersController::class, 'collect_order']);
        Route::post('collectorder-bulk', [OrdersController::class, 'bulk_collect_orders']); 
        Route::get('order/received/{id}', [OrdersController::class, 'received']);
        Route::get('orders/{id}/rollback/preview', [\App\Http\Controllers\OrderRollbackController::class, 'preview']);
        Route::post('orders/{id}/rollback', [\App\Http\Controllers\OrderRollbackController::class, 'execute']);
    });

    Route::middleware(['order.profile:part_shipment'])->group(function () {
        Route::post('partcollectorder/{id}', [OrdersController::class, 'partCollect_order']); // part
        Route::get('addshippmentnumber/{id}', [OrdersController::class, 'addShippmentNumber']);
    });

    Route::middleware([EmployeesHrApiAccess::class])->group(function () {
        Route::get('employees/search', [App\Http\Controllers\EmployeeController::class, 'search']);
        Route::get('employeepermonth/{id}', [App\Http\Controllers\EmployeeController::class, 'employeePerMonth']);
        Route::get('employeespermonth', [App\Http\Controllers\EmployeeController::class, 'employeesPerMonth']);
        Route::get('getEmpDataPerMonth', [App\Http\Controllers\EmployeeController::class, 'getEmpsDataPerMonth']);
        Route::get('getEmpDataPerMonth/{id}', [App\Http\Controllers\EmployeeController::class, 'getEmpDataPerMonth']);
        Route::post('empHoursPermission', [App\Http\Controllers\EmployeeController::class, 'empHoursPermission']);
        Route::post('empHoursPermissionall', [App\Http\Controllers\EmployeeController::class, 'empHoursPermissionAll']);
        Route::post('employees/edit/{id}', [App\Http\Controllers\EmployeeController::class, 'edit']);
        Route::patch('employees/{id}/payable-account', [App\Http\Controllers\EmployeeController::class, 'updatePayableAccount']);
        Route::get('employees/absences', [App\Http\Controllers\EmployeeSubtractionController::class, 'employeesAbsences']);
        Route::post('employee/absencestatus', [App\Http\Controllers\EmployeeSubtractionController::class, 'absenceStatus']);
        Route::get('employees/accountstatment', [App\Http\Controllers\EmployeeController::class, 'accountStatment']);
        Route::get('employees/accountstatment/reviewed/{id}', [App\Http\Controllers\EmployeeController::class, 'reviewedStatus']);
        Route::post('employees/excelfingerprintdata', [App\Http\Controllers\EmployeeController::class, 'saveExcelFingerPrintData']);
        Route::post('employees/link-payable-accounts', [App\Http\Controllers\EmployeeController::class, 'linkPayableAccounts']);
        Route::post('updatefingerprintsheet', [App\Http\Controllers\EmployeeFingerPrintSheetController::class, 'update']);
        Route::get('fingerprint-sheet-logs/{id}', [App\Http\Controllers\EmployeeFingerPrintSheetController::class, 'logs']);
        Route::post('addCheckOut/{id}', [App\Http\Controllers\EmployeeFingerPrintSheetController::class, 'addCheckOut']);
        Route::post('editCheckInOrOut/{id}', [App\Http\Controllers\EmployeeFingerPrintSheetController::class, 'editCheckInOrOut']);
        Route::post('changeCheckIn/{id}', [App\Http\Controllers\EmployeeFingerPrintSheetController::class, 'changeCheckIn']);
        Route::get('reviewMonth', [App\Http\Controllers\EmployeeFingerPrintSheetController::class, 'reviewMonth']);
        Route::post('absencededuction', [App\Http\Controllers\EmployeeFingerPrintSheetController::class, 'absenceDeduction']);
        Route::post('addFixedChangedSalary', [App\Http\Controllers\EmployeeMeritsController::class, 'addFixedChangedSalary']);
        Route::apiResource('employees', App\Http\Controllers\EmployeeController::class);
        Route::apiResource('employeemerit', App\Http\Controllers\EmployeeMeritsController::class);
        Route::apiResource('employeesubtraction', App\Http\Controllers\EmployeeSubtractionController::class);
        Route::apiResource('employeeadvancepayment', App\Http\Controllers\EmployeeAdvancePaymentController::class);
        Route::post('employeemonthpaid/bulk', [App\Http\Controllers\EmployeeMonthPaidController::class, 'bulkStore']);
        Route::apiResource('employeemonthpaid', App\Http\Controllers\EmployeeMonthPaidController::class);
    });

    // new role Corparates
    Route::middleware([CorporateSalesApiAccess::class])->group(function () {
        Route::post('googlesheet/{sheet}', [App\Http\Controllers\GoogleController::class, 'addData']);

        Route::post('edit-lead', [CorporateSalesLeadController::class, 'edit']);
        Route::get('lead/team-users', [CorporateSalesLeadController::class, 'getLeadTeamUsers']);
        Route::get('lead/activity-stats', [CorporateSalesLeadController::class, 'getLeadActivityStats']);
        Route::get('lead/recommenders/pending', [CorporateSalesLeadController::class, 'getPendingRecommenders']);
        Route::delete('lead/recommenders/{id}', [CorporateSalesLeadController::class, 'deleteRecommender']);
        Route::patch('lead/recommenders/{id}/toggle-done', [CorporateSalesLeadController::class, 'toggleRecommenderDone']);
        Route::apiResource('lead', App\Http\Controllers\CorporateSalesLeadController::class);
        Route::apiResource('lead-source', App\Http\Controllers\CorporateSalesLeadSourceController::class);
        Route::apiResource('lead-tool', App\Http\Controllers\CorporateSalesLeadToolController::class);
        Route::apiResource('lead-industry', App\Http\Controllers\CorporateSalesIndustryController::class);
        
        // Lead Status Management Routes ( literals قبل apiResource حتى لا تُفعَّل كـ {lead_status} )
        Route::get('lead-statuses/active', [App\Http\Controllers\LeadStatusController::class, 'getActive']);
        Route::get('lead-statuses/closed-won', [App\Http\Controllers\LeadStatusController::class, 'getClosedWon']);
        Route::get('lead-statuses/closed-lost', [App\Http\Controllers\LeadStatusController::class, 'getClosedLost']);
        Route::get('lead-statuses/follow-up-leads', [App\Http\Controllers\LeadStatusController::class, 'getFollowUpLeads']);
        Route::put('lead-statuses/lead/{lead}', [App\Http\Controllers\LeadStatusController::class, 'updateLeadStatus']);
        Route::apiResource('lead-statuses', App\Http\Controllers\LeadStatusController::class);
    });

    Route::middleware(['order.profile:orders_create'])->group(function () {
        Route::post('orders', [OrdersController::class, 'store']);
        Route::get('phonenumbers', [OrdersController::class, 'phoneNumbers']);
        Route::post('shipping_methods', [ShippingMethodsController::class, 'store']);
    });

    Route::middleware([CustomerCompanyIndexApiAccess::class])->group(function () {
        Route::get('companies', [CustomerCompanyController::class, 'index']);
    });

    Route::middleware(['order.profile:companies_main'])->group(function () {
        Route::post('companies', [CustomerCompanyController::class, 'store']);
        Route::get('getOrdersNumbers', [OrdersController::class, 'getOrdersNumbers']);
        Route::apiResource('ShippingLineStatement', App\Http\Controllers\ShippingLineStatementController::class);
    });

    Route::middleware(['order.profile:companies_balance'])->group(function () {
        Route::get('companies/unlinked-summary', [CustomerCompanyController::class, 'unlinkedSummary']);
        Route::post('companies/link-unlinked', [CustomerCompanyController::class, 'linkUnlinked']);
        Route::post('companies/{id}/link-account', [CustomerCompanyController::class, 'linkAccount']);
        Route::put('companies/{id}', [CustomerCompanyController::class, 'update']);
        Route::get('companies/search', [CustomerCompanyController::class, 'search']);
        Route::get('companies/{id}', [CustomerCompanyController::class, 'customerCompanyBalance']);
        Route::post('companies/companycollect/{id}', [CustomerCompanyController::class, 'companyCollect']);
    });

    Route::middleware(['order.profile:edit_order'])->group(function () {
        Route::post('editorder/{id}', [OrdersController::class, 'edit']);
        Route::post('orders/{id}/cancel-lines', [OrdersController::class, 'cancelOrderLines'])->whereNumber('id');
    });

    Route::middleware(['order.profile:confirm_order'])->group(function () {
        Route::post('confirm/{id}', [OrdersController::class, 'confirm']);
    });

    Route::middleware(['order.profile:refuse_maintain'])->group(function () {
        Route::get('refuseorder/{id}', [OrdersController::class, 'refuseOrder']);
        Route::post('order/maintained/{id}', [OrdersController::class, 'maintained']);
    });

    Route::middleware(['order.profile:shipping_company_crud'])->group(function () {
        Route::get('shippingcompanies', [ShippingCompanyController::class, 'index']);
    });

    Route::middleware(['order.profile:shipping_company_manage'])->group(function () {
        Route::get('shippingcompanies/unlinked-summary', [ShippingCompanyController::class, 'unlinkedSummary']);
        Route::post('shippingcompanies/link-unlinked', [ShippingCompanyController::class, 'linkUnlinked']);
        Route::post('shippingcompanies/{id}/link-account', [ShippingCompanyController::class, 'linkAccount']);
        Route::get('shippingcompanies/{id}/reconcile-count', [ShippingCompanyController::class, 'reconcileCount']);
        Route::get('shippingcompanies/{id}/reconcile-orders', [ShippingCompanyController::class, 'reconcileOrders']);
        Route::post('shippingcompanies/{id}/reconcile-receivables', [ShippingCompanyController::class, 'reconcileReceivables']);
        Route::post('shippingcompanies', [ShippingCompanyController::class, 'store']);
        Route::put('shippingcompanies/{shippingcompany}', [ShippingCompanyController::class, 'update']);
        Route::delete('shippingcompanies/{shippingcompany}', [ShippingCompanyController::class, 'destroy']);
    });

    Route::middleware(['order.profile:shipping_company_crud'])->group(function () {
        Route::get('shippingcompanies/{shippingcompany}', [ShippingCompanyController::class, 'show']);
    });

    Route::middleware(['order.profile:shipping_company_statement'])->group(function () {
        Route::get('shippingcompany/search', [ShippingCompanyController::class, 'search']);
        Route::get('shippingcompany/{id}', [ShippingCompanyController::class, 'show']);
    });

    Route::middleware(['order.profile:shipping_company_crud'])->group(function () {
        Route::get('collection-companies/select', [CollectionCompanyController::class, 'select']);
        Route::get('collection-companies', [CollectionCompanyController::class, 'index']);
    });

    Route::middleware(['order.profile:shipping_company_manage'])->group(function () {
        Route::get('collection-companies/unlinked-summary', [CollectionCompanyController::class, 'unlinkedSummary']);
        Route::post('collection-companies/link-unlinked', [CollectionCompanyController::class, 'linkUnlinked']);
        Route::post('collection-companies/{collectionCompany}/link-account', [CollectionCompanyController::class, 'linkAccount']);
        Route::get('collection-companies/{collectionCompany}/reconcile-count', [CollectionCompanyController::class, 'reconcileCount']);
        Route::get('collection-companies/{collectionCompany}/reconcile-orders', [CollectionCompanyController::class, 'reconcileOrders']);
        Route::post('collection-companies/{collectionCompany}/reconcile-receivables', [CollectionCompanyController::class, 'reconcileReceivables']);
        Route::post('collection-companies', [CollectionCompanyController::class, 'store']);
        Route::put('collection-companies/{collectionCompany}', [CollectionCompanyController::class, 'update']);
        Route::delete('collection-companies/{collectionCompany}', [CollectionCompanyController::class, 'destroy']);
    });

    Route::middleware(['order.profile:shipping_company_crud'])->group(function () {
        Route::get('collection-companies/{collectionCompany}', [CollectionCompanyController::class, 'show']);
    });

    Route::middleware(['order.profile:ship_collect'])->group(function () {
        Route::get('orders/{id}/fulfillment', [OrderFulfillmentController::class, 'show']);
        Route::put('orders/{id}/fulfillment/providers', [OrderFulfillmentController::class, 'assignProviders']);
        Route::post('orders/{id}/fulfillment/transfer-liability', [OrderFulfillmentController::class, 'transferLiability']);
    });

    Route::middleware(['order.profile:collection_settlements'])->group(function () {
        Route::get('settlements', [SettlementController::class, 'index']);
        Route::get('settlements/{settlement}', [SettlementController::class, 'show']);
        Route::post('settlements', [SettlementController::class, 'store']);
        Route::post('settlements/{settlement}/post', [SettlementController::class, 'post']);
    });

    Route::middleware(['order.profile:change_status'])->group(function () {
        Route::get('changestatus/{id}', [OrdersController::class, 'change_status']);
    });

    Route::middleware(['order.profile:vip_shortage'])->group(function () {
        Route::get('vip/{id}', [OrdersController::class, 'vip']);
        Route::get('shortage/{id}', [OrdersController::class, 'shortage']);
    });

    Route::middleware(['order.profile:offer_crud'])->group(function () {
        Route::apiResource('offer', App\Http\Controllers\OffersController::class);
    });

    Route::middleware(['order.profile:review_temp'])->group(function () {
        Route::post('userrevieworder', [OrdersController::class, 'userReviewOrder']);
        Route::get('tempreview/{id}', [OrdersController::class, 'userTempReviewOrder']);
    });

    Route::middleware(['order.profile:add_note'])->group(function () {
        Route::get('addnote/{id}', [OrdersController::class, 'addNote']);
        Route::post('addnote/{id}', [OrdersController::class, 'addNote']);
        Route::put('updatenote/{id}', [OrdersController::class, 'updateNote']);
    });
});

// http://127.0.0.1:8000/api/transactions/by-customer-order/detailed?itemsPerPage=15&page=1&customer=01018816899
// http://127.0.0.1:8000/api/transactions/by-customer-order/detailed?itemsPerPage=15&page=1&customer=01018816899


Route::middleware('auth')->group(function () {
    Route::get('transactions/by-customer-order/search', [OrdersController::class, 'allUserUnique']);
    Route::get('transactions/by-customer-order/detailed', [TransactionController::class, 'index']);
    Route::middleware(['permission:finance.tree_account.balance_adjustment|system.rbac'])->group(function () {
        Route::post('tree_accounts/{id}/balance-adjustment', [TreeAccountController::class, 'balanceAdjustment']);
        Route::post('tree_accounts/bulk-balance-adjustment', [TreeAccountController::class, 'bulkBalanceAdjustment']);
    });
    Route::middleware(['department.access:Admin'])->group(function () {
        Route::get('tree_accounts/trash', [TreeAccountController::class, 'trash']);
        Route::post('tree_accounts/{id}/restore', [TreeAccountController::class, 'restore']);
        Route::delete('tree_accounts/{id}/force', [TreeAccountController::class, 'forceDestroy']);
    });
    Route::get('tree_accounts/{id}/audits', [TreeAccountController::class, 'audits']);
    Route::apiResource('tree_accounts', TreeAccountController::class)->names('tree_account');
});

// Accounting Routes
Route::prefix('accounting/')->middleware('auth')->group(function () {
    // Vouchers
    Route::prefix('vouchers/')->group(function () {
        Route::get('/', [App\Http\Controllers\V2\Accounting\VoucherController::class, 'index']);
        Route::get('/client-options', [App\Http\Controllers\V2\Accounting\VoucherController::class, 'clientOptions']);
        Route::post('/', [App\Http\Controllers\V2\Accounting\VoucherController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\V2\Accounting\VoucherController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\V2\Accounting\VoucherController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\V2\Accounting\VoucherController::class, 'destroy']);
    });

    // Cost Centers
    Route::prefix('cost-centers/')->group(function () {
        Route::get('/', [App\Http\Controllers\V2\Accounting\CostCenterController::class, 'index']);
        Route::get('/tree', [App\Http\Controllers\V2\Accounting\CostCenterController::class, 'tree']);
        Route::post('/', [App\Http\Controllers\V2\Accounting\CostCenterController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\V2\Accounting\CostCenterController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\V2\Accounting\CostCenterController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\V2\Accounting\CostCenterController::class, 'destroy']);
    });

    // Safes
    Route::prefix('safes/')->group(function () {
        Route::get('/', [App\Http\Controllers\V2\Accounting\SafeController::class, 'index']);
        Route::post('/', [App\Http\Controllers\V2\Accounting\SafeController::class, 'store']);
        Route::post('/transfer', [App\Http\Controllers\V2\Accounting\SafeController::class, 'transfer']);
        Route::post('/direct-transaction', [App\Http\Controllers\V2\Accounting\SafeController::class, 'directTransaction']);
        Route::get('/direct-transactions', [App\Http\Controllers\V2\Accounting\SafeController::class, 'listDirectTransactions']);
        Route::get('/direct-transactions/{id}', [App\Http\Controllers\V2\Accounting\SafeController::class, 'showDirectTransaction']);
        Route::put('/direct-transactions/{id}', [App\Http\Controllers\V2\Accounting\SafeController::class, 'updateDirectTransaction']);
        Route::get('/{id}', [App\Http\Controllers\V2\Accounting\SafeController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\V2\Accounting\SafeController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\V2\Accounting\SafeController::class, 'destroy']);
    });

    // Daily Entries
    Route::prefix('daily-entries/')->group(function () {
        Route::get('/', [App\Http\Controllers\V2\Accounting\DailyEntryController::class, 'index']);
        Route::get('/users', [App\Http\Controllers\V2\Accounting\DailyEntryController::class, 'users']);
        Route::post('/', [App\Http\Controllers\V2\Accounting\DailyEntryController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\V2\Accounting\DailyEntryController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\V2\Accounting\DailyEntryController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\V2\Accounting\DailyEntryController::class, 'destroy']);
    });

    // Banks
    Route::prefix('banks/')->group(function () {
        Route::get('/', [App\Http\Controllers\V2\Accounting\BankController::class, 'index']);
        Route::post('/', [App\Http\Controllers\V2\Accounting\BankController::class, 'store']);
        Route::post('/transfer', [App\Http\Controllers\V2\Accounting\BankController::class, 'transfer']);
        Route::post('/direct-transaction', [App\Http\Controllers\V2\Accounting\BankController::class, 'directTransaction']);
        Route::get('/direct-transactions', [App\Http\Controllers\V2\Accounting\BankController::class, 'listDirectTransactions']);
        Route::get('/direct-transactions/{id}', [App\Http\Controllers\V2\Accounting\BankController::class, 'showDirectTransaction']);
        Route::put('/direct-transactions/{id}', [App\Http\Controllers\V2\Accounting\BankController::class, 'updateDirectTransaction']);
        Route::get('/{id}/users', [App\Http\Controllers\V2\Accounting\BankController::class, 'assignedUsers']);
        Route::put('/{id}/users', [App\Http\Controllers\V2\Accounting\BankController::class, 'syncAssignedUsers']);
        Route::get('/{id}', [App\Http\Controllers\V2\Accounting\BankController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\V2\Accounting\BankController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\V2\Accounting\BankController::class, 'destroy']);
    });

    // Capitals
    Route::prefix('capitals/')->group(function () {
        Route::get('/', [App\Http\Controllers\V2\Accounting\CapitalController::class, 'index']);
        Route::post('/', [App\Http\Controllers\V2\Accounting\CapitalController::class, 'store']);
    });

    // Payment sources (unified: safes, banks, service accounts) for payment follow-up
    Route::get('payment-sources', [App\Http\Controllers\V2\Accounting\PaymentSourcesController::class, 'index']);

    // Reports
    Route::prefix('reports/')->group(function () {
        Route::get('/daily-ledger', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'dailyLedger']);
        Route::get('/account-balance', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'accountBalance']);
        Route::get('/trial-balance', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'trialBalance']);
        Route::get('/accounting-tree', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'accountingTree']);
        Route::get('/account-statement', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'accountStatement']);
        Route::get('/account-hierarchy', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'getAccountHierarchy']);
        Route::get('/validate-income-structure', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'validateIncomeStructure']);
        Route::get('/income-statement', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'incomeStatement']);
        Route::get('/product-performance', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'productPerformance']);
        Route::get('/category-profitability', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'categoryProfitability']);
    });

    // Accounting Transactions (no nested accounting/ prefix)
    Route::post('/process-cash-transaction', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'processCashTransaction']);
    Route::post('/update-hierarchy-balances', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'updateHierarchyBalances']);
    Route::post('/recalculate-all-hierarchy-balances', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'recalculateAllHierarchyBalances']);
    // Service Accounts
    Route::prefix('service-accounts/')->group(function () {
        Route::get('/', [App\Http\Controllers\ServiceAccountsController::class, 'index']);
        Route::post('/', [App\Http\Controllers\ServiceAccountsController::class, 'store']);
        Route::post('/transfer', [App\Http\Controllers\ServiceAccountsController::class, 'transfer']);
        Route::put('/{id}', [App\Http\Controllers\ServiceAccountsController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\ServiceAccountsController::class, 'destroy']);
    });

    // Settings
    Route::get('/settings', [App\Http\Controllers\SettingController::class, 'getSettings']);
    Route::post('/settings', [App\Http\Controllers\SettingController::class, 'updateSettings']);
    Route::post('/settings/update-existing', [App\Http\Controllers\SettingController::class, 'updateExistingEntities']);
});

Route::prefix('report/')->middleware('auth')->group(function () {
    Route::get('order', [ReportOrderController::class, 'AllOrder']);
    Route::get('getByOrderId', [ReportOrderController::class, 'getByOrderId']);
});


