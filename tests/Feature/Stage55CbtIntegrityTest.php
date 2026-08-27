<?php

use App\Models\CbtAttempt;
use App\Models\CbtExam;
use Illuminate\Support\Facades\Route;

it('keeps CBT exam routes protected for exam officers and teachers only', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/cbt/'));

    expect($routes)->not->toBeEmpty();
    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->toContain('auth:sanctum');
        expect($route->gatherMiddleware())->toContain('school.active');
        expect($route->gatherMiddleware())->toContain('role:exam_officer|teacher');
    }
});

it('keeps student CBT routes restricted to student accounts', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/student/cbt/'));

    expect($routes)->not->toBeEmpty();
    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->toContain('auth:sanctum');
        expect($route->gatherMiddleware())->toContain('school.active');
        expect($route->gatherMiddleware())->toContain('role:student');
    }
});

it('uses soft deletion for CBT exams so saved attempts are not cascade-deleted', function () {
    expect(in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(CbtExam::class), true))->toBeTrue();
    expect((new CbtAttempt)->exam()->getRelated())->toBeInstanceOf(CbtExam::class);
});
