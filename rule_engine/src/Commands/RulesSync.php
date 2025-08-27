<?php

namespace Xpkg\RuleEngine\Commands;

use Xpkg\Arrays\Arr;
use Xpkg\Elasticsearch\ES;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Xpkg\RuleEngine\Models\RuleFacts;

class RulesSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rules:sync {--f|force} {--t|truncate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sync fields from elasticsearch to mysql';

    protected array $mapping = [];
    protected bool $keepCurrent = false;
    protected bool $truncateFacts = false;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->keepCurrent = !$this->option('force');
        $this->truncateFacts = $this->option('truncate');
        $this->getMapping()
            ->simplifyMapping()
            ->checkAllowHide()
            ->generate();
    }

    protected function getMapping(): static
    {
        $indexes = config('rules.index');
        $mapping = [];
        foreach ($indexes as $item) {
            $mapping[$item] = collect(ES::getIndexMapping($item))
                ->first()
                ->first()
                ->first()
                ->toArray();
        }
        $this->mapping = Arr::dot($mapping);
        return $this;
    }

    protected function simplifyMapping(): static
    {
        $mapping = [];
        foreach ($this->mapping as $path => $type) {
            if (!in_array($type, ['text', 'long', 'date', 'geo_point', 'boolean', 'object', 'float']) || is_bool($type)) {
                continue;
            }
            $path = str_replace(['properties.', '.type'], '', $path);
            $type = Arr::get(config('rules.casts'), $path, $type);

            if ($type === 'long') {
                $type = 'integer';
            }
            if ($type === 'object') {
                $type = 'text';
            }
            $mapping[$path] = $type;
        }
        $this->mapping = $mapping;
        return $this;
    }

    protected function checkAllowHide(): static
    {
        $allow = config('rules.only');
        $deny = config('rules.except');
        if ($allow === ['*'] && empty($deny)) {
            return $this;
        }
        if ($deny === ['*'] && empty($allow)) {
            $this->mapping = [];
            return $this;
        }
        if (!empty($deny)) {
            Arr::forget($this->mapping, $deny);
            $this->mapping = Arr::dot($this->mapping);
        }
        if (!empty($allow) && $allow !== ['*']) {
            $this->mapping = Arr::only($this->mapping, $allow);
        }
        return $this;
    }

    public function generate(): void
    {
        Schema::disableForeignKeyConstraints();
        if ($this->truncateFacts) {
            RuleFacts::truncate();
        }

        $exists = RuleFacts::select(DB::raw('CONCAT(fact,".",name) AS fullName'))->get();
        $exists = $exists->pluck('fullName')->flip();
        $insert = [];
        foreach ($this->mapping as $path => $type) {
            $path = explode('.', $path, 2);
            $fact = $path[0];
            $name = end($path);
            $fullName = "{$fact}.{$name}";
            if ($this->keepCurrent && $exists->has($fullName)) {
                continue;
            }
            if ($exists->has($fullName)) {
                RuleFacts::where('name', $name)->where('fact', $fact)->delete();
            }
            RuleFacts::create([
                'fact' => $fact,
                'name' => $name,
                'type' => $type,
            ]);
        }
        Schema::enableForeignKeyConstraints();
    }
}