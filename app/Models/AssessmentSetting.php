<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class AssessmentSetting extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'ca_weight', 'assignment_weight', 'exam_weight'];
}
