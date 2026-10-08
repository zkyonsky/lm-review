<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    // Admin Routes
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', App\Http\Controllers\Admin\UserController::class)->except(['show']);
        Route::resource('trainings', App\Http\Controllers\Admin\TrainingController::class);
        Route::resource('trainings.subjects', App\Http\Controllers\Admin\SubjectController::class)->shallow();
        Route::resource('subjects.materials', App\Http\Controllers\Admin\MaterialController::class)->shallow();
        
        // Subject Assignments (Pengembang & Reviewer)
        Route::post('subjects/{subject}/assign', [App\Http\Controllers\Admin\SubjectAssignmentController::class, 'store'])->name('subjects.assign');
        Route::delete('subjects/{subject}/assign/{user}', [App\Http\Controllers\Admin\SubjectAssignmentController::class, 'destroy'])->name('subjects.unassign');
        
        // Material Versions
        Route::get('materials/{material}/versions/create', [App\Http\Controllers\Admin\MaterialVersionController::class, 'create'])->name('materials.versions.create');
        Route::post('materials/{material}/versions', [App\Http\Controllers\Admin\MaterialVersionController::class, 'store'])->name('materials.versions.store');
        Route::get('versions/{version}', [App\Http\Controllers\Admin\MaterialVersionController::class, 'show'])->name('versions.show');
        Route::delete('versions/{version}', [App\Http\Controllers\Admin\MaterialVersionController::class, 'destroy'])->name('versions.destroy');
        Route::patch('versions/{version}/toggle', [App\Http\Controllers\Admin\MaterialVersionController::class, 'toggleActive'])->name('versions.toggle');

        // Review Assignments & Workspace
        Route::post('versions/{version}/assign', [App\Http\Controllers\Admin\ReviewAssignmentController::class, 'store'])->name('versions.assign');
        Route::delete('versions/{version}/assign/{review}', [App\Http\Controllers\Admin\ReviewAssignmentController::class, 'destroy'])->name('versions.unassign');
        
        Route::get('workspace/{review}', [App\Http\Controllers\Admin\ReviewWorkspaceController::class, 'show'])->name('workspace.show');
        Route::post('workspace/{review}/comments/{comment}/reply', [App\Http\Controllers\Admin\ReviewWorkspaceController::class, 'reply'])->name('workspace.comments.reply');
        Route::patch('workspace/{review}/comments/{comment}/toggle', [App\Http\Controllers\Admin\ReviewWorkspaceController::class, 'toggleStatus'])->name('workspace.comments.toggle');

        // Reports
        Route::get('reports/comments', [App\Http\Controllers\Admin\ReportController::class, 'exportComments'])->name('reports.comments');
    });

    // Reviewer Routes
    Route::middleware('role:reviewer')->prefix('reviewer')->name('reviewer.')->group(function () {
        Route::get('reviews', [App\Http\Controllers\Reviewer\ReviewController::class, 'index'])->name('reviews.index');
        Route::get('workspace/{review}', [App\Http\Controllers\Reviewer\ReviewController::class, 'workspace'])->name('workspace.show');
        Route::post('workspace/{review}/comments', [App\Http\Controllers\Reviewer\ReviewController::class, 'storeComment'])->name('workspace.comments.store');
        Route::post('workspace/{review}/submit', [App\Http\Controllers\Reviewer\ReviewController::class, 'submitReview'])->name('workspace.submit');
    });

    // Developer / Pengembang Routes
    Route::middleware('role:pengembang|developer')->prefix('developer')->name('developer.')->group(function () {
        Route::get('subjects', [App\Http\Controllers\Developer\SubjectController::class, 'index'])->name('subjects.index');
        Route::get('subjects/{subject}', [App\Http\Controllers\Developer\SubjectController::class, 'show'])->name('subjects.show');

        // Material Management
        Route::get('subjects/{subject}/materials/create', [App\Http\Controllers\Developer\MaterialController::class, 'create'])->name('subjects.materials.create');
        Route::post('subjects/{subject}/materials', [App\Http\Controllers\Developer\MaterialController::class, 'store'])->name('subjects.materials.store');
        Route::get('materials/{material}', [App\Http\Controllers\Developer\MaterialController::class, 'show'])->name('materials.show');
        Route::get('materials/{material}/edit', [App\Http\Controllers\Developer\MaterialController::class, 'edit'])->name('materials.edit');
        Route::put('materials/{material}', [App\Http\Controllers\Developer\MaterialController::class, 'update'])->name('materials.update');

        // Material Versions
        Route::get('materials/{material}/versions/create', [App\Http\Controllers\Developer\MaterialVersionController::class, 'create'])->name('materials.versions.create');
        Route::post('materials/{material}/versions', [App\Http\Controllers\Developer\MaterialVersionController::class, 'store'])->name('materials.versions.store');
        Route::get('versions/{version}', [App\Http\Controllers\Developer\MaterialVersionController::class, 'show'])->name('versions.show');

        // Workspace Review & Feedback
        Route::get('workspace/{review}', [App\Http\Controllers\Developer\ReviewWorkspaceController::class, 'show'])->name('workspace.show');
        Route::post('workspace/{review}/comments/{comment}/reply', [App\Http\Controllers\Developer\ReviewWorkspaceController::class, 'reply'])->name('workspace.comments.reply');
        Route::patch('workspace/{review}/comments/{comment}/toggle', [App\Http\Controllers\Developer\ReviewWorkspaceController::class, 'toggleStatus'])->name('workspace.comments.toggle');
    });

    // Viewer Route
    Route::get('/viewer/{version}', [App\Http\Controllers\ViewerController::class, 'show'])->name('viewer.show');
});

require __DIR__.'/settings.php';
