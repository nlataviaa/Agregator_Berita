<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan as ArtisanFacade;

class Berita extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'berita {action?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wrapper command for berita subcommands (e.g. sync-smkn)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        if (!$action) {
            $this->info('Available subcommands: sync-smkn');
            return 0;
        }

        if ($action === 'sync-smkn') {
            $exit = ArtisanFacade::call('berita:sync-smkn');
            $this->info('Executed berita:sync-smkn (exit code: ' . $exit . ')');
            return $exit;
        }

        $this->error('Unknown subcommand: ' . $action);
        return 1;
    }
}
