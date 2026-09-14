<?php

namespace App\Tenancy;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            $organizationId = app(TenantContext::class)->id();

            if (config('app.is_tenant') && $organizationId !== null) {
                $builder->where($builder->getModel()->qualifyColumn('organization_id'), $organizationId);
            }
        });

        static::creating(function (Model $model): void {
            if (filled($model->getAttribute('organization_id'))) {
                return;
            }

            $organizationId = app(TenantContext::class)->id()
                ?? Organization::query()->where('is_default', true)->value('id');

            if (is_string($organizationId) && $organizationId !== '') {
                $model->setAttribute('organization_id', $organizationId);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function belongsToOrganization(?string $organizationId): bool
    {
        return ! config('app.is_tenant')
            || ($organizationId !== null && $this->getAttribute('organization_id') === $organizationId);
    }

    public function isInSameOrganizationAs(Model $model): bool
    {
        return ! config('app.is_tenant')
            || ($this->getAttribute('organization_id') !== null
                && $this->getAttribute('organization_id') === $model->getAttribute('organization_id'));
    }
}
