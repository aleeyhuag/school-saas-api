<?php

use App\Models\School;
use App\Services\SchoolBackupService;
use Illuminate\Support\Facades\Route;

it('restricts school backup downloads to the proprietor role', function () {
    $matching = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'api/school-backup/download' && in_array('GET', $route->methods(), true));

    expect($matching)->not->toBeNull();
    expect($matching->gatherMiddleware())->toContain('role:proprietor');
});

it('restricts platform backups to the super admin role', function () {
    $matching = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'api/platform/backup/download' && in_array('GET', $route->methods(), true));

    expect($matching)->not->toBeNull();
    expect($matching->gatherMiddleware())->toContain('role:super_admin');
});

it('rejects unsupported school export modules before touching school data', function () {
    $school = new School(['name' => 'Test School']);

    expect(fn () => app(SchoolBackupService::class)->createModule($school, 'passwords'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported school export module.');
});
