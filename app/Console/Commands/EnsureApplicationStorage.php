<?php

namespace App\Console\Commands;

use App\Support\PublicStorageLinker;
use Illuminate\Console\Command;

/**
 * Prepare storage/bootstrap folders and public/storage link without calling exec().
 */
class EnsureApplicationStorage extends Command
{
    protected $signature = 'app:ensure-storage {--force : Replace an existing public/storage path}';

    protected $description = 'Create required storage directories and link public/storage (no exec)';

    public function handle(): int
    {
        PublicStorageLinker::ensureDirectories();

        $result = PublicStorageLinker::linkPublicStorage((bool) $this->option('force'));

        if ($result['ok']) {
            $this->info('public/storage is linked to storage/app/public.');

            return self::SUCCESS;
        }

        if ($result['message'] === 'exists') {
            $this->warn('public/storage exists but points elsewhere. Use --force to replace.');

            return self::FAILURE;
        }

        $this->warn('Could not create the link from PHP.');
        if ($result['shell_hint']) {
            $this->line('Run manually:');
            $this->comment('  '.$result['shell_hint']);
        }

        return self::FAILURE;
    }
}
