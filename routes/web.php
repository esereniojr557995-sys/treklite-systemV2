<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OptionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\InventoryReportController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\OwnerOnly;
use Illuminate\Support\Facades\Route;

// Only for people who are not logged in
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Must be logged in
Route::middleware(['auth', EnsureUserIsActive::class])->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');   // staff are redirected to New Sale

    // Owner and Staff: everyone has their OWN profile (name, email, password)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

    // Photos kept in the database (product photos, payment confirmations)
    Route::get('/images/{image}', [ImageController::class, 'show'])->whereNumber('image')->name('images.show');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');   // stock lookup
    Route::get('/sales/new', [SaleController::class, 'create'])->name('sales.create');
    // Must come before /sales/{sale}, otherwise "export" would be read as an invoice id.
    Route::get('/sales/export', [SaleController::class, 'export'])->middleware(OwnerOnly::class)->name('sales.export');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');

    // Owner only
    Route::middleware(OwnerOnly::class)->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('/products/{product}/archive', [ProductController::class, 'archive'])->name('products.archive');
    Route::post('/products/{product}/restore', [ProductController::class, 'restore'])->name('products.restore');

    // Pick-lists for products: brands, categories, sizes, colors (add / edit / disable / delete)
    Route::get('/options', [OptionController::class, 'index'])->name('options.index');
    Route::post('/options/{type}', [OptionController::class, 'store'])->name('options.store');
    Route::put('/options/{type}/{id}', [OptionController::class, 'update'])->whereNumber('id')->name('options.update');
    Route::post('/options/{type}/{id}/toggle', [OptionController::class, 'toggle'])->whereNumber('id')->name('options.toggle');
    Route::delete('/options/{type}/{id}', [OptionController::class, 'destroy'])->whereNumber('id')->name('options.destroy');

    Route::post('/sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void');
    Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');

    Route::get('/inventory/history', [InventoryController::class, 'history'])->name('inventory.history');
    Route::post('/inventory/{variant}/stock', [InventoryController::class, 'stock'])->name('inventory.stock');

    Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
    Route::get('/reports/sales/export', [ReportController::class, 'export'])->name('reports.sales.export');          // ?format=pdf|xlsx
    Route::get('/reports/inventory', [InventoryReportController::class, 'index'])->name('reports.inventory');
    Route::get('/reports/inventory/export', [InventoryReportController::class, 'export'])->name('reports.inventory.export');   // ?format=pdf|xlsx

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::post('/backup', [BackupController::class, 'store'])->name('backup.store');
    Route::get('/backup/{name}', [BackupController::class, 'download'])->name('backup.download');
    Route::post('/backup/restore', [BackupController::class, 'restore'])->name('backup.restore');
    });
});