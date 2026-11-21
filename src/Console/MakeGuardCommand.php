<?php

namespace IkongDev\RestApi\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeGuardCommand extends GeneratorCommand
{
    protected $name = 'make:restapi-guard';
    protected $description = 'Create a new REST API exception guard';
    protected $type = 'Guard';

    public function handle()
    {
        if (parent::handle() === false) {
            return false; // class already exists
        }

        $this->registerInBootstrap($this->qualifyClass($this->getNameInput()));

        return self::SUCCESS;
    }

    protected function registerInBootstrap(string $class): void
    {
        $path = base_path('bootstrap/app.php');
        $contents = file_get_contents($path);

        if (str_contains($contents, $class . '()')) {
            $this->components->warn('Guard already registered in bootstrap/app.php.');
            return;
        }

        $line = '(new \\' . $class . '())->parse($exceptions);';
        $count = 0;

        $updated = preg_replace_callback(
            '/(->withExceptions\(function\s*\(Exceptions \$exceptions\)[^{]*\{\R)/',
            fn ($m) => $m[1] . '        ' . $line . PHP_EOL,
            $contents,
            1,
            $count
        );

        if ($count === 0) {
            $this->components->warn('Could not find withExceptions() in bootstrap/app.php.');
            $this->line('Add this inside it manually:');
            $this->line('    ' . $line);
            return;
        }

        file_put_contents($path, $updated);
        $this->components->info('Registered in bootstrap/app.php.');
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/guard.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Guards';
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        return str_replace(
            ['{{ prefix }}', '{{ type }}'],
            [$this->option('prefix') ?: 'api', $this->option('type') ?: 'JSON'],
            $stub
        );
    }

    protected function getOptions(): array
    {
        return [
            ['prefix', 'p', InputOption::VALUE_OPTIONAL, 'URL prefix to guard', 'api'],
            ['type', 't', InputOption::VALUE_OPTIONAL, 'JSON, XML, String or Plain', 'JSON'],
            ['force', null, InputOption::VALUE_NONE, 'Overwrite the guard if it already exists'],
        ];
    }
}