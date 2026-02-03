<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BanksController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\MeasurementController;
use App\Http\Controllers\OrderSourceController;
use App\Http\Controllers\ShippingLineController;
use App\Http\Controllers\V2\stock\stockController;
use App\Http\Controllers\CustomerCompanyController;
use App\Http\Controllers\ShippingCompanyController;
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

 Route::get('order_source', [OrderSourceController::class, 'index']);
 Route::get('shipping_methods', [ShippingMethodsController::class, 'index']);
 Route::get('shippinglines', [ShippingLineController::class, 'index']);
 Route::get('orders/search', [OrdersController::class, 'search']);
 Route::get('orders/{id}', [OrdersController::class, 'show']);

 Route::get('productions', [ProductionController::class, 'index']);
 Route::get('shippingcompanySelect', [ShippingCompanyController::class, 'shippingcompanySelect']);
 Route::get('bankSelect', [BanksController::class, 'bankSelect']);
 Route::get('categories/cat_orders', [CategoriesController::class, 'categories_for_orders']);

 // Reports and charts
 Route::get('reports/categoriesSellReports', [App\Http\Controllers\CategoriesController::class, 'categoriesSellReports']);

 Route::middleware(['department.access:Admin,Account Management,Logistics Specialist'])->group(function () {
  Route::get('categories/warehouse_balance', [CategoriesController::class, 'warehouse_balance']);
  Route::get('categories/categories_details/{id}', [CategoriesController::class, 'categories_details']);
  Route::get('categories/warehousedetails', [CategoriesController::class, 'warehouseDetails']);
  Route::get('categories/categoryDetailsByWherehouse', [CategoriesController::class, 'categoryDetailsByWherehouse']);
  Route::get('categoryquantity', [CategoriesController::class, 'changeCategoryQuantity']);
  Route::get('categories/monthlyinventory', [CategoriesController::class, 'monthlyInventory']);
  Route::get('categories/monthlyInventoryDetailsByWherehouse', [CategoriesController::class, 'monthlyInventoryDetailsByWherehouse']);
  Route::get('categories/categoryByWarehouse', [CategoriesController::class, 'categoryByWarehouse']);


  Route::get('suppliers/search', [SupplierController::class, 'search']);
  Route::post('suppliers/StoreSupplierType', [SupplierController::class, 'StoreSupplierType']);
  Route::delete('suppliers/deleteType/{id}', [SupplierController::class, 'deleteType']);
  Route::post('suppliers/supplierPay/{id}', [SupplierController::class, 'supplierPay']);
  Route::get('suppliers/getAllSupplierTypes', [SupplierController::class, 'getAllSupplierTypes']);
  Route::get('suppliers/supplierDetails/{id}', [SupplierController::class, 'supplier_details']);
  Route::get('suppliers/supplier_names', [SupplierController::class, 'supplier_names']);
  Route::apiResource('suppliers', App\Http\Controllers\SupplierController::class);

  Route::get('purchases/search', [App\Http\Controllers\PurchasesController::class, 'search']);
  Route::get('purchases/{id}', [App\Http\Controllers\PurchasesController::class, 'show']);
  Route::apiResource('purchases', App\Http\Controllers\PurchasesController::class);

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
  Route::post('editexpense/{id}', [App\Http\Controllers\ExpenseController::class, 'editExpense']);
  Route::post('deleteexpense/{id}', [App\Http\Controllers\ExpenseController::class, 'deleteExpense']);
  Route::apiResource('expense', App\Http\Controllers\ExpenseController::class);
  Route::get('expense_kind/search', [App\Http\Controllers\ExpenseKindController::class, 'search']);
  Route::apiResource('expense_kind', App\Http\Controllers\ExpenseKindController::class);

  Route::apiResource('assets', App\Http\Controllers\AssetController::class);
  Route::post('assets/run-depreciation', [App\Http\Controllers\DepreciationController::class, 'runDepreciation']); // New Route
  Route::apiResource('cimmitments', App\Http\Controllers\CimmitmentController::class);
  Route::apiResource('incomes', App\Http\Controllers\IncomeController::class);
 });

 Route::middleware(['department.access:Admin,Data Entry,Account Management,Logistics Specialist,Customer Service'])->group(function () {
  Route::get('categories/search', [CategoriesController::class, 'search']);
  Route::patch('/categories/{id}/quantity', [CategoriesController::class, 'changeCategoryQuantityss']);

  Route::get('productions/{production}', [ProductionController::class, 'show']);
  Route::get('measurements', [MeasurementController::class, 'index']);
  Route::get('measurements/{measurement}', [MeasurementController::class, 'show']);
 });

 Route::middleware(['department.access:Admin,Data Entry,Account Management,Logistics Specialist'])->group(function () {
  Route::get('allcategories', [CategoriesController::class, 'allCategories']);
  Route::get('categories', [CategoriesController::class, 'index']);
  Route::get('getCategoryByStockId', [CategoriesController::class, 'getCategoryByStockId']);

  Route::post('categories', [CategoriesController::class, 'store']);
  Route::PATCH('categories/{id}/update-code', [CategoriesController::class, 'updateCode']);

  Route::get('category/{id}', [CategoriesController::class, 'getCategoryById']);
  Route::post('editcategory/{id}', [CategoriesController::class, 'editCategory']);
  Route::delete('deletecategory/{id}', [CategoriesController::class, 'deleteCategory']);
  Route::put('categories/{category}', [CategoriesController::class, 'update']);
  Route::delete('categories/{category}', [CategoriesController::class, 'destroy']);
  Route::get('categories/{category}', [CategoriesController::class, 'show']);

  Route::post('productions', [ProductionController::class, 'store']);
  Route::put('productions/{production}', [ProductionController::class, 'update']);
  Route::delete('productions/{production}', [ProductionController::class, 'destroy']);

  Route::post('measurements', [MeasurementController::class, 'store']);
  Route::put('measurements/{measurement}', [MeasurementController::class, 'update']);
  Route::delete('measurements/{measurement}', [MeasurementController::class, 'destroy']);
 });

 Route::middleware(['department.access:Admin'])->group(function () {
  Route::get('allnotification', [App\Http\Controllers\NotificationController::class, 'allNotifiy']);
  Route::delete('notification/delete/{id}', [App\Http\Controllers\NotificationController::class, 'destroy']);

  Route::post('manufacture', [App\Http\Controllers\ManufactureController::class, 'store']);
  Route::get('manufacture', [App\Http\Controllers\ManufactureController::class, 'index']);
  Route::get('manufacture/manfucture_by_warhouse', [App\Http\Controllers\ManufactureController::class, 'manfucture_by_warhouse']);
  Route::post('manufacture/confirm', [App\Http\Controllers\ManufactureController::class, 'confirm']);
  Route::get('manufacture/confirmed', [App\Http\Controllers\ManufactureController::class, 'confirmed']);
  Route::get('manufacture/done/{id}', [App\Http\Controllers\ManufactureController::class, 'done']);

  Route::get('pendingBanks', [PendingBankBalanceController::class, 'pendingBanks']);
  Route::post('pendingBanks', [PendingBankBalanceController::class, 'pendingBanksStatus']);


  Route::get('users', [App\Http\Controllers\UserController::class, 'index']);
  Route::delete('user/delete/{id}', [App\Http\Controllers\AuthController::class, 'destroy']);
  Route::post('register', [App\Http\Controllers\AuthController::class, 'register']);

  Route::apiResource('approvals', App\Http\Controllers\ApprovalsController::class);

  Route::post('order_source', [OrderSourceController::class, 'store']);
  Route::post('revieworder', [OrdersController::class, 'revieworder']);
  Route::get('readtempreview/{id}', [OrdersController::class, 'readTempReviewOrder']);

  Route::get('shippinglines/{shippingline}', [ShippingLineController::class, 'show']);
  Route::post('shippinglines', [ShippingLineController::class, 'store']);
  Route::put('shippinglines/{shippingline}', [ShippingLineController::class, 'update']);
  Route::delete('shippinglines/{shippingline}', [ShippingLineController::class, 'destroy']);

  Route::get('tracking', [OrdersController::class, 'getTrackings']);
  Route::post('tracking/undo', [OrdersController::class, 'undo']);
  Route::get('getActions', [OrdersController::class, 'getActions']);

  // V2
  //  Route::apiResource('tree_accounts', TreeAccountController::class)->names('tree_account');
  Route::apiResource('stocks', stockController::class)->names('stock');
 });

 Route::middleware(['department.access:Admin,Operation Management,Operation Specialist,Logistics Specialist'])->group(function () {
  Route::post('shiporder/{id}', [OrdersController::class, 'ship_order']);
  Route::post('collectorder/{id}', [OrdersController::class, 'collect_order']); // all 
  Route::get('order/received/{id}', [OrdersController::class, 'received']);
 });

 Route::middleware(['department.access:Admin,Operation Management'])->group(function () {
  Route::get('employees/search', [App\Http\Controllers\EmployeeController::class, 'search']);
  Route::get('employeepermonth/{id}', [App\Http\Controllers\EmployeeController::class, 'employeePerMonth']);
  Route::get('employeespermonth', [App\Http\Controllers\EmployeeController::class, 'employeesPerMonth']);
  Route::get('getEmpDataPerMonth', [App\Http\Controllers\EmployeeController::class, 'getEmpsDataPerMonth']);
  Route::get('getEmpDataPerMonth/{id}', [App\Http\Controllers\EmployeeController::class, 'getEmpDataPerMonth']);
  Route::post('empHoursPermission', [App\Http\Controllers\EmployeeController::class, 'empHoursPermission']);
  Route::post('empHoursPermissionall', [App\Http\Controllers\EmployeeController::class, 'empHoursPermissionAll']);
  Route::post('employees/edit/{id}', [App\Http\Controllers\EmployeeController::class, 'edit']);
  Route::get('employees/absences', [App\Http\Controllers\EmployeeSubtractionController::class, 'employeesAbsences']);
  Route::post('employee/absencestatus', [App\Http\Controllers\EmployeeSubtractionController::class, 'absenceStatus']);
  Route::get('employees/accountstatment', [App\Http\Controllers\EmployeeController::class, 'accountStatment']);
  Route::get('employees/accountstatment/reviewed/{id}', [App\Http\Controllers\EmployeeController::class, 'reviewedStatus']);
  Route::post('employees/excelfingerprintdata', [App\Http\Controllers\EmployeeController::class, 'saveExcelFingerPrintData']);
  Route::post('updatefingerprintsheet', [App\Http\Controllers\EmployeeFingerPrintSheetController::class, 'update']);
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
  Route::apiResource('employeemonthpaid', App\Http\Controllers\EmployeeMonthPaidController::class);

  Route::post('partcollectorder/{id}', [OrdersController::class, 'partCollect_order']); // part
  Route::get('addshippmentnumber/{id}', [OrdersController::class, 'addShippmentNumber']);
 });

 Route::middleware(['department.access:Admin,Shipping Management'])->group(function () {
  Route::post('googlesheet/{sheet}', [App\Http\Controllers\GoogleController::class, 'addData']);

  Route::post('edit-lead', [CorporateSalesLeadController::class, 'edit']);
  Route::apiResource('lead', App\Http\Controllers\CorporateSalesLeadController::class);
  Route::apiResource('lead-source', App\Http\Controllers\CorporateSalesLeadSourceController::class);
  Route::apiResource('lead-tool', App\Http\Controllers\CorporateSalesLeadToolController::class);
  Route::apiResource('lead-industry', App\Http\Controllers\CorporateSalesIndustryController::class);
 });

 Route::middleware(['department.access:Admin,Data Entry'])->group(function () {
  Route::post('orders', [OrdersController::class, 'store']);
  Route::get('phonenumbers', [OrdersController::class, 'phoneNumbers']);
  Route::post('shipping_methods', [ShippingMethodsController::class, 'store']);
 });

 Route::middleware(['department.access:Admin,Operation Management,Account Management,Logistics Specialist,Data Entry'])->group(function () {
  Route::post('companies', [CustomerCompanyController::class, 'store']);
  Route::get('companies', [CustomerCompanyController::class, 'index']);
  Route::get('getOrdersNumbers', [OrdersController::class, 'getOrdersNumbers']);
  Route::apiResource('ShippingLineStatement', App\Http\Controllers\ShippingLineStatementController::class);
 });

 Route::middleware(['department.access:Admin,Operation Management,Account Management,Logistics Specialist'])->group(function () {
  Route::get('companies/search', [CustomerCompanyController::class, 'search']);
  Route::get('companies/{id}', [CustomerCompanyController::class, 'customerCompanyBalance']);
  Route::post('companies/companycollect/{id}', [CustomerCompanyController::class, 'companyCollect']);
 });

 Route::middleware(['department.access:Admin,Operation Management,Shipping Management'])->group(function () {
  Route::post('editorder/{id}', [OrdersController::class, 'edit']);
 });

 Route::middleware(['department.access:Admin,Operation Management,Shipping Management,Customer Service'])->group(function () {
  Route::post('confirm/{id}', [OrdersController::class, 'confirm']);
 });

 Route::middleware(['department.access:Admin,Operation Management,Operation Specialist,Logistics Specialist,Shipping Management'])->group(function () {
  Route::get('refuseorder/{id}', [OrdersController::class, 'refuseOrder']);
  Route::post('order/maintained/{id}', [OrdersController::class, 'maintained']);
 });

 Route::middleware(['department.access:Admin,Operation Management,Operation Specialist,Account Management,Logistics Specialist'])->group(function () {
  Route::get('shippingcompany/search', [ShippingCompanyController::class, 'search']);
  Route::get('shippingcompanies', [ShippingCompanyController::class, 'index']);
  Route::post('shippingcompanies', [ShippingCompanyController::class, 'store']);
  Route::get('shippingcompanies/{shippingcompany}', [ShippingCompanyController::class, 'show']);
  Route::put('shippingcompanies/{shippingcompany}', [ShippingCompanyController::class, 'update']);
  Route::delete('shippingcompanies/{shippingcompany}', [ShippingCompanyController::class, 'destroy']);
  Route::get('shippingcompany/{id}', [ShippingCompanyController::class, 'show']);
 });

 Route::middleware(['department.access:Admin,Operation Management,Operation Specialist,Logistics Specialist,Shipping Management,Data Entry'])->group(function () {
  Route::get('changestatus/{id}', [OrdersController::class, 'change_status']);
 });

 Route::middleware(['department.access:Admin,Data Entry,Shipping Management,Customer Service'])->group(function () {
  Route::get('vip/{id}', [OrdersController::class, 'vip']);
  Route::apiResource('offer', App\Http\Controllers\OffersController::class);
  Route::get('shortage/{id}', [OrdersController::class, 'shortage']);
 });

 Route::middleware(['department.access:Admin,Data Entry,Review Management'])->group(function () {
  Route::post('userrevieworder', [OrdersController::class, 'userReviewOrder']);
  Route::get('tempreview/{id}', [OrdersController::class, 'userTempReviewOrder']);
 });

 Route::middleware(['department.access:Admin,Operation Management,Operation Specialist,Shipping Management,Data Entry,Account Management,Logistics Specialist,Customer Service'])->group(function () {
  Route::get('addnote/{id}', [OrdersController::class, 'addNote']);
 });
});

// http://127.0.0.1:8000/api/transactions/by-customer-order/detailed?itemsPerPage=15&page=1&customer=01018816899
// http://127.0.0.1:8000/api/transactions/by-customer-order/detailed?itemsPerPage=15&page=1&customer=01018816899


Route::get('transactions/by-customer-order/search', [OrdersController::class, 'allUserUnique']);
Route::get('transactions/by-customer-order/detailed', [TransactionController::class, 'index']);
// new
Route::apiResource('tree_accounts', TreeAccountController::class)->names('tree_account');

// Accounting Routes
Route::prefix('accounting/')->middleware('auth')->group(function () {
    // Vouchers
    Route::prefix('vouchers/')->group(function () {
        Route::get('/', [App\Http\Controllers\V2\Accounting\VoucherController::class, 'index']);
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
        Route::get('/{id}', [App\Http\Controllers\V2\Accounting\SafeController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\V2\Accounting\SafeController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\V2\Accounting\SafeController::class, 'destroy']);
    });

    // Daily Entries
    Route::prefix('daily-entries/')->group(function () {
        Route::get('/', [App\Http\Controllers\V2\Accounting\DailyEntryController::class, 'index']);
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
        Route::get('/{id}', [App\Http\Controllers\V2\Accounting\BankController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\V2\Accounting\BankController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\V2\Accounting\BankController::class, 'destroy']);
    });

    // Capitals
    Route::prefix('capitals/')->group(function () {
        Route::get('/', [App\Http\Controllers\V2\Accounting\CapitalController::class, 'index']);
        Route::post('/', [App\Http\Controllers\V2\Accounting\CapitalController::class, 'store']);
    });

    // Reports
    Route::prefix('reports/')->group(function () {
        Route::get('/daily-ledger', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'dailyLedger']);
        Route::get('/account-balance', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'accountBalance']);
        Route::get('/trial-balance', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'trialBalance']);
        Route::get('/accounting-tree', [App\Http\Controllers\V2\Accounting\AccountingReportController::class, 'accountingTree']);
    });
    // Service Accounts
    Route::prefix('service-accounts/')->group(function () {
        Route::get('/', [App\Http\Controllers\ServiceAccountsController::class, 'index']);
        Route::post('/', [App\Http\Controllers\ServiceAccountsController::class, 'store']);
        Route::post('/transfer', [App\Http\Controllers\ServiceAccountsController::class, 'transfer']);
        Route::put('/{id}', [App\Http\Controllers\ServiceAccountsController::class, 'update']);
        // Route::delete('/{id}', [App\Http\Controllers\ServiceAccountsController::class, 'destroy']);
    });
});

Route::prefix('report/')->group(function () {
 Route::get('order', [ReportOrderController::class, 'AllOrder']);
 Route::get('getByOrderId', [ReportOrderController::class, 'getByOrderId']);
});


Route::get('ahmed', function () {
 Log::info("ddd", ["ddd"]);
});
