<?php

use App\Http\Controllers\SheetTabController;
use App\Http\Controllers\SpreadsheetController;
use App\Http\Controllers\SyncLogController;
use App\Http\Controllers\TableMappingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/spreadsheets');

Route::get('spreadsheets/lookup', [SpreadsheetController::class, 'lookup'])->name('spreadsheets.lookup');
Route::resource('spreadsheets', SpreadsheetController::class)->except(['create', 'edit']);
Route::post('spreadsheets/{spreadsheet}/sync', [SpreadsheetController::class, 'sync'])->name('spreadsheets.sync');
Route::post('spreadsheets/{spreadsheet}/fetch-tabs', [SpreadsheetController::class, 'fetchTabs'])->name('spreadsheets.fetch-tabs');

Route::post('spreadsheets/{spreadsheet}/tabs', [SheetTabController::class, 'store'])->name('tabs.store');
Route::get('tabs/{tab}/edit', [SheetTabController::class, 'edit'])->name('tabs.edit');
Route::put('tabs/{tab}', [SheetTabController::class, 'update'])->name('tabs.update');
Route::delete('tabs/{tab}', [SheetTabController::class, 'destroy'])->name('tabs.destroy');
Route::get('tabs/{tab}/preview', [SheetTabController::class, 'preview'])->name('tabs.preview');

Route::get('tabs/{tab}/mappings/create', [TableMappingController::class, 'create'])->name('mappings.create');
Route::post('tabs/{tab}/mappings', [TableMappingController::class, 'store'])->name('mappings.store');
Route::get('mappings/{mapping}/edit', [TableMappingController::class, 'edit'])->name('mappings.edit');
Route::put('mappings/{mapping}', [TableMappingController::class, 'update'])->name('mappings.update');
Route::delete('mappings/{mapping}', [TableMappingController::class, 'destroy'])->name('mappings.destroy');

Route::get('logs', [SyncLogController::class, 'index'])->name('logs.index');
