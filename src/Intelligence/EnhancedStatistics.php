<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Generates richer project statistics beyond basic file counts.
 *
 * Metrics: Largest controllers/services/models, average sizes,
 * complexity estimates, total methods/relations/policies/requests,
 * total events/jobs, average routes per controller, largest feature,
 * most connected model.
 */
class EnhancedStatistics
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function analyze(array $data): array
    {
        // Controller statistics
        $ctrlStats = $this->analyzeControllers($data);

        // Service statistics
        $svcStats = $this->analyzeServices($data);

        // Model statistics
        $modelStats = $this->analyzeModels($data);

        // Route statistics
        $routeStats = $this->analyzeRoutes($data);

        // Feature statistics
        $featureStats = $this->analyzeFeatures($data);

        // Relationship statistics
        $relationStats = $this->analyzeRelationships($data);

        // Complexity estimate
        $complexity = $this->estimateComplexity($data);

        return [
            'enhanced_statistics' => [
                'controllers' => $ctrlStats,
                'services' => $svcStats,
                'models' => $modelStats,
                'routes' => $routeStats,
                'features' => $featureStats,
                'relationships' => $relationStats,
                'complexity' => $complexity,
                'totals' => $this->calculateTotals($data),
                'confidence' => 85,
            ],
        ];
    }

    private function analyzeControllers(array $data): array
    {
        $items = $data['controllers']['items'] ?? [];
        $methodCounts = array_map(fn($c) => count($c['methods'] ?? []), $items);

        // Sort by method count descending
        $sorted = $items;
        usort($sorted, fn($a, $b) => count($b['methods'] ?? []) <=> count($a['methods'] ?? []));

        return [
            'total' => count($items),
            'total_methods' => array_sum($methodCounts),
            'average_methods' => count($items) > 0 ? round(array_sum($methodCounts) / count($items), 1) : 0,
            'largest' => array_slice($sorted, 0, 5),
            'smallest' => array_slice(array_reverse($sorted), 0, 3),
            'median_methods' => $this->median($methodCounts),
            'crud_count' => count(array_filter($items, fn($c) => $c['is_crud'] ?? false)),
            'action_count' => count(array_filter($items, fn($c) => in_array('__invoke', $c['methods'] ?? []))),
            'total_middleware' => array_sum(array_map(fn($c) => count($c['middleware'] ?? []), $items)),
        ];
    }

    private function analyzeServices(array $data): array
    {
        $items = $data['services']['items'] ?? [];
        $methodCounts = array_map(fn($s) => count($s['methods'] ?? []), $items);

        $sorted = $items;
        usort($sorted, fn($a, $b) => count($b['methods'] ?? []) <=> count($a['methods'] ?? []));

        $byType = [];
        foreach ($items as $svc) {
            $type = $svc['type'] ?? 'service';
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        return [
            'total' => count($items),
            'total_methods' => array_sum($methodCounts),
            'average_methods' => count($items) > 0 ? round(array_sum($methodCounts) / count($items), 1) : 0,
            'largest' => array_slice($sorted, 0, 5),
            'by_type' => $byType,
            'total_dependencies' => array_sum(array_map(fn($s) => count($s['dependencies'] ?? []), $items)),
            'total_model_refs' => array_sum(array_map(fn($s) => count($s['referenced_models'] ?? []), $items)),
        ];
    }

    private function analyzeModels(array $data): array
    {
        $items = $data['models']['items'] ?? [];
        $methodCounts = [];
        foreach ($items as $m) {
            $count = count($m['scopes'] ?? []) + count($m['accessors'] ?? []) + count($m['mutators'] ?? []);
            $methodCounts[] = $count;
        }

        $sorted = $items;
        usort($sorted, fn($a, $b) => (count($b['relations'] ?? []) + count($b['fillable'] ?? [])) <=> (count($a['relations'] ?? []) + count($a['fillable'] ?? [])));

        // Most connected model (most relations)
        $mostConnected = null;
        $maxRelations = 0;
        foreach ($items as $m) {
            $totalRels = array_sum($m['relations'] ?? []);
            if ($totalRels > $maxRelations) {
                $maxRelations = $totalRels;
                $mostConnected = $m['name'];
            }
        }

        return [
            'total' => count($items),
            'largest' => array_slice($sorted, 0, 5),
            'most_connected' => $mostConnected,
            'total_relations_count' => array_sum(array_map(fn($m) => array_sum($m['relations'] ?? []), $items)),
            'total_scopes' => array_sum(array_map(fn($m) => count($m['scopes'] ?? []), $items)),
            'total_accessors' => array_sum(array_map(fn($m) => count($m['accessors'] ?? []), $items)),
            'total_mutators' => array_sum(array_map(fn($m) => count($m['mutators'] ?? []), $items)),
            'total_traits_used' => array_sum(array_map(fn($m) => count($m['traits'] ?? []), $items)),
            'models_with_soft_deletes' => count(array_filter($items, fn($m) => in_array('SoftDeletes', $m['traits'] ?? []))),
        ];
    }

    private function analyzeRoutes(array $data): array
    {
        $items = $data['routes']['items'] ?? [];
        $routeGroups = $data['route_intelligence']['groups'] ?? [];

        // Average routes per controller
        $controllerRoutes = [];
        foreach ($items as $route) {
            $action = $route['action'] ?? '';
            if (str_contains($action, '@')) {
                $ctrlName = substr(strrchr(explode('@', $action)[0], '\\') ?: explode('@', $action)[0], 1);
                $controllerRoutes[$ctrlName] = ($controllerRoutes[$ctrlName] ?? 0) + 1;
            }
        }

        $routeCounts = array_values($controllerRoutes);

        return [
            'total' => count($items),
            'average_per_controller' => count($routeCounts) > 0 ? round(array_sum($routeCounts) / count($routeCounts), 1) : 0,
            'controller_with_most_routes' => !empty($controllerRoutes) ? array_search(max($routeCounts), $routeCounts) : null,
            'most_routes_for_controller' => !empty($routeCounts) ? max($routeCounts) : 0,
            'groups' => count($routeGroups),
            'named_routes' => count(array_filter($items, fn($r) => !empty($r['name']))),
            'unnamed_routes' => count(array_filter($items, fn($r) => empty($r['name']))),
            'method_breakdown' => $this->countMethods($items),
        ];
    }

    private function analyzeFeatures(array $data): array
    {
        $features = $data['features']['items'] ?? [];
        if (empty($features)) return ['total' => 0];

        $sorted = $features;
        usort($sorted, fn($a, $b) => ($b['slice_count'] ?? 0) <=> ($a['slice_count'] ?? 0));

        return [
            'total' => count($features),
            'largest' => !empty($sorted) ? $sorted[0]['name'] : null,
            'largest_slice_count' => !empty($sorted) ? $sorted[0]['slice_count'] ?? 0 : 0,
            'average_slice_count' => count($features) > 0 ? round(array_sum(array_column($features, 'slice_count')) / count($features), 1) : 0,
            'top_features' => array_slice($sorted, 0, 5),
        ];
    }

    private function analyzeRelationships(array $data): array
    {
        $models = $data['models']['items'] ?? [];
        $totalRelations = 0;
        $relationTypes = [];

        foreach ($models as $m) {
            foreach ($m['relations'] ?? [] as $type => $count) {
                $relationTypes[$type] = ($relationTypes[$type] ?? 0) + $count;
                $totalRelations += $count;
            }
        }

        return [
            'total_relationships' => $totalRelations,
            'by_type' => $relationTypes,
            'models_with_relations' => count(array_filter($models, fn($m) => !empty($m['relations']))),
        ];
    }

    private function estimateComplexity(array $data): array
    {
        $score = 0;

        // More services = more business logic complexity
        $svcCount = $data['services']['count'] ?? 0;
        $score += $svcCount * 2;

        // More events = more complex state changes
        $eventCount = $data['events']['count'] ?? 0;
        $score += $eventCount * 3;

        // More jobs = more async complexity
        $jobCount = $data['jobs']['count'] ?? 0;
        $score += $jobCount * 2;

        // More policies = more authorization complexity
        $policyCount = $data['policies']['count'] ?? 0;
        $score += $policyCount * 2;

        // More notifications = more notification complexity
        $notifCount = $data['notifications']['count'] ?? 0;
        $score += $notifCount;

        // More models = more domain complexity
        $modelCount = $data['models']['count'] ?? 0;
        $score += $modelCount * 3;

        // More controllers = more route complexity
        $ctrlCount = $data['controllers']['count'] ?? 0;
        $score += $ctrlCount * 2;

        // Routes
        $routeCount = $data['routes']['count'] ?? 0;
        $score += $routeCount;

        // Large controllers increase complexity
        $ctrlItems = $data['controllers']['items'] ?? [];
        foreach ($ctrlItems as $c) {
            if (count($c['methods'] ?? []) > 10) $score += 2;
        }

        // Architectural patterns
        $arch = $data['architecture'] ?? [];
        if (!empty($arch['secondary'])) $score += count($arch['secondary']) * 3;

        $level = match (true) {
            $score >= 100 => 'very_high',
            $score >= 60 => 'high',
            $score >= 30 => 'medium',
            default => 'low',
        };

        return [
            'score' => $score,
            'level' => $level,
            'description' => match ($level) {
                'very_high' => 'Complex project with extensive business logic, event system, and background jobs',
                'high' => 'Moderately complex project with multiple services and event flows',
                'medium' => 'Standard Laravel project with average complexity',
                'low' => 'Simple project — few services, events, or jobs',
            },
        ];
    }

    private function calculateTotals(array $data): array
    {
        return [
            'public_methods' => $this->sumPublicMethods($data),
            'total_relations' => array_sum(array_map(fn($m) => array_sum($m['relations'] ?? []), $data['models']['items'] ?? [])),
            'total_policies' => $data['policies']['count'] ?? 0,
            'total_requests' => $data['form_requests']['count'] ?? 0,
            'total_events' => $data['events']['count'] ?? 0,
            'total_jobs' => $data['jobs']['count'] ?? 0,
            'total_commands' => $data['statistics']['commands'] ?? 0,
            'total_enums' => $data['enums']['count'] ?? 0,
            'total_traits' => $data['traits']['count'] ?? 0,
            'total_livewire_components' => $data['statistics']['livewire_components'] ?? 0,
            'total_mail_classes' => $data['mail']['count'] ?? 0,
            'total_middleware' => count($data['middleware']['registered'] ?? []),
        ];
    }

    private function sumPublicMethods(array $data): int
    {
        $total = 0;
        foreach ($data['controllers']['items'] ?? [] as $c) $total += count($c['methods'] ?? []);
        foreach ($data['services']['items'] ?? [] as $s) $total += count($s['methods'] ?? []);
        return $total;
    }

    private function median(array $numbers): float
    {
        sort($numbers);
        $count = count($numbers);
        if ($count === 0) return 0;
        $mid = (int)floor($count / 2);
        if ($count % 2 === 0) {
            return ($numbers[$mid - 1] + $numbers[$mid]) / 2;
        }
        return $numbers[$mid];
    }

    private function countMethods(array $routes): array
    {
        $methods = [];
        foreach ($routes as $r) {
            foreach ($r['methods'] ?? [] as $m) {
                if ($m !== 'HEAD') {
                    $methods[$m] = ($methods[$m] ?? 0) + 1;
                }
            }
        }
        arsort($methods);
        return $methods;
    }
}