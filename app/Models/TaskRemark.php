<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Database\Factories\TaskRemarkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'task_id',
    'author_id',
    'parent_remark_id',
    'body',
    'is_admin_remark',
])]
class TaskRemark extends Model
{
    use CrudTrait;

    /** @use HasFactory<TaskRemarkFactory> */
    use HasFactory;

    use HasUuids;

    public string $identifiableAttribute = 'remark_preview';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_admin_remark' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parentRemark(): BelongsTo
    {
        return $this->belongsTo(TaskRemark::class, 'parent_remark_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TaskRemark::class, 'parent_remark_id')->orderBy('created_at');
    }

    public function getRemarkPreviewAttribute(): string
    {
        return Str::limit(strip_tags($this->body), 60);
    }
}
