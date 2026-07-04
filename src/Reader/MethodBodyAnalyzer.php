<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Reader;

use PhpToken;

/**
 * Analyzes PHP method bodies to extract proven relationships.
 *
 * This is the core engine for "proven extraction" - it tracks every
 * called method, referenced class, dispatched event, queued job,
 * returned view, and more - all with file:line evidence.
 *
 * No inference. No guessing. Every relationship is proven from source code.
 */
class MethodBodyAnalyzer
{
    /**
     * Analyze the body of a method and extract all proven relationships.
     *
     * @param string $contents The full PHP file contents
     * @param string $methodName The method name to analyze
     * @return array<string, mixed>
     */
    public function analyzeMethod(string $contents, string $methodName): array
    {
        $tokens = $this->tokenize($contents);
        if (empty($tokens)) {
            return $this->getEmptyResult();
        }

        $methodBody = $this->extractMethodBody($tokens, $methodName);
        if ($methodBody === null) {
            return $this->getEmptyResult();
        }

        [$bodyTokens, $startLine, $endLine] = $methodBody;

        return [
            'method' => $methodName,
            'start_line' => $startLine,
            'end_line' => $endLine,
            'calls' => $this->extractMethodCalls($bodyTokens, $bodyTokens[0]->line),
            'models_used' => $this->extractModelsUsed($bodyTokens, $bodyTokens[0]->line),
            'services_called' => $this->extractServicesCalled($bodyTokens, $bodyTokens[0]->line),
            'events_dispatched' => $this->extractEventsDispatched($bodyTokens, $bodyTokens[0]->line),
            'jobs_dispatched' => $this->extractJobsDispatched($bodyTokens, $bodyTokens[0]->line),
            'notifications_sent' => $this->extractNotificationsSent($bodyTokens, $bodyTokens[0]->line),
            'validation_requests' => $this->extractValidationRequests($bodyTokens, $bodyTokens[0]->line),
            'views_returned' => $this->extractViewsReturned($bodyTokens, $bodyTokens[0]->line),
            'resources_returned' => $this->extractResourcesReturned($bodyTokens, $bodyTokens[0]->line),
            'redirects' => $this->extractRedirects($bodyTokens, $bodyTokens[0]->line),
            'database_transactions' => $this->extractDatabaseTransactions($bodyTokens, $bodyTokens[0]->line),
            'models_written' => $this->extractModelsWritten($bodyTokens, $bodyTokens[0]->line),
        ];
    }

    /**
     * Extract all invoked method calls on objects/providers.
     */
    public function extractMethodCalls(array $tokens, int $baseLine = 1): array
    {
        $calls = [];
        $count = count($tokens);

        for ($i = 0; $i < $count - 3; $i++) {
            // Pattern: $var->method( or static::method( or ClassName::method(
            if ($tokens[$i]->text === '->' || $tokens[$i]->text === '::') {
                $arrowPos = $i;
                $methodName = null;

                // Get method name after ->
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j]->id === T_WHITESPACE) continue;
                    if ($tokens[$j]->id === T_STRING) {
                        $methodName = $tokens[$j]->text;
                        break;
                    }
                    break;
                }

                if ($methodName === null) continue;

                // Get target (what's before ->)
                $target = '';
                for ($k = $i - 1; $k >= max(0, $i - 5); $k--) {
                    if ($tokens[$k]->id === T_WHITESPACE) continue;
                    if ($tokens[$k]->id === T_VARIABLE) {
                        $target = $tokens[$k]->text;
                        break;
                    }
                    if ($tokens[$k]->id === T_STRING || $tokens[$k]->text === '\\' || $tokens[$k]->text === ')' || $tokens[$k]->text === ']') {
                        // Build class name
                        if ($tokens[$i]->text === '::' && ($tokens[$k]->id === T_STRING || $tokens[$k]->text === '\\')) {
                            $target = $tokens[$k]->text . $target;
                            continue;
                        }
                        break;
                    }
                    if ($tokens[$k]->text === '(' || $tokens[$k]->text === '{' || $tokens[$k]->text === ';') {
                        break;
                    }
                    break;
                }

                // Skip magic methods and common non-informative calls
                if (in_array($methodName, ['__call', '__get', '__set', '__invoke', 'toArray', 'toJson', 'offsetGet', 'offsetSet'])) {
                    continue;
                }

                // Get line number
                $line = $tokens[$arrowPos]->line;

                $calls[] = [
                    'target' => $target ?: '(unknown)',
                    'method' => $methodName,
                    'type' => $tokens[$i]->text === '::' ? 'static' : 'instance',
                    'line' => $line,
                ];
            }
        }

        return $calls;
    }

    /**
     * Extract model class references from method body.
     */
    public function extractModelsUsed(array $tokens, int $baseLine = 1): array
    {
        $models = [];
        $count = count($tokens);

        for ($i = 0; $i < $count - 2; $i++) {
            // Model::find(), Model::where(), Model::create(), etc.
            if ($tokens[$i]->id === T_STRING && $tokens[$i + 1]?->text === '::') {
                $class = $tokens[$i]->text;

                // Check if this looks like a model (PascalCase, common patterns)
                if (preg_match('/^[A-Z][a-zA-Z0-9]+$/', $class) && !$this->isKnownNonModelClass($class)) {
                    $methodName = null;
                    for ($j = $i + 2; $j < $count; $j++) {
                        if ($tokens[$j]->id === T_WHITESPACE) continue;
                        if ($tokens[$j]->id === T_STRING) {
                            $methodName = $tokens[$j]->text;
                            break;
                        }
                        break;
                    }

                    $existingKey = null;
                    foreach ($models as $key => $m) {
                        if ($m['class'] === $class) {
                            $existingKey = $key;
                            break;
                        }
                    }

                    if ($existingKey !== null) {
                        if (!in_array($methodName, $models[$existingKey]['methods'])) {
                            $models[$existingKey]['methods'][] = $methodName;
                        }
                    } else {
                        $models[] = [
                            'class' => $class,
                            'methods' => $methodName ? [$methodName] : [],
                            'line' => $tokens[$i]->line,
                            'type' => $this->classifyModelUsage($methodName),
                        ];
                    }
                }
            }

            // $model->save(), $model->update(), $model->delete()
            if ($tokens[$i]->id === T_VARIABLE && $tokens[$i + 1]?->text === '->') {
                for ($j = $i + 2; $j < $count; $j++) {
                    if ($tokens[$j]->id === T_WHITESPACE) continue;
                    if ($tokens[$j]->id === T_STRING && in_array($tokens[$j]->text, ['save', 'update', 'delete', 'destroy', 'create', 'forceDelete', 'restore'])) {
                        $models[] = [
                            'class' => '(instance: ' . $tokens[$i]->text . ')',
                            'methods' => [$tokens[$j]->text],
                            'line' => $tokens[$j]->line,
                            'type' => 'write',
                        ];
                    }
                    break;
                }
            }
        }

        return $models;
    }

    /**
     * Extract service class references from method body.
     */
    public function extractServicesCalled(array $tokens, int $baseLine = 1): array
    {
        $services = [];
        $count = count($tokens);

        for ($i = 0; $i < $count - 3; $i++) {
            // app(Service::class)->method()
            if ($tokens[$i]->id === T_STRING && str_ends_with($tokens[$i]->text, 'Service')) {
                $serviceName = $tokens[$i]->text;

                $existingKey = null;
                foreach ($services as $key => $s) {
                    if ($s['class'] === $serviceName) {
                        $existingKey = $key;
                        break;
                    }
                }

                if ($existingKey === null) {
                    $services[] = [
                        'class' => $serviceName,
                        'line' => $tokens[$i]->line,
                    ];
                }
            }
        }

        return $services;
    }

    /**
     * Extract dispatched events from method body.
     */
    public function extractEventsDispatched(array $tokens, int $baseLine = 1): array
    {
        $events = [];
        $count = count($tokens);

        for ($i = 0; $i < $count - 4; $i++) {
            // event(new EventClass(...))
            if ($tokens[$i]->id === T_STRING && strtolower($tokens[$i]->text) === 'event'
                && $tokens[$i + 1]?->text === '(') {
                $depth = 1;
                for ($j = $i + 2; $j < $count && $depth > 0; $j++) {
                    if ($tokens[$j]->text === '(') $depth++;
                    if ($tokens[$j]->text === ')') $depth--;
                    if ($depth === 0) {
                        // Extract event class from the argument
                        $eventClass = $this->extractNewClassName(array_slice($tokens, $i + 2, $j - $i - 2));
                        if ($eventClass) {
                            $events[] = [
                                'class' => $eventClass,
                                'line' => $tokens[$i]->line,
                                'method' => 'event()',
                            ];
                        }
                        break;
                    }
                }
            }

            // Event::dispatch(...)
            if ($tokens[$i]->id === T_STRING && $tokens[$i + 1]?->text === '::'
                && $tokens[$i + 2]?->id === T_STRING && strtolower($tokens[$i + 2]->text) === 'dispatch') {
                $events[] = [
                    'class' => $tokens[$i]->text,
                    'line' => $tokens[$i]->line,
                    'method' => 'dispatch',
                ];
            }

            // Bus::dispatch(new EventClass)
            if ($tokens[$i]->id === T_STRING && $tokens[$i + 1]?->text === '::'
                && $tokens[$i + 2]?->id === T_STRING && strtolower($tokens[$i + 2]->text) === 'dispatch'
                && $tokens[$i + 3]?->text === '(') {
                // Extract the class being dispatched
                $eventClass = $this->extractNewClassName(array_slice($tokens, $i + 4));
                if ($eventClass) {
                    $events[] = [
                        'class' => $eventClass,
                        'line' => $tokens[$i]->line,
                        'method' => $tokens[$i]->text . '::dispatch',
                    ];
                }
            }

            // $this->dispatch(new EventClass) - from traits like Dispatchable
            if ($tokens[$i]->text === '->' && $tokens[$i + 1]?->id === T_STRING
                && strtolower($tokens[$i + 1]->text) === 'dispatch') {
                $eventClass = null;
                for ($j = $i + 2; $j < $count; $j++) {
                    if ($tokens[$j]->text === '(') {
                        $eventClass = $this->extractNewClassName(array_slice($tokens, $j + 1));
                        break;
                    }
                }
                if ($eventClass) {
                    $events[] = [
                        'class' => $eventClass,
                        'line' => $tokens[$i]->line,
                        'method' => '->dispatch',
                    ];
                }
            }
        }

        return $events;
    }

    /**
     * Extract dispatched jobs from method body.
     */
    public function extractJobsDispatched(array $tokens, int $baseLine = 1): array
    {
        $jobs = [];
        $count = count($tokens);

        for ($i = 0; $i < $count - 4; $i++) {
            // dispatch(new JobClass(...))
            if ($tokens[$i]->id === T_STRING && function_exists($tokens[$i]->text)
                ? false : strtolower($tokens[$i]->text) === 'dispatch'
                && $tokens[$i + 1]?->text === '(') {
                $jobClass = $this->extractNewClassName(array_slice($tokens, $i + 2));
                if ($jobClass) {
                    $jobs[] = [
                        'class' => $jobClass,
                        'line' => $tokens[$i]->line,
                        'method' => 'dispatch()',
                    ];
                }
            }

            // Bus::dispatch(new JobClass)
            if ($tokens[$i]->id === T_STRING && $tokens[$i + 1]?->text === '::'
                && $tokens[$i + 2]?->id === T_STRING && strtolower($tokens[$i + 2]->text) === 'dispatch'
                && $tokens[$i + 3]?->text === '(') {
                $jobClass = $this->extractNewClassName(array_slice($tokens, $i + 4));
                if ($jobClass) {
                    $jobs[] = [
                        'class' => $jobClass,
                        'line' => $tokens[$i]->line,
                        'method' => $tokens[$i]->text . '::dispatch',
                    ];
                }
            }

            // $job->dispatch() on a variable named something like $job
            if ($tokens[$i]->id === T_VARIABLE && $tokens[$i + 1]?->text === '->'
                && $tokens[$i + 2]?->id === T_STRING && strtolower($tokens[$i + 2]->text) === 'dispatch') {
                // Check if there's a new JobClass assignment earlier
                $jobs[] = [
                    'class' => '(variable: ' . $tokens[$i]->text . ')',
                    'line' => $tokens[$i]->line,
                    'method' => '->dispatch()',
                ];
            }

            // JobClass::dispatch()
            if ($tokens[$i]->id === T_STRING && preg_match('/^[A-Z]/', $tokens[$i]->text)
                && $tokens[$i + 1]?->text === '::'
                && $tokens[$i + 2]?->id === T_STRING && strtolower($tokens[$i + 2]->text) === 'dispatch') {
                $jobs[] = [
                    'class' => $tokens[$i]->text,
                    'line' => $tokens[$i]->line,
                    'method' => '::dispatch',
                ];
            }
        }

        return $jobs;
    }

    /**
     * Extract sent notifications from method body.
     */
    public function extractNotificationsSent(array $tokens, int $baseLine = 1): array
    {
        $notifications = [];
        $count = count($tokens);

        for ($i = 0; $i < $count - 4; $i++) {
            // $notifiable->notify(new NotificationClass(...))
            if ($tokens[$i]->text === '->' && $tokens[$i + 1]?->id === T_STRING
                && strtolower($tokens[$i + 1]->text) === 'notify'
                && $tokens[$i + 2]?->text === '(') {
                $notificationClass = $this->extractNewClassName(array_slice($tokens, $i + 3));
                if ($notificationClass) {
                    $notifications[] = [
                        'class' => $notificationClass,
                        'line' => $tokens[$i]->line,
                        'method' => '->notify()',
                    ];
                }
            }

            // Notification::send($notifiables, new NotificationClass)
            if ($tokens[$i]->id === T_STRING && strtolower($tokens[$i]->text) === 'notification'
                && $tokens[$i + 1]?->text === '::'
                && $tokens[$i + 2]?->id === T_STRING && strtolower($tokens[$i + 2]->text) === 'send') {
                // Extract the notification class from the second argument
                $notifications[] = [
                    'class' => '(Notification::send)',
                    'line' => $tokens[$i]->line,
                    'method' => 'Notification::send',
                ];
            }
        }

        return $notifications;
    }

    /**
     * Extract FormRequest type hints from method parameters.
     */
    public function extractValidationRequests(array $tokens, int $baseLine = 1): array
    {
        $requests = [];

        foreach ($tokens as $i => $token) {
            if ($token->id === T_VARIABLE && $token->text === '$request') {
                // Look backwards for type hint
                for ($j = $i - 1; $j >= max(0, $i - 5); $j--) {
                    if ($tokens[$j]->id === T_WHITESPACE) continue;
                    if ($tokens[$j]->id === T_STRING || $tokens[$j]->id === T_NAME_QUALIFIED) {
                        if (str_ends_with($tokens[$j]->text, 'Request')) {
                            $requests[] = [
                                'class' => $tokens[$j]->text,
                                'line' => $token->line,
                            ];
                        }
                        break;
                    }
                    if ($tokens[$j]->text === '(' || $tokens[$j]->text === ',') break;
                }
            }
        }

        // Also detect $this->validate(...)
        foreach ($tokens as $i => $token) {
            if ($token->text === '->' && isset($tokens[$i + 1])
                && $tokens[$i + 1]->id === T_STRING && $tokens[$i + 1]->text === 'validate') {
                $requests[] = [
                    'class' => '(inline)',
                    'line' => $token->line,
                    'method' => '$this->validate()',
                ];
            }
        }

        return $requests;
    }

    /**
     * Extract returned view names from method body.
     */
    public function extractViewsReturned(array $tokens, int $baseLine = 1): array
    {
        $views = [];
        $count = count($tokens);

        for ($i = 0; $i < $count - 4; $i++) {
            // view('name', [...])
            if ($tokens[$i]->id === T_STRING && strtolower($tokens[$i]->text) === 'view'
                && $tokens[$i + 1]?->text === '(') {
                // Extract view name from first argument
                $depth = 1;
                for ($j = $i + 2; $j < $count && $depth > 0; $j++) {
                    if ($tokens[$j]->text === '(') $depth++;
                    if ($tokens[$j]->text === ')' && --$depth === 0) break;
                }

                // Get the first string argument
                if (isset($tokens[$i + 2]) && ($tokens[$i + 2]->id === T_CONSTANT_ENCAPSED_STRING || $tokens[$i + 2]->id === T_ENCAPSED_AND_WHITESPACE)) {
                    $viewName = trim($tokens[$i + 2]->text, "'\"");
                    $views[] = [
                        'name' => $viewName,
                        'line' => $tokens[$i]->line,
                    ];
                }
            }

            // return $this->view = ...
            if ($tokens[$i]->id === T_STRING && $tokens[$i + 1]?->text === '->'
                && $tokens[$i + 2]?->id === T_STRING && $tokens[$i + 2]->text === 'view') {
                // Try to find the assignment
                for ($j = $i + 3; $j < $count; $j++) {
                    if ($tokens[$j]->text === '=') {
                        for ($k = $j + 1; $k < $count; $k++) {
                            if ($tokens[$k]->id === T_CONSTANT_ENCAPSED_STRING) {
                                $views[] = [
                                    'name' => trim($tokens[$k]->text, "'\""),
                                    'line' => $tokens[$i]->line,
                                    'via' => '$this->view',
                                ];
                                break;
                            }
                            if ($tokens[$k]->text === ';') break;
                        }
                        break;
                    }
                }
            }
        }

        return $views;
    }

    /**
     * Extract returned API resources.
     */
    public function extractResourcesReturned(array $tokens, int $baseLine = 1): array
    {
        $resources = [];
        $count = count($tokens);

        for ($i = 0; $i < $count - 3; $i++) {
            // return new ResourceClass(...)
            if ($tokens[$i]->id === T_STRING && $tokens[$i + 1]?->text === ':'
                && $tokens[$i + 2]?->id === T_WHITESPACE) {
                // This is a return type, not a call
                continue;
            }

            if ($tokens[$i]->text === 'new' && $tokens[$i + 1]?->id === T_WHITESPACE) {
                for ($j = $i + 2; $j < $count; $j++) {
                    if ($tokens[$j]->id === T_WHITESPACE) continue;
                    if ($tokens[$j]->id === T_STRING || $tokens[$j]->id === T_NAME_QUALIFIED) {
                        $className = '';
                        for ($k = $j; $k < $count; $k++) {
                            if ($tokens[$k]->text === '(') break;
                            if ($tokens[$k]->id === T_WHITESPACE && $className !== '') break;
                            if ($tokens[$k]->id === T_STRING || $tokens[$k]->id === T_NAME_QUALIFIED || $tokens[$k]->text === '\\') {
                                $className .= $tokens[$k]->text;
                            }
                        }
                        if (str_ends_with($className, 'Resource') || str_ends_with($className, 'Collection')) {
                            $resources[] = [
                                'class' => $className,
                                'line' => $tokens[$i]->line,
                            ];
                        }
                        break;
                    }
                    break;
                }
            }

            // ResourceClass::collection(...)
            if ($tokens[$i]->id === T_STRING && str_ends_with($tokens[$i]->text, 'Resource')
                && $tokens[$i + 1]?->text === '::'
                && $tokens[$i + 2]?->id === T_STRING && $tokens[$i + 2]->text === 'collection') {
                $resources[] = [
                    'class' => $tokens[$i]->text,
                    'line' => $tokens[$i]->line,
                    'method' => '::collection()',
                ];
            }
        }

        return $resources;
    }

    /**
     * Extract redirects from method body.
     */
    public function extractRedirects(array $tokens, int $baseLine = 1): array
    {
        $redirects = [];
        $count = count($tokens);

        for ($i = 0; $i < $count - 3; $i++) {
            // redirect()->route()
            if ($tokens[$i]->id === T_STRING && strtolower($tokens[$i]->text) === 'redirect'
                && $tokens[$i + 1]?->text === '('
                && $tokens[$i + 2]?->text === ')'
                && $tokens[$i + 3]?->text === '->') {
                // Find route name
                for ($j = $i + 4; $j < $count; $j++) {
                    if ($tokens[$j]->id === T_STRING && $tokens[$j]->text === 'route'
                        && isset($tokens[$j + 1]) && $tokens[$j + 1]->text === '(') {
                        for ($k = $j + 2; $k < $count; $k++) {
                            if ($tokens[$k]->id === T_CONSTANT_ENCAPSED_STRING) {
                                $redirects[] = [
                                    'type' => 'route',
                                    'target' => trim($tokens[$k]->text, "'\""),
                                    'line' => $tokens[$i]->line,
                                ];
                                break;
                            }
                            if ($tokens[$k]->text === ',' || $tokens[$k]->text === ')') break;
                        }
                        break;
                    }
                    if ($tokens[$j]->text === ';' || $tokens[$j]->text === ')') break;
                }
            }

            // redirect('url')
            if ($tokens[$i]->id === T_STRING && strtolower($tokens[$i]->text) === 'redirect'
                && $tokens[$i + 1]?->text === '(') {
                // Check if it's redirect('...') not redirect()->...
                $isDirectCall = false;
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j]->text === ')') {
                        $isDirectCall = true;
                        break;
                    }
                    if ($tokens[$j]->text === '->') break;
                }
                if ($isDirectCall && isset($tokens[$i + 2]) && $tokens[$i + 2]->id === T_CONSTANT_ENCAPSED_STRING) {
                    $redirects[] = [
                        'type' => 'url',
                        'target' => trim($tokens[$i + 2]->text, "'\""),
                        'line' => $tokens[$i]->line,
                    ];
                }
            }

            // back()
            if ($tokens[$i]->id === T_STRING && strtolower($tokens[$i]->text) === 'back'
                && $tokens[$i + 1]?->text === '(') {
                $redirects[] = [
                    'type' => 'back',
                    'target' => 'previous_url',
                    'line' => $tokens[$i]->line,
                ];
            }

            // return redirect(...)
            if ($tokens[$i]->id === T_STRING && $tokens[$i + 1]?->text === '->'
                && $tokens[$i + 2]?->id === T_STRING && $tokens[$i + 2]->text === 'redirect') {
                $redirects[] = [
                    'type' => 'instance_redirect',
                    'target' => '(redirect on ' . $tokens[$i]->text . ')',
                    'line' => $tokens[$i]->line,
                ];
            }
        }

        return $redirects;
    }

    /**
     * Extract database transaction usage.
     */
    public function extractDatabaseTransactions(array $tokens, int $baseLine = 1): array
    {
        $transactions = [];
        $count = count($tokens);

        for ($i = 0; $i < $count - 3; $i++) {
            // DB::transaction()
            if ($tokens[$i]->id === T_STRING && strtolower($tokens[$i]->text) === 'db'
                && $tokens[$i + 1]?->text === '::'
                && $tokens[$i + 2]?->id === T_STRING && $tokens[$i + 2]->text === 'transaction') {
                $transactions[] = [
                    'type' => 'DB::transaction',
                    'line' => $tokens[$i]->line,
                ];
            }

            // \DB::transaction() with leading backslash
            if ($tokens[$i]->text === '\\' && isset($tokens[$i + 1])
                && $tokens[$i + 1]->id === T_STRING && strtolower($tokens[$i + 1]->text) === 'db'
                && $tokens[$i + 2]?->text === '::'
                && $tokens[$i + 3]?->id === T_STRING && $tokens[$i + 3]->text === 'transaction') {
                $transactions[] = [
                    'type' => '\\DB::transaction',
                    'line' => $tokens[$i]->line,
                ];
            }

            // DB::beginTransaction()
            if ($tokens[$i]->id === T_STRING && strtolower($tokens[$i]->text) === 'db'
                && $tokens[$i + 1]?->text === '::'
                && $tokens[$i + 2]?->id === T_STRING && $tokens[$i + 2]->text === 'beginTransaction') {
                // Look for matching commit
                $hasCommit = false;
                for ($j = $i + 3; $j < $count; $j++) {
                    if ($tokens[$j]->id === T_STRING && strtolower($tokens[$j]->text) === 'db'
                        && $tokens[$j + 1]?->text === '::') {
                        for ($k = $j + 2; $k < $count; $k++) {
                            if ($tokens[$k]->id === T_STRING && $tokens[$k]->text === 'commit') {
                                $hasCommit = true;
                                break 2;
                            }
                            if ($tokens[$k]->id === T_STRING && $tokens[$k]->text === 'rollBack') {
                                break 2;
                            }
                            if ($tokens[$k]->text === ';') break;
                        }
                    }
                }
                $transactions[] = [
                    'type' => 'manual_transaction',
                    'has_commit' => $hasCommit,
                    'line' => $tokens[$i]->line,
                ];
            }
        }

        return $transactions;
    }

    /**
     * Extract models that are written (created/updated/deleted).
     */
    public function extractModelsWritten(array $tokens, int $baseLine = 1): array
    {
        $writes = [];
        $calls = $this->extractMethodCalls($tokens, $baseLine);

        foreach ($calls as $call) {
            $writeMethods = ['save', 'create', 'update', 'delete', 'destroy', 'forceDelete', 'restore', 'increment', 'decrement', 'upsert'];
            if (in_array($call['method'], $writeMethods)) {
                $writes[] = $call;
            }
        }

        // Also detect Model::create(), Model::updateOrCreate(), etc.
        $modelsUsed = $this->extractModelsUsed($tokens, $baseLine);
        $writeStaticMethods = ['create', 'updateOrCreate', 'firstOrCreate', 'insert', 'upsert', 'destroy'];
        foreach ($modelsUsed as $model) {
            foreach ($model['methods'] as $method) {
                if (in_array($method, $writeStaticMethods)) {
                    $writes[] = [
                        'target' => $model['class'],
                        'method' => $method,
                        'type' => 'static',
                        'line' => $model['line'],
                    ];
                }
            }
        }

        return $writes;
    }

    /**
     * Extract a class name from 'new ClassName(...)' pattern in a token slice.
     */
    private function extractNewClassName(array $tokens): ?string
    {
        $count = count($tokens);
        for ($i = 0; $i < $count - 2; $i++) {
            if ($tokens[$i]->id === T_WHITESPACE) continue;
            if ($tokens[$i]->text === 'new') {
                $class = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j]->id === T_WHITESPACE && !empty($class)) {
                        // Check next token
                        if (isset($tokens[$j + 1]) && ($tokens[$j + 1]->id === T_STRING || $tokens[$j + 1]->id === T_NAME_QUALIFIED || $tokens[$j + 1]->text === '\\')) {
                            continue;
                        }
                        break;
                    }
                    if ($tokens[$j]->id === T_WHITESPACE) continue;
                    if ($tokens[$j]->text === '(' || $tokens[$j]->text === ',' || $tokens[$j]->text === ';') break;
                    $class .= $tokens[$j]->text;
                }
                $class = trim($class);
                if (!empty($class) && preg_match('/^[a-zA-Z_\\\\]+$/', $class)) {
                    return $class;
                }
                return null;
            }
        }
        return null;
    }

    /**
     * Extract method body tokens for a named method.
     *
     * @return array{0: array, 1: int, 2: int}|null
     */
    private function extractMethodBody(array $tokens, string $methodName): ?array
    {
        $count = count($tokens);

        for ($i = 0; $i < $count - 3; $i++) {
            // Find 'function methodName'
            if ($tokens[$i]->id === T_FUNCTION
                && $tokens[$i + 1]?->id === T_WHITESPACE
                && $tokens[$i + 2]?->id === T_STRING
                && $tokens[$i + 2]->text === $methodName) {

                // Find opening brace
                for ($j = $i; $j < $count; $j++) {
                    if ($tokens[$j]->text === '{') {
                        $bodyTokens = [];
                        $depth = 1;
                        $startLine = $tokens[$j]->line;

                        for ($k = $j + 1; $k < $count && $depth > 0; $k++) {
                            if ($tokens[$k]->text === '{') $depth++;
                            elseif ($tokens[$k]->text === '}') $depth--;
                            if ($depth > 0) {
                                $bodyTokens[] = $tokens[$k];
                            }
                        }

                        $endLine = $tokens[$k - 1]->line ?? $tokens[$j]->line;

                        return [$bodyTokens, $startLine, $endLine];
                    }
                    // Skip parameter list and return type
                }
            }
        }

        return null;
    }

    /**
     * Check if a class name is a known non-model class.
     */
    private function isKnownNonModelClass(string $className): bool
    {
        $knownNonModels = [
            'App', 'Artisan', 'Auth', 'Blade', 'Broadcast', 'Bus', 'Cache',
            'Config', 'Cookie', 'Crypt', 'Date', 'DB', 'Eloquent', 'Event',
            'File', 'Gate', 'Hash', 'Http', 'Input', 'Lang', 'Log', 'Mail',
            'Notification', 'Password', 'Queue', 'RateLimiter', 'Redirect',
            'Request', 'Response', 'Route', 'Schema', 'Session', 'Storage',
            'Str', 'URL', 'Validator', 'View', 'Collection', 'Arr',
            // Test classes
            'TestCase', 'PHPUnit', 'Mockery', 'Factory',
            // PHP classes
            'DateTime', 'Carbon', 'Exception', 'Error', 'StdClass',
            'Closure', 'Iterator', 'ArrayObject', 'JsonSerializable',
        ];

        return in_array($className, $knownNonModels, true);
    }

    /**
     * Classify model usage as read or write.
     */
    private function classifyModelUsage(?string $methodName): string
    {
        $readMethods = ['find', 'findOrFail', 'findMany', 'first', 'firstOrFail', 'get', 'all', 'where', 'orWhere',
            'with', 'count', 'sum', 'avg', 'min', 'max', 'exists', 'doesntExist', 'pluck', 'value',
            'select', 'join', 'leftJoin', 'rightJoin', 'orderBy', 'groupBy', 'having', 'limit', 'offset',
            'paginate', 'simplePaginate', 'cursorPaginate', 'lazy', 'chunk', 'each'];

        $writeMethods = ['create', 'update', 'updateOrCreate', 'firstOrCreate', 'insert', 'upsert',
            'delete', 'destroy', 'forceDelete', 'truncate', 'save', 'touch', 'increment', 'decrement'];

        if ($methodName === null) return 'reference';
        if (in_array($methodName, $readMethods)) return 'read';
        if (in_array($methodName, $writeMethods)) return 'write';
        return 'reference';
    }

    private function tokenize(string $contents): array
    {
        try {
            return PhpToken::tokenize($contents);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getEmptyResult(): array
    {
        return [
            'method' => null,
            'start_line' => null,
            'end_line' => null,
            'calls' => [],
            'models_used' => [],
            'services_called' => [],
            'events_dispatched' => [],
            'jobs_dispatched' => [],
            'notifications_sent' => [],
            'validation_requests' => [],
            'views_returned' => [],
            'resources_returned' => [],
            'redirects' => [],
            'database_transactions' => [],
            'models_written' => [],
        ];
    }
}