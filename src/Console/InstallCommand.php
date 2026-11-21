<?php

namespace IkongDev\RestApi\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'restapi:install';
    protected $description = 'Prepare the app for IkongDev REST API';

    public function handle(): int
    {
        if (! file_exists(base_path('routes/api.php'))) {
            $this->call('install:api', ['--no-interaction' => true]);
        }

        $this->call('vendor:publish', ['--tag' => 'restapi-config']);

        $this->info('Done. Define your endpoints in routes/api.php.');

        return self::SUCCESS;
    }
}