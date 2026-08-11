<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class RunDatabaseBackupJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $exitCode = Artisan::call('backup:run', [
            '--only-db' => true,
            '--disable-notifications' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Daily database backup failed: '.trim(Artisan::output()));
        }
    }
}
