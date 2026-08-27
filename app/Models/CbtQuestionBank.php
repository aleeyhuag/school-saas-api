<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtQuestionBank extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'subject_id', 'created_by_user_id', 'question_text', 'topic', 'marks',
    ];

    protected $casts = ['marks' => 'decimal:2'];

    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function options(): HasMany { return $this->hasMany(CbtQuestionBankOption::class)->orderBy('position'); }
}
