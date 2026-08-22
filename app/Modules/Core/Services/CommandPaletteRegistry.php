<?php

namespace App\Modules\Core\Services;

class CommandPaletteRegistry
{
    protected array $providers = [];
    protected array $macros = [];
    protected array $actions = [];

    /**
     * Register a search provider (e.g., Tasks, Customers, DomainEvents)
     */
    public function registerProvider(string $key, callable $handler)
    {
        $this->providers[$key] = $handler;
    }

    /**
     * Register a global macro (e.g., "/daily")
     */
    public function registerMacro(string $macro, callable $handler)
    {
        $this->macros[$macro] = $handler;
    }

    /**
     * Register a system command (e.g., "> clear cache")
     */
    public function registerAction(string $command, callable $handler)
    {
        $this->actions[$command] = $handler;
    }

    public function getProviders(): array
    {
        return $this->providers;
    }

    public function search(string $query, array $context = []): array
    {
        $results = [];
        
        // Handle Macros
        if (str_starts_with($query, '/')) {
            $macroKey = explode(' ', $query)[0];
            if (isset($this->macros[$macroKey])) {
                return call_user_func($this->macros[$macroKey], $query, $context);
            }
        }
        
        // Handle Actions
        if (str_starts_with($query, '>')) {
            $actionKey = trim(str_replace('>', '', $query));
            // Basic matching for scaffold
            foreach ($this->actions as $command => $handler) {
                if (str_contains(strtolower($command), strtolower($actionKey))) {
                    $results[] = [
                        'type' => 'Action',
                        'title' => $command,
                        'icon' => 'heroicon-m-command-line',
                        'action' => 'execute',
                        'command' => $command
                    ];
                }
            }
            return $results;
        }

        // Standard Search
        foreach ($this->providers as $key => $handler) {
            $providerResults = call_user_func($handler, $query, $context);
            $results = array_merge($results, $providerResults);
        }

        return $this->rankResults($results, $query, $context);
    }

    protected function rankResults(array $results, string $query, array $context): array
    {
        // Scaffold implementation: In production, rank exact UUID/Correlation IDs higher
        return collect($results)->sortByDesc(function ($result) use ($query) {
            $score = 0;
            // Exact UUID or Correlation ID match gets massive boost
            if (isset($result['uuid']) && $result['uuid'] === $query) {
                $score += 1000;
            }
            // Context awareness boost (e.g., if viewing conversation, boost related tasks)
            if (isset($context['conversation_id']) && isset($result['conversation_id']) && $context['conversation_id'] === $result['conversation_id']) {
                $score += 500;
            }
            return $score;
        })->take(15)->values()->toArray();
    }
}
