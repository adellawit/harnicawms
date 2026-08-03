<?php

use App\Http\Controllers\Admin\AiConfigurationController;
use App\Http\Controllers\Admin\AttributeDefinitionController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BusinessUnitController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerGroupController;
use App\Http\Controllers\Admin\DashboardConfigurationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DesignSystemController;
use App\Http\Controllers\Admin\DivisionController;
use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Admin\ImportDataController;
use App\Http\Controllers\Admin\MembershipConfigurationController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MethodPaymentController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ParameterController;
use App\Http\Controllers\Admin\ParameterDetailController;
use App\Http\Controllers\Admin\Partner\AgentController as PartnerAgentController;
use App\Http\Controllers\Admin\Partner\CuttingPriceConfigController;
use App\Http\Controllers\Admin\Partner\PartnerApplicationController;
use App\Http\Controllers\Admin\Partner\PartnerNetworkMapController;
use App\Http\Controllers\Admin\Partner\PartnerReportController;
use App\Http\Controllers\Admin\Partner\ResellerController as PartnerResellerController;
use App\Http\Controllers\Admin\Partner\ResellerMappingController as PartnerResellerMappingController;
use App\Http\Controllers\Admin\PaymentGatewayConfigurationController;
use App\Http\Controllers\Admin\POSController;
use App\Http\Controllers\Admin\PositionController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\ProductCollectionController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductNatureController;
use App\Http\Controllers\Admin\ProductPriceController;
use App\Http\Controllers\Admin\ProductPriceListController;
use App\Http\Controllers\Admin\ProductStockController;
use App\Http\Controllers\Admin\ProductTagController;
use App\Http\Controllers\Admin\ProductUnitController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\PurchaseInvoiceController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\ReportAdvancedController;
use App\Http\Controllers\Admin\ReportBarcodeTrackingController;
use App\Http\Controllers\Admin\ReportAgentCuttingPriceController;
use App\Http\Controllers\Admin\ReportFgBarcodeStockController;
use App\Http\Controllers\Admin\ReportPurchaseOrderController;
use App\Http\Controllers\Admin\ReportStockCardController;
use App\Http\Controllers\Admin\ReportStockHistoryController;
use App\Http\Controllers\Admin\ReportSummarySalesController;
use App\Http\Controllers\Admin\ReportTransactionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\StockOpnameController;
use App\Http\Controllers\Admin\ShippingRateController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\ThemeConfigurationController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Ai\ChatController;
use App\Http\Controllers\Ai\ConversationController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\Admin\Finance\AccountMappingController;
use App\Http\Controllers\Admin\Finance\BeginningBalanceController;
use App\Http\Controllers\Admin\Finance\CashBankController;
use App\Http\Controllers\Admin\Finance\CashFlowCategoryController;
use App\Http\Controllers\Admin\Finance\FinancialReportController;
use App\Http\Controllers\Admin\Finance\ChartOfAccountController;
use App\Http\Controllers\Admin\Finance\FiscalCalendarController;
use App\Http\Controllers\Admin\Finance\JournalEntryController;
use App\Http\Controllers\Admin\Finance\JurnalUmumController;
use App\Http\Controllers\Telegram\WebhookController as TelegramWebhookController;
use App\Http\Controllers\Xendit\WebhookController as XenditWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::post('/webhooks/xendit', [XenditWebhookController::class, 'handle'])
    ->name('webhooks.xendit');

Route::post('/webhooks/telegram', [TelegramWebhookController::class, 'handle'])
    ->name('webhooks.telegram');

/*
|--------------------------------------------------------------------------
| Public Partner Registration (NO auth)
|--------------------------------------------------------------------------
| Must stay outside the auth/verified group below. Guests need this URL.
| After deploy, verify: php artisan route:list --path=partner/register -v
| Middleware must be only "web" (not auth). Then: php artisan route:clear
*/
Route::prefix('partner')->name('partner.')->group(function () {
    Route::get('/register', [PartnerApplicationController::class, 'publicCreate'])->name('register');
    Route::post('/register', [PartnerApplicationController::class, 'publicStore'])->name('register.store');
    Route::get('/register/thank-you/{number}', [PartnerApplicationController::class, 'thankYou'])->name('register.thank-you');
    Route::get('/register/form-agen', [PartnerApplicationController::class, 'downloadAgentForm'])->name('register.form-agent');
    Route::get('/register/form-reseller', [PartnerApplicationController::class, 'downloadResellerForm'])->name('register.form-reseller');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // / DASHBOARD CONTROLLER
    Route::get('/dashboard', [DashboardController::class, 'indexView'])->name('dashboard');
    Route::post('/dashboard/task-list', [DashboardController::class, 'getTaskListData'])->name('dashboard.task.list');
    Route::post('/dashboard/client-list', [DashboardController::class, 'getClientListData'])->name('dashboard.client.list');
    Route::post('/dashboard/subscription-list', [DashboardController::class, 'getSubscriptionListData'])->name('dashboard.subscription.list');
    Route::redirect('/warehouse', '/business/warehouse');

    // ************************
    // DESIGN SYSTEM
    // ************************
    Route::get('/design-system', [DesignSystemController::class, 'indexView'])->name('design-system.index.view');

    // / AI CHAT (WMS AI Assistant widget API)
    Route::group([
        'prefix' => 'agent',
        'middleware' => ['permission:'.(config('agent.permission_menu') ?: 'AI Assistant').',is_read', 'throttle:'.(int) config('agent.rate_limit_per_minute', 30).',1'],
    ], function () {
        Route::post('/chat', [ChatController::class, 'chat'])->name('agent.chat');
        Route::get('/conversations', [ConversationController::class, 'index'])->name('agent.conversations');
        Route::post('/conversations/new', [ConversationController::class, 'store'])->name('agent.conversations.new');
        Route::get('/conversations/{conversationId}/messages', [ConversationController::class, 'messages'])->name('agent.conversations.messages');
        Route::delete('/conversations/{conversationId}', [ConversationController::class, 'destroy'])->name('agent.conversations.destroy');
    });

    /********************
     ** ACCOUNT ROUTES **
     *******************/
    Route::group(['prefix' => 'account'], function () {
        // / PROFILE CONTROLLER
        Route::get('/', [ProfileController::class, 'updateProfileView'])->name('profile.view');
        Route::post('/', [ProfileController::class, 'updateProfile'])->name('profile.update');
        Route::get('/change-password', [ProfileController::class, 'changePasswordView'])->name('profile.change-password-view');
        Route::post('/change-password', [ProfileController::class, 'changePasswordProfile'])->name('profile.change-password');
        Route::get('/change-branch', [ProfileController::class, 'switchBranchView'])->name('profile.change-branch-view');
        Route::post('/switch-branch', [ProfileController::class, 'switchBranch'])->name('profile.switch-branch');
    });

    Route::group(['prefix' => 'partner-network'], function () {
        Route::get('/', [PartnerReportController::class, 'index'])->name('partner.reports.index')->middleware('permission:Network,is_read');
        Route::get('/map', [PartnerNetworkMapController::class, 'index'])->name('partner.network-map.index')->middleware('permission:Network,is_read');
        Route::get('/applications', [PartnerApplicationController::class, 'index'])->name('partner.applications.index')->middleware('permission:Partner Application,is_read');
        Route::get('/applications/create', [PartnerApplicationController::class, 'create'])->name('partner.applications.create')->middleware('permission:Partner Application,is_create');
        Route::post('/applications', [PartnerApplicationController::class, 'store'])->name('partner.applications.store')->middleware('permission:Partner Application,is_create');
        Route::get('/applications/{application}/edit', [PartnerApplicationController::class, 'edit'])->name('partner.applications.edit')->middleware('permission:Partner Application,is_update');
        Route::put('/applications/{application}', [PartnerApplicationController::class, 'update'])->name('partner.applications.update')->middleware('permission:Partner Application,is_update');
        Route::get('/applications/{id}', [PartnerApplicationController::class, 'show'])->name('partner.applications.show')->middleware('permission:Partner Application,is_read');
        Route::get('/applications/{id}/documents/{documentId}', [PartnerApplicationController::class, 'downloadDocument'])->name('partner.applications.documents.download')->middleware('permission:Partner Application,is_read');
        Route::post('/applications/{id}/followup', [PartnerApplicationController::class, 'followup'])->name('partner.applications.followup')->middleware('permission:Partner Application,is_update');
        Route::post('/applications/{id}/assign-agent', [PartnerApplicationController::class, 'assignAgent'])->name('partner.applications.assign-agent')->middleware('permission:Partner Application,is_update');
        Route::post('/applications/{id}/convert-agent', [PartnerApplicationController::class, 'convertAgent'])->name('partner.applications.convert-agent')->middleware('permission:Partner Application,is_update');
        Route::post('/applications/{id}/convert-reseller', [PartnerApplicationController::class, 'convertReseller'])->name('partner.applications.convert-reseller')->middleware('permission:Partner Application,is_update');
        Route::get('/agents', [PartnerAgentController::class, 'index'])->name('partner.agents.index')->middleware('permission:Partner Agent,is_read');
        Route::get('/agents/{id}', [PartnerAgentController::class, 'show'])->name('partner.agents.show')->middleware('permission:Partner Agent,is_read');
        Route::get('/resellers', [PartnerResellerController::class, 'index'])->name('partner.resellers.index')->middleware('permission:Partner Reseller,is_read');
        Route::get('/resellers/mapping', [PartnerResellerMappingController::class, 'index'])->name('partner.resellers.mapping.index')->middleware('permission:Partner Reseller Mapping,is_read');
        Route::post('/resellers/mapping', [PartnerResellerMappingController::class, 'update'])->name('partner.resellers.mapping.store')->middleware('permission:Partner Reseller Mapping,is_update');
        Route::get('/resellers/{id}', [PartnerResellerController::class, 'show'])->name('partner.resellers.show')->middleware('permission:Partner Reseller,is_read');
        Route::put('/resellers/{id}/mapping', [PartnerResellerController::class, 'updateMapping'])->name('partner.resellers.mapping.update')->middleware('permission:Partner Reseller,is_update');

        Route::group(['prefix' => 'cutting-price-config'], function () {
            Route::get('/', [CuttingPriceConfigController::class, 'indexView'])
                ->name('partner.cutting-price-config.index.view')
                ->middleware('permission:Cutting Price Config,is_read');
            Route::post('/data', [CuttingPriceConfigController::class, 'indexData'])
                ->name('partner.cutting-price-config.index.data');
            Route::get('/insert', [CuttingPriceConfigController::class, 'insertView'])
                ->name('partner.cutting-price-config.insert.view')
                ->middleware('permission:Cutting Price Config,is_create');
            Route::post('/insert/data', [CuttingPriceConfigController::class, 'insertData'])
                ->name('partner.cutting-price-config.insert.data')
                ->middleware('permission:Cutting Price Config,is_create');
            Route::get('/edit/{id}', [CuttingPriceConfigController::class, 'editView'])
                ->name('partner.cutting-price-config.edit.view')
                ->middleware('permission:Cutting Price Config,is_update');
            Route::post('/edit/data', [CuttingPriceConfigController::class, 'editData'])
                ->name('partner.cutting-price-config.edit.data')
                ->middleware('permission:Cutting Price Config,is_update');
            Route::post('/delete', [CuttingPriceConfigController::class, 'deleteData'])
                ->name('partner.cutting-price-config.delete.data')
                ->middleware('permission:Cutting Price Config,is_delete');
            Route::post('/restore', [CuttingPriceConfigController::class, 'restoreData'])
                ->name('partner.cutting-price-config.restore.data')
                ->middleware('permission:Cutting Price Config,is_delete');
        });
    });

    /*****************
     ** HUMAN RESOURCES ROUTES **
     *****************/
    Route::group(['prefix' => 'human-resources'], function () {
        Route::get('/master', fn () => redirect()->route('division.index.view'));
        Route::group(['prefix' => 'employee'], function () {
            // / USER CONTROLLER
            Route::get('/', [UserController::class, 'indexView'])->name('users.index.view')->middleware('permission:Employee,is_read');
            Route::post('/data', [UserController::class, 'indexData'])->name('users.index.data');
            Route::get('/insert', [UserController::class, 'insertView'])->name('users.insert.view')->middleware('permission:Employee,is_create');
            Route::post('/insert/data', [UserController::class, 'insertData'])->name('users.insert.data')->middleware('permission:Employee,is_create');
            Route::get('/edit/{id}', [UserController::class, 'editView'])->name('users.edit.view')->middleware('permission:Employee,is_update');
            Route::post('/edit/data', [UserController::class, 'editData'])->name('users.edit.data')->middleware('permission:Employee,is_update');
            Route::get('/detail/{id}', [UserController::class, 'detailView'])->name('users.detail.view')->middleware('permission:Employee,is_read');
            Route::post('/delete', [UserController::class, 'deleteData'])->name('users.delete.data')->middleware('permission:Employee,is_delete');
            Route::post('/restore', [UserController::class, 'restoreData'])->name('users.restore.data')->middleware('permission:Employee,is_delete');
            Route::post('/import/data', [UserController::class, 'importData'])->name('users.import.data')->middleware('permission:Employee,is_create');
            Route::get('/users/download-template', [UserController::class, 'downloadTemplate'])->name('users.export.data')->middleware('permission:Employee,is_read');
            Route::post('/login-as', [UserController::class, 'loginAs'])->name('users.login-as');
        });
    });

    Route::post('/stop-impersonation', [UserController::class, 'stopImpersonation'])->name('users.stop-impersonation');

    /*****************
     ** POSITION ROUTES **
     *****************/
    Route::group(['prefix' => 'human-resources'], function () {
        Route::group(['prefix' => 'position'], function () {
            Route::get('/', [PositionController::class, 'indexView'])->name('position.index.view')->middleware('permission:Position,is_read');
            Route::post('/data', [PositionController::class, 'indexData'])->name('position.index.data');
            Route::get('/insert', [PositionController::class, 'insertView'])->name('position.insert.view')->middleware('permission:Position,is_create');
            Route::post('/insert/data', [PositionController::class, 'insertData'])->name('position.insert.data')->middleware('permission:Position,is_create');
            Route::get('/edit/{id}', [PositionController::class, 'editView'])->name('position.edit.view')->middleware('permission:Position,is_update');
            Route::post('/edit/data', [PositionController::class, 'editData'])->name('position.edit.data')->middleware('permission:Position,is_update');
            Route::post('/delete', [PositionController::class, 'deleteData'])->name('position.delete.data')->middleware('permission:Position,is_delete');
            Route::post('/restore', [PositionController::class, 'restoreData'])->name('position.restore.data')->middleware('permission:Position,is_delete');
        });
    });

    /*****************
     ** DIVISION ROUTES **
     *****************/
    Route::group(['prefix' => 'human-resources'], function () {
        Route::group(['prefix' => 'division'], function () {
            Route::get('/', [DivisionController::class, 'indexView'])->name('division.index.view')->middleware('permission:Division,is_read');
            Route::post('/data', [DivisionController::class, 'indexData'])->name('division.index.data');
            Route::get('/insert', [DivisionController::class, 'insertView'])->name('division.insert.view')->middleware('permission:Division,is_create');
            Route::post('/insert/data', [DivisionController::class, 'insertData'])->name('division.insert.data')->middleware('permission:Division,is_create');
            Route::get('/edit/{id}', [DivisionController::class, 'editView'])->name('division.edit.view')->middleware('permission:Division,is_update');
            Route::post('/edit/data', [DivisionController::class, 'editData'])->name('division.edit.data')->middleware('permission:Division,is_update');
            Route::post('/delete', [DivisionController::class, 'deleteData'])->name('division.delete.data')->middleware('permission:Division,is_delete');
            Route::post('/restore', [DivisionController::class, 'restoreData'])->name('division.restore.data')->middleware('permission:Division,is_delete');
        });
    });

    /*****************
     ** OPERATIONAL ROUTES **
     *****************/
    /*****************
     ** HOLDING ROUTES **
     *****************/
    Route::group(['prefix' => 'business'], function () {
        Route::group(['prefix' => 'holding'], function () {
            Route::get('/', [BusinessUnitController::class, 'indexView'])->name('holding.index.view')->middleware('permission:Holding,is_read');
            Route::post('/data', [BusinessUnitController::class, 'indexData'])->name('holding.index.data');
            Route::get('/insert', [BusinessUnitController::class, 'insertView'])->name('holding.insert.view')->middleware('permission:Holding,is_create');
            Route::post('/insert/data', [BusinessUnitController::class, 'insertData'])->name('holding.insert.data')->middleware('permission:Holding,is_create');
            Route::get('/edit/{id}', [BusinessUnitController::class, 'editView'])->name('holding.edit.view')->middleware('permission:Holding,is_update');
            Route::post('/edit/data', [BusinessUnitController::class, 'editData'])->name('holding.edit.data')->middleware('permission:Holding,is_update');
            Route::post('/delete', [BusinessUnitController::class, 'deleteData'])->name('holding.delete.data')->middleware('permission:Holding,is_delete');
            Route::post('/restore', [BusinessUnitController::class, 'restoreData'])->name('holding.restore.data')->middleware('permission:Holding,is_delete');
        });
    });

    /*****************
     ** COMPANY ROUTES **
     *****************/
    Route::group(['prefix' => 'business'], function () {
        Route::group(['prefix' => 'company'], function () {
            Route::get('/', [CompanyController::class, 'indexView'])->name('company.index.view')->middleware('permission:Company,is_read');
            Route::post('/data', [CompanyController::class, 'indexData'])->name('company.index.data');
            Route::get('/insert', [CompanyController::class, 'insertView'])->name('company.insert.view')->middleware('permission:Company,is_create');
            Route::post('/insert/data', [CompanyController::class, 'insertData'])->name('company.insert.data')->middleware('permission:Company,is_create');
            Route::get('/edit/{id}', [CompanyController::class, 'editView'])->name('company.edit.view')->middleware('permission:Company,is_update');
            Route::post('/edit/data', [CompanyController::class, 'editData'])->name('company.edit.data')->middleware('permission:Company,is_update');
            Route::post('/delete', [CompanyController::class, 'deleteData'])->name('company.delete.data')->middleware('permission:Company,is_delete');
            Route::post('/restore', [CompanyController::class, 'restoreData'])->name('company.restore.data')->middleware('permission:Company,is_delete');
        });
    });

    /*****************
     ** BRANCH ROUTES **
     *****************/
    Route::group(['prefix' => 'business'], function () {
        Route::group(['prefix' => 'branch'], function () {
            Route::get('/', [BranchController::class, 'indexView'])->name('branch.index.view')->middleware('permission:Branch,is_read');
            Route::post('/data', [BranchController::class, 'indexData'])->name('branch.index.data');
            Route::get('/insert', [BranchController::class, 'insertView'])->name('branch.insert.view')->middleware('permission:Branch,is_create');
            Route::post('/insert/data', [BranchController::class, 'insertData'])->name('branch.insert.data')->middleware('permission:Branch,is_create');
            Route::get('/edit/{id}', [BranchController::class, 'editView'])->name('branch.edit.view')->middleware('permission:Branch,is_update');
            Route::post('/edit/data', [BranchController::class, 'editData'])->name('branch.edit.data')->middleware('permission:Branch,is_update');
            Route::post('/delete', [BranchController::class, 'deleteData'])->name('branch.delete.data')->middleware('permission:Branch,is_delete');
            Route::post('/restore', [BranchController::class, 'restoreData'])->name('branch.restore.data')->middleware('permission:Branch,is_delete');
        });

        Route::group(['prefix' => 'warehouse'], function () {
            Route::get('/', [WarehouseController::class, 'indexView'])->name('warehouse.index.view')->middleware('permission:Warehouse,is_read');
            Route::post('/data', [WarehouseController::class, 'indexData'])->name('warehouse.index.data');
            Route::get('/insert', [WarehouseController::class, 'insertView'])->name('warehouse.insert.view')->middleware('permission:Warehouse,is_create');
            Route::post('/insert/data', [WarehouseController::class, 'insertData'])->name('warehouse.insert.data')->middleware('permission:Warehouse,is_create');
            Route::get('/generate-code', [WarehouseController::class, 'generateCodeApi'])->name('warehouse.generate.code')->middleware('permission:Warehouse,is_create');
            Route::get('/edit/{id}', [WarehouseController::class, 'editView'])->name('warehouse.edit.view')->middleware('permission:Warehouse,is_update');
            Route::post('/edit/data', [WarehouseController::class, 'editData'])->name('warehouse.edit.data')->middleware('permission:Warehouse,is_update');
            Route::post('/delete', [WarehouseController::class, 'deleteData'])->name('warehouse.delete.data')->middleware('permission:Warehouse,is_delete');
            Route::post('/restore', [WarehouseController::class, 'restoreData'])->name('warehouse.restore.data')->middleware('permission:Warehouse,is_delete');
        });
    });

    /*****************
    ** ROLE ROUTES **
    *****************/
    Route::group(['prefix' => 'masterdatas'], function () {
        // Other master data routes...
    });

    /*****************
     ** ACCESS MANAGEMENT ROUTES ** (views/admin/access-management)
     *****************/
    Route::group(['prefix' => 'access-management'], function () {
        Route::group(['prefix' => 'roles'], function () {
            Route::get('/', [RoleController::class, 'indexView'])->name('roles.index.view')->middleware('permission:Role,is_read');
            Route::post('/data', [RoleController::class, 'indexData'])->name('roles.index.data');
            Route::get('/insert', [RoleController::class, 'insertView'])->name('roles.insert.view')->middleware('permission:Role,is_create');
            Route::post('/insert/data', [RoleController::class, 'insertData'])->name('roles.insert.data')->middleware('permission:Role,is_create');
            Route::get('/edit/{id}', [RoleController::class, 'editView'])->name('roles.edit.view')->middleware('permission:Role,is_update');
            Route::post('/edit/data', [RoleController::class, 'editData'])->name('roles.edit.data')->middleware('permission:Role,is_update');
            Route::post('/delete', [RoleController::class, 'deleteData'])->name('roles.delete.data')->middleware('permission:Role,is_delete');
            Route::post('/restore', [RoleController::class, 'restoreData'])->name('roles.restore.data')->middleware('permission:Role,is_delete');
        });

        Route::group(['prefix' => 'dashboard-configuration'], function () {
            Route::get('/', [DashboardConfigurationController::class, 'indexView'])->name('dashboard-configuration.index');
            Route::get('/{roleId}', [DashboardConfigurationController::class, 'editView'])->name('dashboard-configuration.edit');
            Route::post('/update', [DashboardConfigurationController::class, 'update'])->name('dashboard-configuration.update');
        });

        Route::group(['prefix' => 'menu'], function () {
            Route::get('/', [MenuController::class, 'indexView'])->name('menu.index.view')->middleware('permission:Menu,is_read');
            Route::post('/data', [MenuController::class, 'indexData'])->name('menu.index.data');
            Route::post('/reorder', [MenuController::class, 'reorder'])->name('menu.reorder')->middleware('permission:Menu,is_update');
            Route::get('/insert', [MenuController::class, 'insertView'])->name('menu.insert.view')->middleware('permission:Menu,is_create');
            Route::post('/insert/data', [MenuController::class, 'insertData'])->name('menu.insert.data')->middleware('permission:Menu,is_create');
            Route::get('/edit/{id}', [MenuController::class, 'editView'])->name('menu.edit.view')->middleware('permission:Menu,is_update');
            Route::post('/edit/data', [MenuController::class, 'editData'])->name('menu.edit.data')->middleware('permission:Menu,is_update');
            Route::post('/delete', [MenuController::class, 'deleteData'])->name('menu.delete.data')->middleware('permission:Menu,is_delete');
            Route::post('/restore', [MenuController::class, 'restoreData'])->name('menu.restore.data')->middleware('permission:Menu,is_delete');
        });

        Route::group(['prefix' => 'notifications'], function () {
            Route::get('/list', [NotificationController::class, 'listView'])->name('notifications.list.view');
            Route::get('/config', [NotificationController::class, 'indexView'])->name('notifications.index.view');
            Route::post('/update', [NotificationController::class, 'updateConfig'])->name('notifications.update.config');

            Route::get('/api/list', [NotificationController::class, 'getNotifications'])->name('notifications.api.list');
            Route::get('/api/list-data', [NotificationController::class, 'listData'])->name('notifications.api.list-data');
            Route::get('/api/unread-count', [NotificationController::class, 'getUnreadCount'])->name('notifications.api.unread-count');
            Route::post('/api/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.api.mark-read');
            Route::post('/api/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.api.mark-all-read');
        });
    });

    /*****************
     ** MASTER DATA ROUTES ** (views/admin/master-data)
     *****************/
    Route::group(['prefix' => 'master-data'], function () {
        Route::group(['prefix' => 'parameter'], function () {
            Route::get('/', [ParameterController::class, 'indexView'])->name('parameter.index.view')->middleware('permission:Parameter,is_read');
            Route::post('/data', [ParameterController::class, 'indexData'])->name('parameter.index.data');
            Route::get('/insert', [ParameterController::class, 'insertView'])->name('parameter.insert.view')->middleware('permission:Parameter,is_create');
            Route::post('/insert/data', [ParameterController::class, 'insertData'])->name('parameter.insert.data')->middleware('permission:Parameter,is_create');
            Route::get('/edit/{id}', [ParameterController::class, 'editView'])->name('parameter.edit.view')->middleware('permission:Parameter,is_update');
            Route::post('/edit/data', [ParameterController::class, 'editData'])->name('parameter.edit.data')->middleware('permission:Parameter,is_update');
            Route::post('/delete', [ParameterController::class, 'deleteData'])->name('parameter.delete.data')->middleware('permission:Parameter,is_delete');
            Route::post('/restore', [ParameterController::class, 'restoreData'])->name('parameter.restore.data')->middleware('permission:Parameter,is_delete');

            Route::get('/{parameterId}/details', [ParameterDetailController::class, 'indexView'])->name('parameter.details.index.view')->middleware('permission:Parameter,is_read');
            Route::post('/{parameterId}/details/data', [ParameterDetailController::class, 'indexData'])->name('parameter.details.index.data');
            Route::get('/{parameterId}/details/insert', [ParameterDetailController::class, 'insertView'])->name('parameter.details.insert.view')->middleware('permission:Parameter,is_create');
            Route::post('/{parameterId}/details/insert/data', [ParameterDetailController::class, 'insertData'])->name('parameter.details.insert.data')->middleware('permission:Parameter,is_create');
            Route::get('/{parameterId}/details/edit/{id}', [ParameterDetailController::class, 'editView'])->name('parameter.details.edit.view')->middleware('permission:Parameter,is_update');
            Route::post('/{parameterId}/details/edit/data', [ParameterDetailController::class, 'editData'])->name('parameter.details.edit.data')->middleware('permission:Parameter,is_update');
            Route::post('/{parameterId}/details/delete', [ParameterDetailController::class, 'deleteData'])->name('parameter.details.delete.data')->middleware('permission:Parameter,is_delete');
            Route::post('/{parameterId}/details/restore', [ParameterDetailController::class, 'restoreData'])->name('parameter.details.restore.data')->middleware('permission:Parameter,is_delete');
        });

        Route::group(['prefix' => 'shipping-rate'], function () {
            Route::get('/', [ShippingRateController::class, 'indexView'])->name('shipping-rate.index.view')->middleware('permission:Master Ongkir,is_read');
            Route::post('/data', [ShippingRateController::class, 'indexData'])->name('shipping-rate.index.data');
            Route::get('/insert', [ShippingRateController::class, 'insertView'])->name('shipping-rate.insert.view')->middleware('permission:Master Ongkir,is_create');
            Route::post('/insert/data', [ShippingRateController::class, 'insertData'])->name('shipping-rate.insert.data')->middleware('permission:Master Ongkir,is_create');
            Route::get('/edit/{id}', [ShippingRateController::class, 'editView'])->name('shipping-rate.edit.view')->middleware('permission:Master Ongkir,is_update');
            Route::post('/edit/data', [ShippingRateController::class, 'editData'])->name('shipping-rate.edit.data')->middleware('permission:Master Ongkir,is_update');
            Route::post('/delete', [ShippingRateController::class, 'deleteData'])->name('shipping-rate.delete.data')->middleware('permission:Master Ongkir,is_delete');
            Route::post('/restore', [ShippingRateController::class, 'restoreData'])->name('shipping-rate.restore.data')->middleware('permission:Master Ongkir,is_delete');
            Route::get('/import/template', [ShippingRateController::class, 'importTemplate'])->name('shipping-rate.import.template')->middleware('permission:Master Ongkir,is_create');
            Route::post('/import/data', [ShippingRateController::class, 'importData'])->name('shipping-rate.import.data')->middleware('permission:Master Ongkir,is_create');
        });

        Route::group(['prefix' => 'supplier'], function () {
            Route::get('/', [SupplierController::class, 'indexView'])->name('product.supplier.index.view')->middleware('permission:Supplier,is_read');
            Route::post('/data', [SupplierController::class, 'indexData'])->name('product.supplier.index.data');
            Route::get('/insert', [SupplierController::class, 'insertView'])->name('product.supplier.insert.view')->middleware('permission:Supplier,is_create');
            Route::post('/insert/data', [SupplierController::class, 'insertData'])->name('product.supplier.insert.data')->middleware('permission:Supplier,is_create');
            Route::get('/edit/{id}', [SupplierController::class, 'editView'])->name('product.supplier.edit.view')->middleware('permission:Supplier,is_update');
            Route::post('/edit/data', [SupplierController::class, 'editData'])->name('product.supplier.edit.data')->middleware('permission:Supplier,is_update');
            Route::post('/delete', [SupplierController::class, 'deleteData'])->name('product.supplier.delete.data')->middleware('permission:Supplier,is_delete');
            Route::post('/restore', [SupplierController::class, 'restoreData'])->name('product.supplier.restore.data')->middleware('permission:Supplier,is_delete');
        });
    });

    /*****************
     ** FINANCE ROUTES **
     *****************/
    Route::group(['prefix' => 'finance'], function () {
        Route::group(['prefix' => 'chart-of-accounts'], function () {
            Route::get('/', [ChartOfAccountController::class, 'indexView'])->name('finance.chart-of-accounts.index.view')->middleware('permission:Chart of Accounts,is_read');
            Route::post('/data', [ChartOfAccountController::class, 'indexData'])->name('finance.chart-of-accounts.index.data');
            Route::get('/parents', [ChartOfAccountController::class, 'parentsData'])->name('finance.chart-of-accounts.parents')->middleware('permission:Chart of Accounts,is_read');
            Route::get('/suggest-code', [ChartOfAccountController::class, 'suggestCode'])->name('finance.chart-of-accounts.suggest-code')->middleware('permission:Chart of Accounts,is_read');
            Route::get('/insert', [ChartOfAccountController::class, 'insertView'])->name('finance.chart-of-accounts.insert.view')->middleware('permission:Chart of Accounts,is_create');
            Route::post('/insert/data', [ChartOfAccountController::class, 'insertData'])->name('finance.chart-of-accounts.insert.data')->middleware('permission:Chart of Accounts,is_create');
            Route::get('/edit/{id}', [ChartOfAccountController::class, 'editView'])->name('finance.chart-of-accounts.edit.view')->middleware('permission:Chart of Accounts,is_update');
            Route::post('/edit/data', [ChartOfAccountController::class, 'editData'])->name('finance.chart-of-accounts.edit.data')->middleware('permission:Chart of Accounts,is_update');
            Route::post('/delete', [ChartOfAccountController::class, 'deleteData'])->name('finance.chart-of-accounts.delete.data')->middleware('permission:Chart of Accounts,is_delete');
            Route::post('/restore', [ChartOfAccountController::class, 'restoreData'])->name('finance.chart-of-accounts.restore.data')->middleware('permission:Chart of Accounts,is_delete');
            Route::post('/seed', [ChartOfAccountController::class, 'seedTemplate'])->name('finance.chart-of-accounts.seed')->middleware('permission:Chart of Accounts,is_create');
        });

        Route::group(['prefix' => 'account-mapping'], function () {
            Route::get('/', [AccountMappingController::class, 'indexView'])->name('finance.account-mapping.index.view')->middleware('permission:Account Mapping,is_read');
            Route::post('/save', [AccountMappingController::class, 'saveData'])->name('finance.account-mapping.save')->middleware('permission:Account Mapping,is_update');
        });

        Route::group(['prefix' => 'cash-flow-category'], function () {
            Route::get('/', [CashFlowCategoryController::class, 'indexView'])->name('finance.cash-flow-category.index.view')->middleware('permission:Cash Flow Category,is_read');
            Route::get('/insert', [CashFlowCategoryController::class, 'insertView'])->name('finance.cash-flow-category.insert.view')->middleware('permission:Cash Flow Category,is_create');
            Route::post('/insert/data', [CashFlowCategoryController::class, 'insertData'])->name('finance.cash-flow-category.insert.data')->middleware('permission:Cash Flow Category,is_create');
            Route::get('/edit/{id}', [CashFlowCategoryController::class, 'editView'])->name('finance.cash-flow-category.edit.view')->middleware('permission:Cash Flow Category,is_update');
            Route::post('/edit/data', [CashFlowCategoryController::class, 'editData'])->name('finance.cash-flow-category.edit.data')->middleware('permission:Cash Flow Category,is_update');
            Route::post('/delete', [CashFlowCategoryController::class, 'deleteData'])->name('finance.cash-flow-category.delete.data')->middleware('permission:Cash Flow Category,is_delete');
            Route::post('/restore', [CashFlowCategoryController::class, 'restoreData'])->name('finance.cash-flow-category.restore.data')->middleware('permission:Cash Flow Category,is_delete');
        });

        Route::group(['prefix' => 'fiscal-calendar'], function () {
            Route::get('/', [FiscalCalendarController::class, 'indexView'])->name('finance.fiscal-calendar.index.view')->middleware('permission:Fiscal Calendar,is_read');
            Route::get('/insert', [FiscalCalendarController::class, 'insertView'])->name('finance.fiscal-calendar.insert.view')->middleware('permission:Fiscal Calendar,is_create');
            Route::post('/insert/data', [FiscalCalendarController::class, 'insertData'])->name('finance.fiscal-calendar.insert.data')->middleware('permission:Fiscal Calendar,is_create');
            Route::get('/edit/{id}', [FiscalCalendarController::class, 'editView'])->name('finance.fiscal-calendar.edit.view')->middleware('permission:Fiscal Calendar,is_update');
            Route::post('/edit/data', [FiscalCalendarController::class, 'editData'])->name('finance.fiscal-calendar.edit.data')->middleware('permission:Fiscal Calendar,is_update');
            Route::post('/delete', [FiscalCalendarController::class, 'deleteData'])->name('finance.fiscal-calendar.delete.data')->middleware('permission:Fiscal Calendar,is_delete');
            Route::post('/restore', [FiscalCalendarController::class, 'restoreData'])->name('finance.fiscal-calendar.restore.data')->middleware('permission:Fiscal Calendar,is_delete');
            Route::post('/period/status', [FiscalCalendarController::class, 'setPeriodStatus'])->name('finance.fiscal-calendar.period.status')->middleware('permission:Fiscal Calendar,is_update');
            Route::post('/close', [FiscalCalendarController::class, 'closeCalendar'])->name('finance.fiscal-calendar.close')->middleware('permission:Fiscal Calendar,is_update');
            Route::post('/reopen', [FiscalCalendarController::class, 'reopenCalendar'])->name('finance.fiscal-calendar.reopen')->middleware('permission:Fiscal Calendar,is_update');
            Route::get('/{id}', [FiscalCalendarController::class, 'showView'])->name('finance.fiscal-calendar.show.view')->middleware('permission:Fiscal Calendar,is_read');
        });

        Route::group(['prefix' => 'beginning-balance'], function () {
            Route::get('/', [BeginningBalanceController::class, 'indexView'])->name('finance.beginning-balance.index.view')->middleware('permission:Beginning Balance,is_read');
            Route::get('/edit/{fiscalCalendarId}', [BeginningBalanceController::class, 'editView'])->name('finance.beginning-balance.edit.view')->middleware('permission:Beginning Balance,is_read');
            Route::post('/save', [BeginningBalanceController::class, 'saveData'])->name('finance.beginning-balance.save')->middleware('permission:Beginning Balance,is_update');
            Route::post('/suggest', [BeginningBalanceController::class, 'suggestData'])->name('finance.beginning-balance.suggest')->middleware('permission:Beginning Balance,is_update');
            Route::post('/book', [BeginningBalanceController::class, 'bookData'])->name('finance.beginning-balance.book')->middleware('permission:Beginning Balance,is_update');
            Route::post('/unpost', [BeginningBalanceController::class, 'unpostData'])->name('finance.beginning-balance.unpost')->middleware('permission:Beginning Balance,is_update');
        });

        Route::group(['prefix' => 'journal-entry'], function () {
            Route::get('/', [JournalEntryController::class, 'indexView'])->name('finance.journal-entry.index.view')->middleware('permission:Journal Entry,is_read');
            Route::get('/insert', [JournalEntryController::class, 'insertView'])->name('finance.journal-entry.insert.view')->middleware('permission:Journal Entry,is_create');
            Route::get('/edit/{id}', [JournalEntryController::class, 'editView'])->name('finance.journal-entry.edit.view')->middleware('permission:Journal Entry,is_read');
            Route::post('/save', [JournalEntryController::class, 'saveData'])->name('finance.journal-entry.save')->middleware('permission:Journal Entry,is_read');
            Route::post('/post', [JournalEntryController::class, 'postData'])->name('finance.journal-entry.post')->middleware('permission:Journal Entry,is_update');
            Route::post('/unpost', [JournalEntryController::class, 'unpostData'])->name('finance.journal-entry.unpost')->middleware('permission:Journal Entry,is_update');
            Route::post('/delete', [JournalEntryController::class, 'deleteData'])->name('finance.journal-entry.delete')->middleware('permission:Journal Entry,is_delete');
            Route::post('/attachment/delete', [JournalEntryController::class, 'deleteAttachment'])->name('finance.journal-entry.attachment.delete')->middleware('permission:Journal Entry,is_update');
            Route::get('/accounts', [JournalEntryController::class, 'accountsData'])->name('finance.journal-entry.accounts')->middleware('permission:Journal Entry,is_read');
            Route::get('/suggest-no', [JournalEntryController::class, 'suggestNoData'])->name('finance.journal-entry.suggest-no')->middleware('permission:Journal Entry,is_read');
        });

        Route::group(['prefix' => 'jurnal-umum'], function () {
            Route::get('/', [JurnalUmumController::class, 'indexView'])->name('finance.jurnal-umum.index.view')->middleware('permission:Jurnal Umum,is_read');
            Route::get('/{id}', [JurnalUmumController::class, 'showView'])->name('finance.jurnal-umum.show.view')->middleware('permission:Jurnal Umum,is_read');
        });

        Route::group(['prefix' => 'cash-bank'], function () {
            Route::get('/', [CashBankController::class, 'indexView'])->name('finance.cash-bank.index.view')->middleware('permission:Cash & Bank,is_read');
            Route::get('/mutations/{accountId}', [CashBankController::class, 'mutationsView'])->name('finance.cash-bank.mutations.view')->middleware('permission:Cash & Bank,is_read');
            Route::post('/reconciliation/start', [CashBankController::class, 'reconciliationStart'])->name('finance.cash-bank.reconciliation.start')->middleware('permission:Cash & Bank,is_update');
            Route::get('/reconciliation/history/{accountId}', [CashBankController::class, 'reconciliationHistory'])->name('finance.cash-bank.reconciliation.history')->middleware('permission:Cash & Bank,is_read');
            Route::get('/reconciliation/{id}', [CashBankController::class, 'reconciliationView'])->name('finance.cash-bank.reconciliation.view')->middleware('permission:Cash & Bank,is_read');
            Route::post('/reconciliation/save', [CashBankController::class, 'reconciliationSave'])->name('finance.cash-bank.reconciliation.save')->middleware('permission:Cash & Bank,is_update');
            Route::post('/reconciliation/reopen', [CashBankController::class, 'reconciliationReopen'])->name('finance.cash-bank.reconciliation.reopen')->middleware('permission:Cash & Bank,is_update');
        });

        Route::get('/balance-sheet', [FinancialReportController::class, 'balanceSheetView'])->name('finance.balance-sheet.index.view')->middleware('permission:Balance Sheet,is_read');
        Route::get('/income-statement', [FinancialReportController::class, 'incomeStatementView'])->name('finance.income-statement.index.view')->middleware('permission:Income Statement,is_read');
        Route::get('/cash-flow', [FinancialReportController::class, 'cashFlowView'])->name('finance.cash-flow.index.view')->middleware('permission:Cash Flow,is_read');
        Route::get('/general-ledger', [FinancialReportController::class, 'generalLedgerView'])->name('finance.general-ledger.index.view')->middleware('permission:General Ledger,is_read');
    });

    /*****************
     ** SETTINGS ROUTES **
     *****************/
    Route::group(['prefix' => 'settings'], function () {
        Route::get('/import-data', [ImportDataController::class, 'index'])->name('import-data.index');

        Route::get('/ai-configuration', [AiConfigurationController::class, 'indexView'])
            ->name('settings.ai-configuration.index.view')
            ->middleware('permission:AI Chat Configuration,is_read');
        Route::post('/ai-configuration', [AiConfigurationController::class, 'update'])
            ->name('settings.ai-configuration.update')
            ->middleware('permission:AI Chat Configuration,is_update');
        Route::post('/ai-configuration/test', [AiConfigurationController::class, 'testConnection'])
            ->name('settings.ai-configuration.test')
            ->middleware('permission:AI Chat Configuration,is_update');

        Route::get('/payment-gateway-configuration', [PaymentGatewayConfigurationController::class, 'indexView'])
            ->name('settings.payment-gateway-configuration.index.view')
            ->middleware('permission:Payment Gateway Configuration,is_read');
        Route::post('/payment-gateway-configuration', [PaymentGatewayConfigurationController::class, 'update'])
            ->name('settings.payment-gateway-configuration.update')
            ->middleware('permission:Payment Gateway Configuration,is_update');
        Route::post('/payment-gateway-configuration/test', [PaymentGatewayConfigurationController::class, 'testConnection'])
            ->name('settings.payment-gateway-configuration.test')
            ->middleware('permission:Payment Gateway Configuration,is_update');

        Route::get('/theme-configuration', [ThemeConfigurationController::class, 'indexView'])
            ->name('settings.theme-configuration.index.view')
            ->middleware('permission:Appearance & Theme,is_read');
        Route::post('/theme-configuration', [ThemeConfigurationController::class, 'update'])
            ->name('settings.theme-configuration.update')
            ->middleware('permission:Appearance & Theme,is_update');
    });

    /*****************
     ** CRM ROUTES **
     *****************/
    Route::group(['prefix' => 'crm'], function () {
        Route::group(['prefix' => 'membership-configuration'], function () {
            Route::get('/', [MembershipConfigurationController::class, 'indexView'])->name('crm.membership-configuration.index.view')->middleware('permission:Membership Configuration,is_read');
            Route::post('/data', [MembershipConfigurationController::class, 'indexData'])->name('crm.membership-configuration.index.data');
            Route::get('/insert', [MembershipConfigurationController::class, 'insertView'])->name('crm.membership-configuration.insert.view')->middleware('permission:Membership Configuration,is_create');
            Route::post('/insert/data', [MembershipConfigurationController::class, 'insertData'])->name('crm.membership-configuration.insert.data')->middleware('permission:Membership Configuration,is_create');
            Route::get('/edit/{id}', [MembershipConfigurationController::class, 'editView'])->name('crm.membership-configuration.edit.view')->middleware('permission:Membership Configuration,is_update');
            Route::post('/edit/data', [MembershipConfigurationController::class, 'editData'])->name('crm.membership-configuration.edit.data')->middleware('permission:Membership Configuration,is_update');
            Route::post('/delete', [MembershipConfigurationController::class, 'deleteData'])->name('crm.membership-configuration.delete.data')->middleware('permission:Membership Configuration,is_delete');
            Route::post('/restore', [MembershipConfigurationController::class, 'restoreData'])->name('crm.membership-configuration.restore.data')->middleware('permission:Membership Configuration,is_delete');
        });
    });

    /*****************
     ** PRODUCT ROUTES ** (Unified Product Master)
     *****************/
    Route::get('/product', function () {
        return redirect()->route('product.nature.index.view');
    })->name('product.index');
    Route::group(['prefix' => 'product'], function () {
        Route::get('/master', fn () => redirect()->route('product.nature.index.view'));
        Route::group(['prefix' => 'nature'], function () {
            Route::get('/', [ProductNatureController::class, 'indexView'])->name('product.nature.index.view')->middleware('permission:Product Type,is_read');
            Route::post('/data', [ProductNatureController::class, 'indexData'])->name('product.nature.index.data');
            Route::get('/insert', [ProductNatureController::class, 'insertView'])->name('product.nature.insert.view')->middleware('permission:Product Type,is_create');
            Route::post('/insert/data', [ProductNatureController::class, 'insertData'])->name('product.nature.insert.data')->middleware('permission:Product Type,is_create');
            Route::get('/edit/{id}', [ProductNatureController::class, 'editView'])->name('product.nature.edit.view')->middleware('permission:Product Type,is_update');
            Route::post('/edit/data', [ProductNatureController::class, 'editData'])->name('product.nature.edit.data')->middleware('permission:Product Type,is_update');
            Route::post('/delete', [ProductNatureController::class, 'deleteData'])->name('product.nature.delete.data')->middleware('permission:Product Type,is_delete');
            Route::post('/restore', [ProductNatureController::class, 'restoreData'])->name('product.nature.restore.data')->middleware('permission:Product Type,is_delete');
        });

        Route::group(['prefix' => 'category'], function () {
            Route::get('/', [ProductCategoryController::class, 'indexView'])->name('product.category.index.view')->middleware('permission:Category,is_read');
            Route::post('/data', [ProductCategoryController::class, 'indexData'])->name('product.category.index.data');
            Route::get('/insert', [ProductCategoryController::class, 'insertView'])->name('product.category.insert.view')->middleware('permission:Category,is_create');
            Route::post('/insert/data', [ProductCategoryController::class, 'insertData'])->name('product.category.insert.data')->middleware('permission:Category,is_create');
            Route::get('/edit/{id}', [ProductCategoryController::class, 'editView'])->name('product.category.edit.view')->middleware('permission:Category,is_update');
            Route::post('/edit/data', [ProductCategoryController::class, 'editData'])->name('product.category.edit.data')->middleware('permission:Category,is_update');
            Route::post('/delete', [ProductCategoryController::class, 'deleteData'])->name('product.category.delete.data')->middleware('permission:Category,is_delete');
            Route::post('/restore', [ProductCategoryController::class, 'restoreData'])->name('product.category.restore.data')->middleware('permission:Category,is_delete');
        });

        Route::group(['prefix' => 'satuan'], function () {
            Route::get('/', [ProductUnitController::class, 'indexView'])->name('product.unit.index.view')->middleware('permission:Unit,is_read');
            Route::post('/data', [ProductUnitController::class, 'indexData'])->name('product.unit.index.data');
            Route::get('/insert', [ProductUnitController::class, 'insertView'])->name('product.unit.insert.view')->middleware('permission:Unit,is_create');
            Route::post('/insert/data', [ProductUnitController::class, 'insertData'])->name('product.unit.insert.data')->middleware('permission:Unit,is_create');
            Route::get('/edit/{id}', [ProductUnitController::class, 'editView'])->name('product.unit.edit.view')->middleware('permission:Unit,is_update');
            Route::post('/edit/data', [ProductUnitController::class, 'editData'])->name('product.unit.edit.data')->middleware('permission:Unit,is_update');
            Route::post('/delete', [ProductUnitController::class, 'deleteData'])->name('product.unit.delete.data')->middleware('permission:Unit,is_delete');
            Route::post('/restore', [ProductUnitController::class, 'restoreData'])->name('product.unit.restore.data')->middleware('permission:Unit,is_delete');
        });

        Route::group(['prefix' => 'items'], function () {
            Route::get('/', [ProductController::class, 'indexView'])->name('product.index.view')->middleware('permission:Product,is_read');
            Route::post('/data', [ProductController::class, 'indexData'])->name('product.index.data');
            // 3-Step Insert Flow
            Route::get('/insert', [ProductController::class, 'insertViewStep1'])->name('product.insert.view')->middleware('permission:Product,is_create');
            Route::post('/insert/data', [ProductController::class, 'insertDataStep1'])->name('product.insert.data')->middleware('permission:Product,is_create');
            Route::get('/insert/step1', [ProductController::class, 'insertViewStep1'])->name('product.insert.view.step1')->middleware('permission:Product,is_create');
            Route::post('/insert/step1/data', [ProductController::class, 'insertDataStep1'])->name('product.insert.data.step1')->middleware('permission:Product,is_create');
            Route::get('/generate-code', [ProductController::class, 'generateCodeApi'])->name('product.generate.code')->middleware('permission:Product,is_create');
            Route::post('/quick-unit', [ProductUnitController::class, 'quickStore'])->name('product.quick-unit.store')->middleware('permission:Product,is_create');
            Route::post('/quick-category', [ProductCategoryController::class, 'quickStore'])->name('product.quick-category.store')->middleware('permission:Product,is_create');
            Route::get('/insert/step2', [ProductController::class, 'insertViewStep2'])->name('product.insert.view.step2')->middleware('permission:Product,is_create');
            Route::post('/insert/step2/data', [ProductController::class, 'insertDataStep2'])->name('product.insert.data.step2')->middleware('permission:Product,is_create');
            Route::get('/insert/step3', [ProductController::class, 'insertViewStep3'])->name('product.insert.view.step3')->middleware('permission:Product,is_create');
            Route::post('/insert/step3/data', [ProductController::class, 'insertDataStep3'])->name('product.insert.data.step3')->middleware('permission:Product,is_create');
            // Temp Conversion Routes (for insert flow)
            Route::post('/conversion/remove-temp', [ProductController::class, 'removeTempConversion'])->name('product.conversion.remove-temp');
            Route::post('/conversion/update-temp', [ProductController::class, 'updateTempConversion'])->name('product.conversion.update-temp');
            Route::get('/edit/{id}', [ProductController::class, 'editView'])->name('product.edit.view')->middleware('permission:Product,is_update');
            Route::get('/{id}/print-barcode', [ProductController::class, 'printBarcodeView'])->name('product.print-barcode.view')->middleware('permission:Product,is_read');
            Route::post('/{id}/print-barcode/preview', [ProductController::class, 'printBarcodePreview'])->name('product.print-barcode.preview')->middleware('permission:Product,is_read');
            Route::post('/{id}/print-barcode/reset-serials', [ProductController::class, 'printBarcodeResetSerials'])->name('product.print-barcode.reset-serials')->middleware('permission:Product,is_update');
            Route::post('/{id}/print-barcode/pdf', [ProductController::class, 'printBarcodePdf'])->name('product.print-barcode.pdf')->middleware('permission:Product,is_read');
            Route::get('/variants/{product_id}', [ProductController::class, 'variantsView'])->name('product.variants.view')->middleware('permission:Product,is_read');
            // Variant CRUD Routes
            Route::post('/variants/{product_id}/store', [ProductController::class, 'storeVariant'])->name('product.variant.store')->middleware('permission:Product,is_create');
            Route::get('/variants/{variant_id}/edit', [ProductController::class, 'editVariantView'])->name('product.variant.edit.view')->middleware('permission:Product,is_update');
            Route::post('/variants/{variant_id}/update', [ProductController::class, 'updateVariant'])->name('product.variant.update')->middleware('permission:Product,is_update');
            Route::post('/variants/{variant_id}/delete', [ProductController::class, 'deleteVariant'])->name('product.variant.delete')->middleware('permission:Product,is_delete');
            Route::get('/variants/{variant_id}/data', [ProductController::class, 'variantData'])->name('product.variant.data');
            Route::post('/edit/data', [ProductController::class, 'editData'])->name('product.edit.data')->middleware('permission:Product,is_update');
            Route::post('/delete', [ProductController::class, 'deleteData'])->name('product.delete.data')->middleware('permission:Product,is_delete');
            Route::post('/restore', [ProductController::class, 'restoreData'])->name('product.restore.data')->middleware('permission:Product,is_delete');
            Route::post('/add-conversion', [ProductController::class, 'addConversion'])->name('product.add-conversion')->middleware('permission:Product,is_update');
            Route::post('/edit-conversion', [ProductController::class, 'editConversion'])->name('product.edit-conversion')->middleware('permission:Product,is_update');
            Route::post('/delete-conversion', [ProductController::class, 'deleteConversion'])->name('product.delete-conversion')->middleware('permission:Product,is_delete');
            Route::post('/import', [ProductController::class, 'importData'])->name('product.import.data')->middleware('permission:Product,is_create');
            Route::get('/template', [ProductController::class, 'downloadTemplate'])->name('product.import.template')->middleware('permission:Product,is_read');
            Route::get('/export', [ProductController::class, 'exportData'])->name('product.export.data')->middleware('permission:Product,is_read');
        });

        Route::group(['prefix' => 'price-list'], function () {
            Route::get('/', [ProductPriceListController::class, 'indexView'])->name('product.price-list.index.view')->middleware('permission:Product,is_read');
            Route::post('/data', [ProductPriceListController::class, 'indexData'])->name('product.price-list.index.data');
            Route::get('/insert', [ProductPriceListController::class, 'insertView'])->name('product.price-list.insert.view')->middleware('permission:Product,is_create');
            Route::post('/insert/data', [ProductPriceListController::class, 'insertData'])->name('product.price-list.insert.data')->middleware('permission:Product,is_create');
            Route::get('/edit/{id}', [ProductPriceListController::class, 'editView'])->name('product.price-list.edit.view')->middleware('permission:Product,is_update');
            Route::post('/edit/data', [ProductPriceListController::class, 'editData'])->name('product.price-list.edit.data')->middleware('permission:Product,is_update');
            Route::post('/delete', [ProductPriceListController::class, 'deleteData'])->name('product.price-list.delete.data')->middleware('permission:Product,is_delete');
            Route::post('/restore', [ProductPriceListController::class, 'restoreData'])->name('product.price-list.restore.data')->middleware('permission:Product,is_delete');
            Route::get('/active', [ProductPriceListController::class, 'getActiveLists'])->name('product.price-list.active');
        });

        Route::group(['prefix' => 'attribute'], function () {
            Route::get('/', [AttributeDefinitionController::class, 'indexView'])->name('product.attribute.index.view')->middleware('permission:Attribute,is_read');
            Route::post('/data', [AttributeDefinitionController::class, 'indexData'])->name('product.attribute.index.data');
            Route::get('/insert', [AttributeDefinitionController::class, 'insertView'])->name('product.attribute.insert.view')->middleware('permission:Attribute,is_create');
            Route::post('/insert/data', [AttributeDefinitionController::class, 'insertData'])->name('product.attribute.insert.data')->middleware('permission:Attribute,is_create');
            Route::get('/edit/{id}', [AttributeDefinitionController::class, 'editView'])->name('product.attribute.edit.view')->middleware('permission:Attribute,is_update');
            Route::post('/edit/data', [AttributeDefinitionController::class, 'editData'])->name('product.attribute.edit.data')->middleware('permission:Attribute,is_update');
            Route::post('/delete', [AttributeDefinitionController::class, 'deleteData'])->name('product.attribute.delete.data')->middleware('permission:Attribute,is_delete');
            Route::post('/restore', [AttributeDefinitionController::class, 'restoreData'])->name('product.attribute.restore.data')->middleware('permission:Attribute,is_delete');
            Route::post('/add-value', [AttributeDefinitionController::class, 'addValue'])->name('product.attribute.add-value')->middleware('permission:Attribute,is_update');
            Route::post('/edit-value', [AttributeDefinitionController::class, 'editValue'])->name('product.attribute.edit-value')->middleware('permission:Attribute,is_update');
            Route::post('/delete-value', [AttributeDefinitionController::class, 'deleteValue'])->name('product.attribute.delete-value')->middleware('permission:Attribute,is_update');
        });

        Route::group(['prefix' => 'tag'], function () {
            Route::get('/', [ProductTagController::class, 'indexView'])->name('product.tag.index.view')->middleware('permission:Tag,is_read');
            Route::post('/data', [ProductTagController::class, 'indexData'])->name('product.tag.index.data');
            Route::get('/insert', [ProductTagController::class, 'insertView'])->name('product.tag.insert.view')->middleware('permission:Tag,is_create');
            Route::post('/insert/data', [ProductTagController::class, 'insertData'])->name('product.tag.insert.data')->middleware('permission:Tag,is_create');
            Route::get('/edit/{id}', [ProductTagController::class, 'editView'])->name('product.tag.edit.view')->middleware('permission:Tag,is_update');
            Route::post('/edit/data', [ProductTagController::class, 'editData'])->name('product.tag.edit.data')->middleware('permission:Tag,is_update');
            Route::post('/delete', [ProductTagController::class, 'deleteData'])->name('product.tag.delete.data')->middleware('permission:Tag,is_delete');
            Route::post('/restore', [ProductTagController::class, 'restoreData'])->name('product.tag.restore.data')->middleware('permission:Tag,is_delete');
        });

        Route::group(['prefix' => 'collection'], function () {
            Route::get('/', [ProductCollectionController::class, 'indexView'])->name('product.collection.index.view')->middleware('permission:Collection,is_read');
            Route::post('/data', [ProductCollectionController::class, 'indexData'])->name('product.collection.index.data');
            Route::get('/insert', [ProductCollectionController::class, 'insertView'])->name('product.collection.insert.view')->middleware('permission:Collection,is_create');
            Route::post('/insert/data', [ProductCollectionController::class, 'insertData'])->name('product.collection.insert.data')->middleware('permission:Collection,is_create');
            Route::get('/edit/{id}', [ProductCollectionController::class, 'editView'])->name('product.collection.edit.view')->middleware('permission:Collection,is_update');
            Route::post('/edit/data', [ProductCollectionController::class, 'editData'])->name('product.collection.edit.data')->middleware('permission:Collection,is_update');
            Route::post('/delete', [ProductCollectionController::class, 'deleteData'])->name('product.collection.delete.data')->middleware('permission:Collection,is_delete');
            Route::post('/restore', [ProductCollectionController::class, 'restoreData'])->name('product.collection.restore.data')->middleware('permission:Collection,is_delete');
        });

        Route::group(['prefix' => 'stock'], function () {
            Route::get('/', [ProductStockController::class, 'indexView'])->name('product.stock.index.view')->middleware('permission:Stock,is_read');
        });

        Route::group(['prefix' => 'price'], function () {
            Route::get('/', [ProductPriceController::class, 'indexView'])->name('product.price.index.view')->middleware('permission:Product Price,is_read');
            Route::post('/save', [ProductPriceController::class, 'saveData'])->name('product.price.save.data')->middleware('permission:Product Price,is_update');
        });

        Route::group(['prefix' => 'stock-opname'], function () {
            Route::get('/', [StockOpnameController::class, 'indexView'])->name('product.stock-opname.index')->middleware('permission:Stock Opname,is_read');
            Route::post('/save', [StockOpnameController::class, 'saveData'])->name('product.stock-opname.save')->middleware('permission:Stock Opname,is_update');
        });

        Route::group(['prefix' => 'stock-adjustment'], function () {
            Route::get('/', [StockAdjustmentController::class, 'indexView'])->name('product.stock-adjustment.index')->middleware('permission:Stock Adjustment,is_read');
            Route::post('/save', [StockAdjustmentController::class, 'saveData'])->name('product.stock-adjustment.save')->middleware('permission:Stock Adjustment,is_update');
        });

        Route::group(['prefix' => 'purchase-order'], function () {
            Route::get('/', [PurchaseOrderController::class, 'indexView'])->name('product.purchase-order.index.view')->middleware('permission:Purchase Order,is_read');
            Route::get('/suppliers-by-type', [PurchaseOrderController::class, 'suppliersByType'])->name('product.purchase-order.suppliers-by-type');
            Route::post('/data', [PurchaseOrderController::class, 'indexData'])->name('product.purchase-order.index.data');
            Route::get('/children/{id}', [PurchaseOrderController::class, 'childrenData'])->name('product.purchase-order.children.data');
            Route::get('/masters-for-sub', [PurchaseOrderController::class, 'mastersForSub'])->name('product.purchase-order.masters-for-sub');
            Route::get('/master-items/{id}', [PurchaseOrderController::class, 'masterItems'])->name('product.purchase-order.master-items');
            Route::get('/insert', [PurchaseOrderController::class, 'insertView'])->name('product.purchase-order.insert.view')->middleware('permission:Purchase Order,is_create');
            Route::post('/insert/data', [PurchaseOrderController::class, 'insertData'])->name('product.purchase-order.insert.data')->middleware('permission:Purchase Order,is_create');
            Route::get('/edit/{id}', [PurchaseOrderController::class, 'editView'])->name('product.purchase-order.edit.view')->middleware('permission:Purchase Order,is_update');
            Route::post('/edit/data', [PurchaseOrderController::class, 'editData'])->name('product.purchase-order.edit.data')->middleware('permission:Purchase Order,is_update');
            Route::post('/update-status', [PurchaseOrderController::class, 'updateStatusData'])->name('product.purchase-order.update-status.data')->middleware('permission:Purchase Order,is_update');
            Route::get('/detail/{id}', [PurchaseOrderController::class, 'detailView'])->name('product.purchase-order.detail.view')->middleware('permission:Purchase Order,is_read');
            Route::get('/detail/{id}/pdf', [PurchaseOrderController::class, 'exportPdf'])->name('product.purchase-order.pdf')->middleware('permission:Purchase Order,is_read');
            Route::post('/delete', [PurchaseOrderController::class, 'deleteData'])->name('product.purchase-order.delete.data')->middleware('permission:Purchase Order,is_delete');
            Route::post('/restore', [PurchaseOrderController::class, 'restoreData'])->name('product.purchase-order.restore.data')->middleware('permission:Purchase Order,is_delete');

            Route::get('/receive/{id}', [PurchaseOrderController::class, 'receiveView'])->name('product.purchase-order.receive.view')->middleware('permission:Purchase Order,is_update');
            Route::post('/receive/data', [PurchaseOrderController::class, 'receiveData'])->name('product.purchase-order.receive.data')->middleware('permission:Purchase Order,is_update');
            Route::get('/receive-detail/{id}', [PurchaseOrderController::class, 'receiveDetailView'])->name('product.purchase-order.receive-detail.view')->middleware('permission:Purchase Order,is_read');
            Route::get('/receive-detail/{id}/print-batch', [PurchaseOrderController::class, 'receiveBatchPrint'])->name('product.purchase-order.receive-batch.print')->middleware('permission:Purchase Order,is_read');
        });

        Route::group(['prefix' => 'purchase-invoice'], function () {
            Route::get('/', [PurchaseInvoiceController::class, 'indexView'])->name('product.purchase-invoice.index.view')->middleware('permission:Invoice,is_read');
            Route::post('/data', [PurchaseInvoiceController::class, 'indexData'])->name('product.purchase-invoice.index.data');
            Route::get('/eligible-pos', [PurchaseInvoiceController::class, 'eligiblePurchaseOrders'])->name('product.purchase-invoice.eligible-pos');
            Route::get('/insert', [PurchaseInvoiceController::class, 'insertView'])->name('product.purchase-invoice.insert.view')->middleware('permission:Invoice,is_create');
            Route::post('/insert/data', [PurchaseInvoiceController::class, 'insertData'])->name('product.purchase-invoice.insert.data')->middleware('permission:Invoice,is_create');
            Route::get('/edit/{id}', [PurchaseInvoiceController::class, 'editView'])->name('product.purchase-invoice.edit.view')->middleware('permission:Invoice,is_update');
            Route::post('/edit/data', [PurchaseInvoiceController::class, 'editData'])->name('product.purchase-invoice.edit.data')->middleware('permission:Invoice,is_update');
            Route::get('/detail/{id}', [PurchaseInvoiceController::class, 'detailView'])->name('product.purchase-invoice.detail.view')->middleware('permission:Invoice,is_read');
            Route::post('/submit', [PurchaseInvoiceController::class, 'submitData'])->name('product.purchase-invoice.submit.data')->middleware('permission:Invoice,is_update');
            Route::post('/payment', [PurchaseInvoiceController::class, 'paymentData'])->name('product.purchase-invoice.payment.data')->middleware('permission:Invoice,is_update');
            Route::post('/cancel', [PurchaseInvoiceController::class, 'cancelData'])->name('product.purchase-invoice.cancel.data')->middleware('permission:Invoice,is_update');
            Route::post('/delete', [PurchaseInvoiceController::class, 'deleteData'])->name('product.purchase-invoice.delete.data')->middleware('permission:Invoice,is_delete');
            Route::post('/restore', [PurchaseInvoiceController::class, 'restoreData'])->name('product.purchase-invoice.restore.data')->middleware('permission:Invoice,is_delete');
        });
    });

    /*****************
     ** REPORTING ROUTES ** (views/admin/reporting)
     *****************/

    Route::get('/reporting', function () {
        return redirect()->route('reporting.executive-dashboard.index');
    })->name('reporting.index');

    Route::group(['prefix' => 'reporting'], function () {
        // Executive & Overview
        Route::get('/executive-dashboard', [ReportSummarySalesController::class, 'indexView'])
            ->name('reporting.executive-dashboard.index');
        Route::get('/kpi-dashboard', [ReportAdvancedController::class, 'kpiDashboard'])
            ->name('reporting.kpi-dashboard.index');
        Route::get('/multi-outlet-comparison', [ReportTransactionController::class, 'indexView'])
            ->name('reporting.multi-outlet-comparison.index');
        Route::get('/revenue-trend', [ReportSummarySalesController::class, 'indexView'])
            ->name('reporting.revenue-trend.index');
        Route::get('/profitability-overview', [ReportAdvancedController::class, 'profitabilityOverview'])
            ->name('reporting.profitability-overview.index');
        Route::get('/business-unit-performance', [ReportTransactionController::class, 'indexView'])
            ->name('reporting.business-unit-performance.index');

        // Sales summary reporting
        Route::group(['prefix' => 'summary-sales'], function () {
            Route::get('/', [ReportSummarySalesController::class, 'indexView'])
                ->name('reporting.summary-sales.index');
        });

        // Sales & Transaction aliases
        Route::get('/sales-summary', [ReportSummarySalesController::class, 'indexView'])
            ->name('reporting.sales-summary.index');
        Route::get('/transaction-detail', [ReportTransactionController::class, 'indexView'])
            ->name('reporting.transaction-detail.index');
        Route::get('/sales-by-payment-method', [ReportSummarySalesController::class, 'indexView'])
            ->name('reporting.sales-by-payment-method.index');
        Route::get('/sales-by-cashier', [ReportAdvancedController::class, 'salesByCashier'])
            ->name('reporting.sales-by-cashier.index');
        Route::get('/sales-by-outlet', [ReportTransactionController::class, 'indexView'])
            ->name('reporting.sales-by-outlet.index');
        Route::get('/sales-by-customer', [ReportTransactionController::class, 'indexView'])
            ->name('reporting.sales-by-customer.index');
        Route::get('/barcode-dispatch', [ReportBarcodeTrackingController::class, 'index'])
            ->name('reporting.barcode-dispatch.index')
            ->middleware('permission:Barcode Dispatch,is_read');
        Route::get('/barcode-dispatch/export', [ReportBarcodeTrackingController::class, 'export'])
            ->name('reporting.barcode-dispatch.export')
            ->middleware('permission:Barcode Dispatch,is_read');
        Route::get('/agent-cutting-price', [ReportAgentCuttingPriceController::class, 'index'])
            ->name('reporting.agent-cutting-price.index')
            ->middleware('permission:Agent Cutting Price,is_read');
        Route::get('/agent-cutting-price/export', [ReportAgentCuttingPriceController::class, 'export'])
            ->name('reporting.agent-cutting-price.export')
            ->middleware('permission:Agent Cutting Price,is_read');
        Route::get('/agent-cutting-price/details', [ReportAgentCuttingPriceController::class, 'details'])
            ->name('reporting.agent-cutting-price.details')
            ->middleware('permission:Agent Cutting Price,is_read');
        Route::get('/sales-return-refund', [ReportTransactionController::class, 'indexView'])
            ->name('reporting.sales-return-refund.index');
        Route::get('/top-products-categories', [ReportSummarySalesController::class, 'indexView'])
            ->name('reporting.top-products-categories.index');
        Route::get('/hourly-daily-trend', [ReportSummarySalesController::class, 'indexView'])
            ->name('reporting.hourly-daily-trend.index');

        // Purchase order reporting
        Route::group(['prefix' => 'purchase-order'], function () {
            Route::get('/', [ReportPurchaseOrderController::class, 'indexView'])
                ->name('reporting.purchase-order.index');
        });

        // Purchasing aliases
        Route::get('/po-summary', [ReportPurchaseOrderController::class, 'indexView'])
            ->name('reporting.po-summary.index');
        Route::get('/po-receiving-grn', [ReportAdvancedController::class, 'poReceivingGrn'])
            ->name('reporting.po-receiving-grn.index');
        Route::get('/po-aging-open-po', [ReportAdvancedController::class, 'poAgingOpenPo'])
            ->name('reporting.po-aging-open-po.index');
        Route::get('/purchase-by-supplier', [ReportAdvancedController::class, 'purchaseBySupplier'])
            ->name('reporting.purchase-by-supplier.index');
        Route::get('/purchase-price-history', [ReportAdvancedController::class, 'purchasePriceHistory'])
            ->name('reporting.purchase-price-history.index');
        Route::get('/supplier-performance', [ReportAdvancedController::class, 'supplierPerformance'])
            ->name('reporting.supplier-performance.index');

        // Transaction reporting
        Route::group(['prefix' => 'transaction'], function () {
            Route::get('/', [ReportTransactionController::class, 'indexView'])
                ->name('reporting.transaction.index');
        });

        // Product / inventory reporting
        Route::get('/stock-movement', [ReportStockCardController::class, 'indexView'])
            ->name('reporting.stock-movement.index')
            ->middleware('permission:Stock Movement,is_read');

        Route::group(['prefix' => 'product'], function () {
            Route::group(['prefix' => 'stock-card'], function () {
                Route::get('/', [ReportStockCardController::class, 'indexView'])
                    ->name('reporting.stock-card.index')
                    ->middleware('permission:Stock Movement,is_read');
            });
            Route::group(['prefix' => 'stock-history'], function () {
                Route::get('/', [ReportStockHistoryController::class, 'indexView'])
                    ->name('reporting.stock-history.index')
                    ->middleware('permission:Stock History,is_read');
            });
        });

        // Inventory & Warehouse aliases
        Route::get('/stock-on-hand', [ReportStockHistoryController::class, 'indexView'])
            ->name('reporting.stock-on-hand.index');
        Route::get('/stock-card-movement', [ReportStockCardController::class, 'indexView'])
            ->name('reporting.stock-card-movement.index');
        Route::get('/low-stock-reorder-alert', [ReportStockHistoryController::class, 'indexView'])
            ->name('reporting.low-stock-reorder-alert.index');
        Route::get('/stock-opname-variance', [ReportStockCardController::class, 'indexView'])
            ->name('reporting.stock-opname-variance.index');
        Route::get('/stock-adjustment-report', [ReportStockCardController::class, 'indexView'])
            ->name('reporting.stock-adjustment-report.index');
        Route::get('/slow-dead-stock', [ReportStockHistoryController::class, 'indexView'])
            ->name('reporting.slow-dead-stock.index');
        Route::get('/stock-valuation', [ReportAdvancedController::class, 'stockValuation'])
            ->name('reporting.stock-valuation.index');
        Route::get('/inventory-aging', [ReportAdvancedController::class, 'inventoryAging'])
            ->name('reporting.inventory-aging.index');
        Route::get('/negative-stock-analysis', [ReportAdvancedController::class, 'negativeStockAnalysis'])
            ->name('reporting.negative-stock-analysis.index');
        Route::get('/fg-barcode-stock', [ReportFgBarcodeStockController::class, 'index'])
            ->name('reporting.fg-barcode-stock.index')
            ->middleware('permission:FG Barcode & Stock,is_read');
        Route::get('/fg-barcode-stock/export', [ReportFgBarcodeStockController::class, 'export'])
            ->name('reporting.fg-barcode-stock.export')
            ->middleware('permission:FG Barcode & Stock,is_read');
        Route::get('/fg-barcode-stock/serials', [ReportFgBarcodeStockController::class, 'serials'])
            ->name('reporting.fg-barcode-stock.serials')
            ->middleware('permission:FG Barcode & Stock,is_read');
    });

    /*****************
     ** UPLOAD ROUTE **
     *****************/
    Route::post('/upload-file', [FileUploadController::class, 'upload']);

    /*****************
     ** TRANSACTION ROUTES ** (views/admin/transaction)
     *****************/
    Route::group(['prefix' => 'transaction'], function () {
        Route::get('/', [TransactionController::class, 'indexView'])->name('transaction.index');
        Route::post('/data', [TransactionController::class, 'indexData'])->name('transaction.index.data');
        Route::get('/pos', [POSController::class, 'indexView'])->name('transaction.pos');
        Route::get('/pos/product-variants', [POSController::class, 'getProductVariants'])->name('transaction.pos.product-variants');
        Route::post('/pos/preview-promo', [POSController::class, 'previewPromo'])->name('transaction.pos.preview-promo');
        Route::post('/pos/barcode-lookup', [POSController::class, 'lookupBarcode'])->name('transaction.pos.barcode-lookup');
        Route::post('/pos/payment', [POSController::class, 'processPayment'])->name('transaction.pos.payment');
        Route::get('/pos/payment/{orderId}/status', [POSController::class, 'paymentStatus'])->name('transaction.pos.payment.status');
        Route::post('/pos/payment/{orderId}/sync', [POSController::class, 'syncPaymentStatus'])->name('transaction.pos.payment.sync');
        Route::get('/pos/payment/return', [POSController::class, 'paymentReturn'])->name('transaction.pos.payment.return');
        Route::group(['prefix' => 'method-payment'], function () {
            Route::get('/', [MethodPaymentController::class, 'indexView'])->name('pos.method-payment.index')->middleware('permission:Method Payment,is_read');
            Route::post('/data', [MethodPaymentController::class, 'indexData'])->name('pos.method-payment.index.data');
            Route::get('/insert', [MethodPaymentController::class, 'insertView'])->name('pos.method-payment.insert.view')->middleware('permission:Method Payment,is_create');
            Route::post('/insert/data', [MethodPaymentController::class, 'insertData'])->name('pos.method-payment.insert.data')->middleware('permission:Method Payment,is_create');
            Route::get('/edit/{id}', [MethodPaymentController::class, 'editView'])->name('pos.method-payment.edit.view')->middleware('permission:Method Payment,is_update');
            Route::post('/edit/data', [MethodPaymentController::class, 'editData'])->name('pos.method-payment.edit.data')->middleware('permission:Method Payment,is_update');
            Route::post('/delete', [MethodPaymentController::class, 'deleteData'])->name('pos.method-payment.delete.data')->middleware('permission:Method Payment,is_delete');
            Route::post('/restore', [MethodPaymentController::class, 'restoreData'])->name('pos.method-payment.restore.data')->middleware('permission:Method Payment,is_delete');
            Route::post('/sync-pg-channels', [MethodPaymentController::class, 'syncPgChannels'])->name('pos.method-payment.sync-pg')->middleware('permission:Method Payment,is_create');
        });

        // Detail / print routes (must be after all named routes to avoid conflicts)
        Route::get('/detail/{id}', [TransactionController::class, 'detailView'])->name('transaction.detail');
        Route::get('/{id}/print-invoice', [TransactionController::class, 'printInvoice'])->name('transaction.print-invoice');
    });

    /*****************
     ** CUSTOMER ROUTES ** (views/admin/customer)
     *****************/
    Route::group(['prefix' => 'customer'], function () {
        Route::group(['prefix' => 'group'], function () {
            Route::get('/', [CustomerGroupController::class, 'indexView'])->name('customer.group.index')->middleware('permission:Group,is_read');
            Route::post('/data', [CustomerGroupController::class, 'indexData'])->name('customer.group.index.data');
            Route::get('/insert', [CustomerGroupController::class, 'insertView'])->name('customer.group.insert.view')->middleware('permission:Group,is_create');
            Route::post('/insert/data', [CustomerGroupController::class, 'insertData'])->name('customer.group.insert.data')->middleware('permission:Group,is_create');
            Route::get('/edit/{id}', [CustomerGroupController::class, 'editView'])->name('customer.group.edit.view')->middleware('permission:Group,is_update');
            Route::post('/edit/data', [CustomerGroupController::class, 'editData'])->name('customer.group.edit.data')->middleware('permission:Group,is_update');
            Route::post('/delete', [CustomerGroupController::class, 'deleteData'])->name('customer.group.delete.data')->middleware('permission:Group,is_delete');
            Route::post('/restore', [CustomerGroupController::class, 'restoreData'])->name('customer.group.restore.data')->middleware('permission:Group,is_delete');
        });
        Route::group(['prefix' => 'list'], function () {
            Route::get('/', [CustomerController::class, 'indexView'])->name('customer.list.index')->middleware('permission:List,is_read');
            Route::post('/data', [CustomerController::class, 'indexData'])->name('customer.list.index.data');
            Route::get('/insert', [CustomerController::class, 'insertView'])->name('customer.list.insert.view')->middleware('permission:List,is_create');
            Route::post('/insert/data', [CustomerController::class, 'insertData'])->name('customer.list.insert.data')->middleware('permission:List,is_create');
            Route::get('/edit/{id}', [CustomerController::class, 'editView'])->name('customer.list.edit.view')->middleware('permission:List,is_update');
            Route::post('/edit/data', [CustomerController::class, 'editData'])->name('customer.list.edit.data')->middleware('permission:List,is_update');
            Route::post('/delete', [CustomerController::class, 'deleteData'])->name('customer.list.delete.data')->middleware('permission:List,is_delete');
            Route::post('/restore', [CustomerController::class, 'restoreData'])->name('customer.list.restore.data')->middleware('permission:List,is_delete');
            Route::post('/remove-attachment', [CustomerController::class, 'removeAttachment'])->name('customer.list.remove.attachment')->middleware('permission:List,is_update');
        });
    });

});

Route::group(['prefix' => 'helper'], function () {
    Route::get('/provinces', [HelperController::class, 'getProvinces'])->name('helper.provinces');
    Route::get('/cities', [HelperController::class, 'getCities'])->name('helper.cities');
    Route::get('/business-units', [HelperController::class, 'getBusinessUnits'])->name('helper.business-units');
    Route::get('/product-variants', [HelperController::class, 'getProductVariants'])->name('helper.product-variants');
});

require __DIR__ . '/auth.php';
require __DIR__ . '/customer.php';
require __DIR__ . '/agent.php';

require __DIR__.'/distribution.php';
require __DIR__.'/training.php';
require __DIR__.'/marketing.php';
