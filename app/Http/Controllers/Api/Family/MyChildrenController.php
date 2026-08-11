<?php

namespace App\Http\Controllers\Api\Family;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Self-service — a Parent's OWN linked children. Distinct from
 * StudentController::index (admin-only) — this only ever returns
 * students this specific logged-in user is a guardian of.
 */
class MyChildrenController extends Controller
{
    public function __invoke()
    {
        return Auth::user()->children()->with('schoolClass')->get();
    }
}
