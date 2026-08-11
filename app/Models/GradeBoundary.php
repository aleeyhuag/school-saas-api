<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class GradeBoundary extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'grade', 'remark', 'min_score', 'max_score'];
}
