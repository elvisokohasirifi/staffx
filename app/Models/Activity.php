<?php

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Spatie\Activitylog\Models\Activity as BaseActivity;

class Activity extends BaseActivity
{
    use BelongsToOrganization;
}
