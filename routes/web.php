<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\ReceiptSettingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    // 1. Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 2. Kasir (POS Transaction)
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos', [PosController::class, 'store'])->name('pos.store');
    Route::post('/pos/quick-customer', [PosController::class, 'quickCustomer'])->name('pos.quick-customer');

    // 3. Riwayat Penjualan (Order History, Cancel, Return, Repay, Status, Delivery & Courier)
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/lookup-booking', [OrderController::class, 'lookupBooking'])->name('orders.lookup-booking');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/delivery', [OrderController::class, 'updateDelivery'])->name('orders.delivery');
    Route::post('/orders/{order}/confirm-driver-pickup', [OrderController::class, 'confirmDriverPickup'])->name('orders.confirm-driver-pickup');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/return', [OrderController::class, 'returnOrder'])->name('orders.return');
    Route::post('/orders/{order}/payment', [OrderController::class, 'updatePayment'])->name('orders.payment');
    Route::post('/orders/{order}/terms', [OrderController::class, 'storeTerm'])->name('orders.terms.store');
    Route::delete('/orders/{order}/terms/{payment}', [OrderController::class, 'deleteTerm'])->name('orders.terms.destroy');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

    // 4. Produk
    Route::post('/products/bulk-stock', [ProductController::class, 'bulkStock'])->name('products.bulk-stock');
    Route::resource('products', ProductController::class)->except(['create', 'edit', 'show']);

    // 5. Kategori Produk
    Route::resource('categories', CategoryController::class)->except(['create', 'edit', 'show']);

    // 6. Bahan Baku (Raw Materials)
    Route::resource('raw-materials', RawMaterialController::class)->except(['create', 'edit', 'show']);

    // 7. Pelanggan (Customers)
    Route::resource('customers', CustomerController::class)->except(['create', 'edit', 'show']);

    // 8. Shift Kasir (Multi Person, Roles, Permissions)
    Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('/shifts/start', [ShiftController::class, 'start'])->name('shifts.start');
    Route::post('/shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
    Route::post('/shifts/users', [ShiftController::class, 'storeUser'])->name('shifts.users.store');
    Route::put('/shifts/users/{user}', [ShiftController::class, 'updateUser'])->name('shifts.users.update');
    Route::delete('/shifts/users/{user}', [ShiftController::class, 'destroyUser'])->name('shifts.users.destroy');
    Route::post('/shifts/switch-user', [ShiftController::class, 'switchUser'])->name('shifts.switch-user');

    // 9. Laporan Harian/Mingguan/Bulanan
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export-csv', [ReportController::class, 'exportCsv'])->name('reports.export-csv');

    // 10. Laporan Stok (Tambah / Kurang)
    Route::get('/stock-reports', [StockReportController::class, 'index'])->name('stock-reports.index');
    Route::post('/stock-reports/adjust', [StockReportController::class, 'adjust'])->name('stock-reports.adjust');
    Route::post('/stock-reports/bulk-adjust', [StockReportController::class, 'bulkAdjust'])->name('stock-reports.bulk-adjust');

    // 11. Pengaturan
    Route::resource('settings/units', UnitController::class)->names('settings.units')->except(['create', 'edit', 'show']);
    Route::get('/settings/receipt', [ReceiptSettingController::class, 'index'])->name('settings.receipt');
    Route::put('/settings/receipt', [ReceiptSettingController::class, 'update'])->name('settings.receipt.update');
});

require __DIR__.'/settings.php';
