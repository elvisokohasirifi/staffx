<?php

use Illuminate\Support\Facades\Route;

// --------------------------
// Custom Backpack Routes
// --------------------------
// This route file is loaded automatically by Backpack\CRUD.
// Routes you generate using Backpack\Generators will be placed here.

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () { // custom admin routes
    Route::crud('staff', 'UserCrudController');
    Route::get('tasks/bulk-create', 'TaskCrudController@bulkCreate')->name('tasks.bulk-create');
    Route::post('tasks/bulk-create', 'TaskCrudController@bulkStore')->name('tasks.bulk-store');
    Route::crud('tasks', 'TaskCrudController');
    Route::crud('remarks', 'TaskRemarkCrudController');
    Route::post('tasks/{id}/remarks', 'TaskCrudController@storeRemark')->name('tasks.remarks.store');
    Route::post('tasks/{id}/mark-in-progress', 'TaskCrudController@markInProgress')->name('tasks.mark-in-progress');
    Route::post('tasks/{id}/mark-completed', 'TaskCrudController@markCompleted')->name('tasks.mark-completed');
}); // this should be the absolute last line of this file

/**
 * DO NOT ADD ANYTHING HERE.
 */
