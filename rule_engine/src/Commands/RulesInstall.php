<?php

namespace Xpkg\RuleEngine\Commands;

use Illuminate\Console\Command;

class RulesInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rules:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    public function handle()
    {
        $this->call('vendor:publish', ['--provider' => 'Xpkg\RuleEngine\ServiceProvider']);
        $answer = $this->ask('Do you want to run artisan:migrate now? [y/n]', 'y');
        $answer = strtolower($answer);
        if (in_array($answer, ['y', 'yes'])) {
            $this->call('migrate');
            $answer = $this->ask('Do you want to seed the database? [y/n]', 'y');
            $answer = strtolower($answer);
            if (in_array($answer, ['y', 'yes'])) {
                $this->call('db:seed', ['--class' => 'RuleFieldsSeeder']);
            }
        }
    }
}