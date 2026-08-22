<?php

namespace Modules\Rules\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Modules\Rules\Models\BusinessRule;
use Modules\Rules\Models\RuleExecution;
use Modules\Rules\Contracts\ActionInterface;

class RulesEngine
{
    protected array $actionProviders = [];

    public function __construct()
    {
        // In real app, inject via service provider. Hardcoded for scaffold.
        $this->registerActionProvider(new \Modules\Rules\Providers\NotificationActionProvider());
    }

    public function registerActionProvider(ActionInterface $provider): void
    {
        $this->actionProviders[$provider->getName()] = $provider;
    }

    public function evaluateEvent(string $eventName, array $context, bool $isSimulation = false): array
    {
        $rules = BusinessRule::where('event_name', $eventName)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        $report = [
            'event' => $eventName,
            'is_simulation' => $isSimulation,
            'rules_evaluated' => [],
        ];

        foreach ($rules as $rule) {
            $startedAt = now();
            $isMatch = $this->evaluateConditions($rule->conditions, $context);
            
            $ruleReport = [
                'rule_id' => $rule->id,
                'name' => $rule->name,
                'matched' => $isMatch,
                'actions_dispatched' => [],
                'skipped' => false,
            ];

            if ($isMatch) {
                try {
                    $ruleReport['actions_dispatched'] = $this->dispatchActions($rule->actions, $context, $isSimulation);
                    
                    if (!$isSimulation) {
                        RuleExecution::create([
                            'rule_id' => $rule->id,
                            'event_name' => $eventName,
                            'context_snapshot' => $context,
                            'started_at' => $startedAt,
                            'finished_at' => now(),
                            'is_success' => true,
                            'is_simulation' => false,
                        ]);
                    }
                } catch (\Exception $e) {
                    if (!$isSimulation) {
                        RuleExecution::create([
                            'rule_id' => $rule->id,
                            'event_name' => $eventName,
                            'context_snapshot' => $context,
                            'started_at' => $startedAt,
                            'finished_at' => now(),
                            'is_success' => false,
                            'error_message' => $e->getMessage(),
                            'is_simulation' => false,
                        ]);
                    }
                    Log::error("Rule Execution Failed for Rule ID {$rule->id}: " . $e->getMessage());
                }

                $report['rules_evaluated'][] = $ruleReport;

                if ($rule->stop_processing) {
                    $report['halted_by'] = $rule->id;
                    break;
                }
            } else {
                $report['rules_evaluated'][] = $ruleReport;
            }
        }

        return $report;
    }

    public function simulateEvent(string $eventName, array $context): array
    {
        return $this->evaluateEvent($eventName, $context, true);
    }

    /**
     * Evaluate a rule's `conditions` JSON against the event `$context`.
     *
     * Supported shapes:
     *  - []                                                     → always matches (unconditional rule)
     *  - ['AND' => [<condition>, <condition>, ...]]              → all sub-conditions must match
     *  - ['OR'  => [<condition>, <condition>, ...]]              → any sub-condition must match
     *  - ['field' => 'task.priority', 'operator' => '=', 'value' => 'urgent']
     *      → dot-notation lookup into $context, compared with `operator`
     */
    protected function evaluateConditions(array $conditions, array $context): bool
    {
        if (empty($conditions)) {
            return true;
        }

        if (isset($conditions['AND']) && is_array($conditions['AND'])) {
            foreach ($conditions['AND'] as $sub) {
                if (! $this->evaluateConditions((array) $sub, $context)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($conditions['OR']) && is_array($conditions['OR'])) {
            foreach ($conditions['OR'] as $sub) {
                if ($this->evaluateConditions((array) $sub, $context)) {
                    return true;
                }
            }

            return false;
        }

        if (array_key_exists('field', $conditions)) {
            $actual = Arr::get($context, $conditions['field']);
            $operator = $conditions['operator'] ?? '=';
            $expected = $conditions['value'] ?? null;

            return $this->compare($actual, $operator, $expected);
        }

        // Unrecognized condition shape: fail closed (don't match) instead of
        // silently pretending every rule always matches.
        Log::warning('RulesEngine: unrecognized condition shape, treating as no-match.', [
            'conditions' => $conditions,
        ]);

        return false;
    }

    protected function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '=', '==' => $actual == $expected,
            '!=', '<>' => $actual != $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            'contains' => is_string($actual) && is_string($expected)
                && str_contains(strtolower($actual), strtolower($expected)),
            'in' => is_array($expected) && in_array($actual, $expected, false),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, false),
            default => false,
        };
    }

    protected function dispatchActions(array $actionsConfig, array $context, bool $isSimulation): array
    {
        $executed = [];
        foreach ($actionsConfig as $actionConfig) {
            $type = $actionConfig['type'] ?? null;
            if (!$type || !isset($this->actionProviders[$type])) {
                Log::warning("Unknown action provider type: {$type}");
                continue;
            }

            /** @var ActionInterface $provider */
            $provider = $this->actionProviders[$type];
            $provider->execute($actionConfig, $context, $isSimulation);
            $executed[] = $type;
        }
        return $executed;
    }
}
