<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class PeriodDefinition extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'period_number', 'start_time', 'end_time'];
}
