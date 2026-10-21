<?php

namespace App\Console\Commands;

use App\Support\PublicStorageLinker;
use Illuminate\Foundation\Console\StorageLinkCommand as LaravelStorageLinkCommand;

/**
 * Same as Laravel storage:link but never calls exec() (disabled on many hosts / Windows).
 */
class StorageLinkCommand extends LaravelStorageLinkCommand
{
    public function handle()
    {
        PublicStorageLinker::ensureDirectories();

        $relative = (bool) $this->option('relative');
        $force = (bool) $this->option('force');

        foreach ($this->links() as $link => $target) {
            $result = PublicStorageLinker::link($link, $target, $relative, $force);

            if ($result['message'] === 'already_linked') {
                $this->components->info("The [$link] link already points to [$target].");

                continue;
            }

            if ($result['ok']) {
                $this->components->info("The [$link] link has been connected to [$target].");

                continue;
            }

            if ($result['message'] === 'exists') {
                $this->components->error("The [$link] link already exists. Use --force to replace it.");

                continue;
            }

            $this->components->error("Could not create the link [$link].");
            if ($result['shell_hint']) {
                $this->line('Try manually:');
                $this->comment('  '.$result['shell_hint']);
            }
        }
    }
}
