<?php

use App\Http\Controllers\Api\School\BranchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Branches (multi-branch Proprietor support)
|--------------------------------------------------------------------------
|
| Every other role stays tied to exactly one school — these routes
| only ever do anything for a Proprietor. See BranchController's
| class docblock for how switching actually works under the hood.
|
*/

/*
|--------------------------------------------------------------------------
| API Routes — Branches (multi-branch Proprietor support)
|--------------------------------------------------------------------------
|
| Every other role stays tied to exactly one school — these routes
| only ever do anything for a Proprietor. See BranchController's
| class docblock for how switching actually works under the hood.
|
| 'branches' (list) and 'branches/switch' are deliberately NOT gated
| by 'school.active' — same narrow self-unlock reasoning as Billing
| (see api-billing.php). If the branch a Proprietor happens to be
| CURRENTLY switched into gets disabled, 'school.active' would block
| every request on that school_id, including the very request needed
| to switch to a different, still-active branch they own — locking
| them out of branches that were never disabled. 'branches' (add a
| new branch) stays gated as before; there's no self-unlock need for
| that one.
|
*/

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor'])->group(function () {
    Route::get('branches', [BranchController::class, 'index']);
    Route::post('branches/switch', [BranchController::class, 'switchBranch']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor'])->group(function () {
    Route::post('branches', [BranchController::class, 'store']);
});
