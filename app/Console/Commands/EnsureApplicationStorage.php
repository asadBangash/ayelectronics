<?php

namespace App\Console\Commands;

use App\Support\PublicStorageLinker;
use Illuminate\Console\Command;

/**
 * Prepare storage/bootstrap folders and public/storage link without calling exec().
 */
class EnsureApplicationStorage extends Command
{
    protected $signature = 'app:ensure-storage
                            {--force : Replace an existing public/storage path}
                            {--mirror : Copy files into public/storage when symlinks are disabled}';

    protected $description = 'Create required storage directories and link public/storage (no exec)';

    public function handle(): int
    {
        PublicStorageLinker::ensureDirectories();

        $force = (bool) $this->option('force');

        if ($this->option('mirror')) {
            $mirror = PublicStorageLinker::mirror($force);
            if ($mirror['ok']) {
                $this->info('public/storage mirrors storage/app/public (copy mode).');

                return self::SUCCESS;
            }
            if ($mirror['message'] === 'exists') {
                $this->warn('public/storage already has files. Use --force to replace.');

                return self::FAILURE;
            }
        }

        $result = PublicStorageLinker::linkPublicStorage($force);

        if ($result['ok']) {
            $this->info('public/storage is linked to storage/app/public.');

            return self::SUCCESS;
        }

        if ($result['message'] === 'exists') {
            $this->warn('public/storage exists but points elsewhere. Use --force to replace.');

            return self::FAILURE;
        }

        $this->warn('PHP cannot create a symlink on this server (normal on Hostinger).');
        if ($result['shell_hint']) {
            $this->line('Best fix — run in SSH:');
            $this->comment('  '.$result['shell_hint']);
        }
        $this->newLine();
        $this->info('Fallback: /storage/* URLs are served by Laravel when no symlink exists.');
        $this->line('Optional copy mode: php artisan app:ensure-storage --mirror --force');

        return self::SUCCESS;
    }
}
