<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Database\Factories\PerformanceQueryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['admin_id', 'organization_id', 'question', 'chart_type', 'result_data'])]
class PerformanceQuery extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<PerformanceQueryFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result_data' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
