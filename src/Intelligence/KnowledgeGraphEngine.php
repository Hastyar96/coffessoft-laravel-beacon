<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * v3.0 Knowledge Graph Engine
 *
 * Every discovered object becomes a typed node with id, type, name, namespace,
 * path, summary, and confidence. Relationships connect nodes with typed edges.
 *
 * Node types: Model, Controller, Service, Repository, Job, Event, Notification,
 * Mail, Middleware, Policy, FormRequest, BladeComponent, View, Route, Command,
 * Migration, Table, Enum, Trait, Package
 *
 * Relationship types: controller_uses_service, service_uses_repository,
 * repository_uses_model, controller_returns_view, route_calls_controller,
 * model_has_relation, model_has_policy, model_has_factory, model_has_observer,
 * controller_dispatches_job, job_dispatches_event, event_calls_listener,
 * notification_uses_mail, blade_extends_layout, blade_uses_component,
 * request_used_by_controller, policy_protects_model
 */
class KnowledgeGraphEngine
{
    /** @var array<string, array<string, mixed>> */
    private array $nodes = [];

    /** @var array<int, array<string, mixed>> */
    private array $edges = [];

    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        $this->nodes = [];
        $this->edges = [];

        // Phase 1: Add all nodes
        $this->addModelNodes($data);
        $this->addControllerNodes($data);
        $this->addServiceNodes($data);
        $this->addRepositoryNodes($data);
        $this->addFormRequestNodes($data);
        $this->addPolicyNodes($data);
        $this->addEventNodes($data);
        $this->addListenerNodes($data);
        $this->addJobNodes($data);
        $this->addNotificationNodes($data);
        $this->addMailNodes($data);
        $this->addRouteNodes($data);
        $this->addMiddlewareNodes($data);
        $this->addBladeNodes($data);
        $this->addEnumNodes($data);
        $this->addTraitNodes($data);
        $this->addCommandNodes($data);
        $this->addTableNodes($data);
        $this->addPackageNodes($data);

        // Phase 2: Add all relationships
        $this->addControllerServiceEdges($data);
        $this->addServiceRepositoryEdges($data);
        $this->addRepositoryModelEdges($data);
        $this->addControllerReturnViewEdges($data);
        $this->addRouteControllerEdges($data);
        $this->addModelRelationEdges($data);
        $this->addPolicyModelEdges($data);
        $this->addControllerDispatchesJobEdges($data);
        $this->addJobEventEdges($data);
        $this->addEventListenerEdges($data);
        $this->addNotificationMailEdges($data);
        $this->addBladeLayoutEdges($data);
        $this->addBladeComponentEdges($data);
        $this->addRequestControllerEdges($data);
        $this->addServiceModelEdges($data);
        $this->addControllerModelEdges($data);
        $this->addJobDispatchersEdges($data);
        $this->addEventDispatchersEdges($data);

        $graph = [
            'nodes' => array_values($this->nodes),
            'node_count' => count($this->nodes),
            'edges' => $this->edges,
            'edge_count' => count($this->edges),
            'node_types' => $this->countNodeTypes(),
            'relationship_types' => $this->countEdgeTypes(),
        ];

        return [
            'knowledge_graph' => $graph,
            'project_graph' => $graph, // Also update original for backward compat
        ];
    }

    private function addNode(string $id, string $type, string $name, ?string $namespace = null, ?string $path = null, ?string $summary = null, int $confidence = 85): void
    {
        if (!isset($this->nodes[$id])) {
            $this->nodes[$id] = [
                'id' => $id,
                'type' => $type,
                'name' => $name,
                'namespace' => $namespace,
                'path' => $path,
                'summary' => $summary,
                'confidence' => $confidence,
                'references' => [], // Will be populated later
            ];
        }
    }

    private function addEdge(string $fromId, string $toId, string $type, string $label, int $confidence = 80): void
    {
        // Only add edge if both nodes exist
        if (isset($this->nodes[$fromId]) && isset($this->nodes[$toId])) {
            $this->edges[] = [
                'from' => $fromId,
                'to' => $toId,
                'type' => $type,
                'label' => $label,
                'confidence' => $confidence,
            ];

            // Add cross-references
            $this->nodes[$fromId]['references'][] = ['id' => $toId, 'type' => $type];
            $this->nodes[$toId]['references'][] = ['id' => $fromId, 'type' => $type];
        }
    }

    private function nodeId(string $type, string $name): string
    {
        return strtolower($type) . ':' . $name;
    }

    // ========= Node Builders =========

    private function addModelNodes(array $data): void
    {
        foreach ($data['models']['items'] ?? [] as $m) {
            $fillable = $m['fillable'] ?? [];
            $traits = $m['traits'] ?? [];
            $relations = $m['relations'] ?? [];
            $summary = "Model representing {$m['name']} entity.";
            if (!empty($fillable)) $summary .= " Attributes: " . implode(', ', array_slice($fillable, 0, 6)) . (count($fillable) > 6 ? '...' : '');
            if (!empty($traits)) $summary .= " Traits: " . implode(', ', $traits);
            $this->addNode($this->nodeId('model', $m['name']), 'model', $m['name'], $m['namespace'], $m['path'], $summary, 90);
        }
    }

    private function addControllerNodes(array $data): void
    {
        foreach ($data['controllers']['items'] ?? [] as $c) {
            $methods = $c['methods'] ?? [];
            $isCrud = $c['is_crud'] ?? false;
            $type = $isCrud ? 'crud_controller' : 'controller';
            $summary = ($isCrud ? 'CRUD ' : '') . "Controller for " . ($methods ? implode(', ', $methods) : 'HTTP requests');
            $this->addNode($this->nodeId('controller', $c['name']), $type, $c['name'], $c['namespace'], $c['path'], $summary, 90);
        }
    }

    private function addServiceNodes(array $data): void
    {
        foreach ($data['services']['items'] ?? [] as $s) {
            $methods = $s['methods'] ?? [];
            $summary = "Service with " . count($methods) . " methods: " . implode(', ', array_slice($methods, 0, 5)) . (count($methods) > 5 ? '...' : '');
            $this->addNode($this->nodeId('service', $s['name']), 'service', $s['name'], $s['namespace'], $s['path'], $summary, 85);
        }
    }

    private function addRepositoryNodes(array $data): void
    {
        foreach ($data['repositories']['items'] ?? [] as $r) {
            $type = ($r['type'] ?? '') === 'interface' ? 'repository_interface' : 'repository';
            $methods = $r['methods'] ?? [];
            $summary = "Repository with " . count($methods) . " methods";
            $this->addNode($this->nodeId('repository', $r['name']), $type, $r['name'], $r['namespace'], $r['path'], $summary, 85);
        }
    }

    private function addFormRequestNodes(array $data): void
    {
        foreach ($data['form_requests']['items'] ?? [] as $r) {
            $rules = $r['rules'] ?? [];
            $summary = "Validates " . count($rules) . " fields" . ($r['authorize'] ? ', with authorization' : '');
            $this->addNode($this->nodeId('request', $r['name']), 'form_request', $r['name'], $r['namespace'], $r['path'], $summary, 90);
        }
    }

    private function addPolicyNodes(array $data): void
    {
        foreach ($data['policies']['items'] ?? [] as $p) {
            $abilities = $p['abilities'] ?? [];
            $summary = "Authorizes " . implode(', ', $abilities) . " for " . ($p['model'] ?? 'unknown') . " model";
            $this->addNode($this->nodeId('policy', $p['name']), 'policy', $p['name'], $p['namespace'], $p['path'], $summary, 90);
        }
    }

    private function addEventNodes(array $data): void
    {
        foreach ($data['events']['items'] ?? [] as $e) {
            $summary = "Event" . ($e['should_broadcast'] ? ' (broadcasts)' : '') . ($e['should_queue'] ? ' (queued)' : '');
            $this->addNode($this->nodeId('event', $e['name']), 'event', $e['name'], $e['namespace'], $e['path'], $summary, 85);
        }
    }

    private function addListenerNodes(array $data): void
    {
        foreach ($data['events']['listeners'] ?? [] as $l) {
            $summary = "Handles " . ($l['handles'] ?? 'events') . ($l['queued'] ? ' (queued)' : '');
            $this->addNode($this->nodeId('listener', $l['name']), 'listener', $l['name'], $l['namespace'], $l['path'], $summary, 85);
        }
    }

    private function addJobNodes(array $data): void
    {
        foreach ($data['jobs']['items'] ?? [] as $j) {
            $summary = ($j['queued'] ? 'Queued' : 'Sync') . " job" . ($j['unique'] ? ' (unique)' : '');
            $this->addNode($this->nodeId('job', $j['name']), 'job', $j['name'], $j['namespace'], $j['path'], $summary, 85);
        }
    }

    private function addNotificationNodes(array $data): void
    {
        foreach ($data['notifications']['items'] ?? [] as $n) {
            $channels = implode(', ', $n['channels'] ?? []);
            $summary = "Notification via " . ($channels ?: 'default channels');
            $this->addNode($this->nodeId('notification', $n['name']), 'notification', $n['name'], $n['namespace'], $n['path'], $summary, 85);
        }
    }

    private function addMailNodes(array $data): void
    {
        foreach ($data['mail']['items'] ?? [] as $m) {
            $summary = ($m['subject'] ? "Subject: {$m['subject']}" : 'Mail class') . ($m['markdown'] ? " (template: {$m['markdown']})" : '');
            $this->addNode($this->nodeId('mail', $m['name']), 'mail', $m['name'], $m['namespace'], $m['path'], $summary, 85);
        }
    }

    private function addRouteNodes(array $data): void
    {
        foreach ($data['routes']['items'] ?? [] as $r) {
            $uri = $r['uri'] ?? '';
            $methods = implode(',', array_diff($r['methods'] ?? [], ['HEAD']));
            $summary = "{$methods} {$uri}" . ($r['name'] ? " ({$r['name']})" : '');
            $this->addNode($this->nodeId('route', $uri), 'route', $uri, null, null, $summary, 95);
        }
    }

    private function addMiddlewareNodes(array $data): void
    {
        $registered = $data['middleware']['registered'] ?? [];
        $seen = [];
        foreach ($registered as $mw) {
            $name = $mw['name'] ?? $mw;
            if (in_array($name, $seen)) continue;
            $seen[] = $name;
            $this->addNode($this->nodeId('middleware', $name), 'middleware', $name, null, $mw['path'] ?? null, 'Middleware', 85);
        }
    }

    private function addBladeNodes(array $data): void
    {
        foreach ($data['blade']['layouts'] ?? [] as $l) {
            $this->addNode($this->nodeId('layout', $l['name']), 'blade_layout', $l['name'], null, $l['path'], 'Layout template', 90);
        }
        foreach ($data['blade']['components'] ?? [] as $c) {
            $isAnon = ($c['anonymous'] ?? false);
            $this->addNode($this->nodeId($isAnon ? 'anonymous_component' : 'blade_component', $c['name']), $isAnon ? 'anonymous_component' : 'blade_component', $c['name'], null, $c['path'], ($isAnon ? 'Anonymous' : '') . ' Blade component', 85);
        }
        foreach ($data['blade']['views'] ?? [] as $v) {
            $this->addNode($this->nodeId('view', $v['name']), 'blade_view', $v['name'], null, $v['path'], 'Blade view', 90);
        }
    }

    private function addEnumNodes(array $data): void
    {
        foreach ($data['enums']['definitions'] ?? [] as $e) {
            $cases = array_map(fn($c) => $c['name'], $e['cases'] ?? []);
            $summary = "Enum with cases: " . implode(', ', $cases) . ($e['backed_type'] ? " (backed by {$e['backed_type']})" : '');
            $this->addNode($this->nodeId('enum', $e['name']), 'enum', $e['name'], $e['namespace'], $e['path'], $summary, 90);
        }
    }

    private function addTraitNodes(array $data): void
    {
        foreach ($data['traits']['definitions'] ?? [] as $t) {
            $methods = $t['methods'] ?? [];
            $summary = "Trait with " . count($methods) . " methods";
            $this->addNode($this->nodeId('trait', $t['name']), 'trait', $t['name'], $t['namespace'], $t['path'], $summary, 80);
        }
    }

    private function addCommandNodes(array $data): void
    {
        foreach ($data['entry_points']['items'] ?? [] as $ep) {
            if (($ep['type'] ?? '') === 'artisan_command') {
                foreach ($ep['commands'] ?? [] as $cmd) {
                    $this->addNode($this->nodeId('command', $cmd['signature']), 'command', $cmd['signature'], null, null, $cmd['description'] ?? 'Artisan command', 85);
                }
            }
        }
    }

    private function addTableNodes(array $data): void
    {
        foreach ($data['database']['tables'] ?? [] as $t) {
            $cols = count($t['columns'] ?? []);
            $summary = "Table with {$cols} columns";
            $this->addNode($this->nodeId('table', $t['name']), 'database_table', $t['name'], null, null, $summary, 95);
        }
    }

    private function addPackageNodes(array $data): void
    {
        foreach ($data['packages']['items'] ?? [] as $p) {
            $this->addNode($this->nodeId('package', $p['name']), 'package', $p['name'], null, null, $p['purpose'] ?? $p['category'], 95);
        }
    }

    // ========= Edge Builders =========

    private function addControllerServiceEdges(array $data): void
    {
        foreach ($data['controllers']['items'] ?? [] as $c) {
            foreach ($data['services']['items'] ?? [] as $s) {
                if (str_contains($s['name'], preg_replace('/Controller$/', '', $c['name']))) {
                    $this->addEdge($this->nodeId('controller', $c['name']), $this->nodeId('service', $s['name']), 'controller_uses_service', 'uses service', 75);
                }
            }
        }
    }

    private function addServiceRepositoryEdges(array $data): void
    {
        foreach ($data['services']['items'] ?? [] as $s) {
            foreach ($s['referenced_repositories'] ?? [] as $ref) {
                $parts = explode('\\', $ref);
                $name = end($parts);
                $this->addEdge($this->nodeId('service', $s['name']), $this->nodeId('repository', $name), 'service_uses_repository', 'uses repository', 85);
            }
        }
    }

    private function addRepositoryModelEdges(array $data): void
    {
        foreach ($data['repositories']['items'] ?? [] as $r) {
            foreach ($r['referenced_models'] ?? [] as $ref) {
                $parts = explode('\\', $ref);
                $name = end($parts);
                $this->addEdge($this->nodeId('repository', $r['name']), $this->nodeId('model', $name), 'repository_uses_model', 'queries model', 85);
            }
        }
    }

    private function addControllerReturnViewEdges(array $data): void
    {
        foreach ($data['controllers']['items'] ?? [] as $c) {
            $modelName = preg_replace('/Controller$/', '', $c['name']);
            $viewPattern = strtolower(preg_replace('/([a-z])([A-Z])/', '$1.$2', $modelName));
            foreach ($data['blade']['views'] ?? [] as $v) {
                if ($viewPattern && str_contains($v['name'] ?? '', $viewPattern)) {
                    $this->addEdge($this->nodeId('controller', $c['name']), $this->nodeId('view', $v['name']), 'controller_returns_view', 'returns view', 70);
                }
            }
        }
    }

    private function addRouteControllerEdges(array $data): void
    {
        foreach ($data['routes']['items'] ?? [] as $r) {
            $action = $r['action'] ?? '';
            if (str_contains($action, '@')) {
                $parts = explode('@', $action);
                $ctrlName = substr(strrchr($parts[0], '\\') ?: $parts[0], 1);
                $this->addEdge($this->nodeId('route', $r['uri']), $this->nodeId('controller', $ctrlName), 'route_calls_controller', 'calls controller', 95);
            }
        }
    }

    private function addModelRelationEdges(array $data): void
    {
        foreach ($data['models']['items'] ?? [] as $m) {
            foreach ($m['relations'] ?? [] as $type => $count) {
                $this->addEdge($this->nodeId('model', $m['name']), $this->nodeId('model', $m['name']), 'model_has_relation', "{$type}({$count})", 80);
            }
        }
    }

    private function addPolicyModelEdges(array $data): void
    {
        foreach ($data['policies']['items'] ?? [] as $p) {
            if ($p['model']) {
                $this->addEdge($this->nodeId('policy', $p['name']), $this->nodeId('model', $p['model']), 'policy_protects_model', 'protects', 90);
            }
        }
    }

    private function addControllerDispatchesJobEdges(array $data): void
    {
        foreach ($data['jobs']['dispatchers'] ?? [] as $d) {
            $ctrlName = $d['class'] ?? '';
            foreach ($d['dispatches'] ?? [] as $jobName) {
                $this->addEdge($this->nodeId('controller', $ctrlName), $this->nodeId('job', $jobName), 'controller_dispatches_job', 'dispatches job', 80);
                $this->addEdge($this->nodeId('service', $ctrlName), $this->nodeId('job', $jobName), 'controller_dispatches_job', 'dispatches job', 80);
            }
        }
    }

    private function addJobEventEdges(array $data): void
    {
        foreach ($data['events']['dispatchers'] ?? [] as $d) {
            foreach ($d['dispatches'] ?? [] as $eventName) {
                $this->addEdge($this->nodeId('job', $d['class']), $this->nodeId('event', $eventName), 'job_dispatches_event', 'dispatches event', 75);
            }
        }
    }

    private function addEventListenerEdges(array $data): void
    {
        foreach ($data['events']['listeners'] ?? [] as $l) {
            if ($l['handles']) {
                $this->addEdge($this->nodeId('listener', $l['name']), $this->nodeId('event', $l['handles']), 'event_calls_listener', 'handles event', 85);
            }
        }
    }

    private function addNotificationMailEdges(array $data): void
    {
        foreach ($data['notifications']['items'] ?? [] as $n) {
            if (in_array('mail', $n['channels'] ?? [])) {
                foreach ($data['mail']['items'] ?? [] as $m) {
                    if (str_contains($m['name'], $n['name'])) {
                        $this->addEdge($this->nodeId('notification', $n['name']), $this->nodeId('mail', $m['name']), 'notification_uses_mail', 'sends via mail', 70);
                    }
                }
            }
        }
    }

    private function addBladeLayoutEdges(array $data): void
    {
        foreach ($data['blade']['views'] ?? [] as $v) {
            if ($v['extends']) {
                $this->addEdge($this->nodeId('view', $v['name']), $this->nodeId('layout', $v['extends']), 'blade_extends_layout', 'extends', 90);
            }
        }
    }

    private function addBladeComponentEdges(array $data): void
    {
        foreach ($data['blade']['views'] ?? [] as $v) {
            foreach ($v['components'] ?? [] as $comp) {
                $this->addEdge($this->nodeId('view', $v['name']), $this->nodeId('blade_component', $comp), 'blade_uses_component', 'uses component', 80);
            }
        }
    }

    private function addRequestControllerEdges(array $data): void
    {
        foreach ($data['form_requests']['items'] ?? [] as $r) {
            $modelName = preg_replace('/^(Store|Update|Create|Delete)\s*/', '', $r['name']);
            $modelName = preg_replace('/Request$/', '', $modelName);
            if ($modelName) {
                $ctrlName = $modelName . 'Controller';
                $this->addEdge($this->nodeId('request', $r['name']), $this->nodeId('controller', $ctrlName), 'request_used_by_controller', 'validates for', 70);
            }
        }
    }

    private function addServiceModelEdges(array $data): void
    {
        foreach ($data['services']['items'] ?? [] as $s) {
            foreach ($s['referenced_models'] ?? [] as $ref) {
                $parts = explode('\\', $ref);
                $name = end($parts);
                $this->addEdge($this->nodeId('service', $s['name']), $this->nodeId('model', $name), 'service_uses_model', 'uses model', 85);
            }
        }
    }

    private function addControllerModelEdges(array $data): void
    {
        foreach ($data['controllers']['items'] ?? [] as $c) {
            $modelName = preg_replace('/Controller$/', '', $c['name']);
            if ($modelName && isset($this->nodes[$this->nodeId('model', $modelName)])) {
                $this->addEdge($this->nodeId('controller', $c['name']), $this->nodeId('model', $modelName), 'controller_manages_model', 'manages', 85);
            }
        }
    }

    private function addJobDispatchersEdges(array $data): void
    {
        foreach ($data['jobs']['dispatchers'] ?? [] as $d) {
            foreach ($d['dispatches'] ?? [] as $jobName) {
                // Already handled in addControllerDispatchesJobEdges
            }
        }
    }

    private function addEventDispatchersEdges(array $data): void
    {
        foreach ($data['events']['dispatchers'] ?? [] as $d) {
            foreach ($d['dispatches'] ?? [] as $eventName) {
                $class = $d['class'] ?? '';
                $this->addEdge($this->nodeId('event', $eventName), $this->nodeId('controller', $class), 'event_dispatched_by', 'dispatched by', 75);
                $this->addEdge($this->nodeId('event', $eventName), $this->nodeId('service', $class), 'event_dispatched_by', 'dispatched by', 75);
            }
        }
    }

    private function countNodeTypes(): array
    {
        $counts = [];
        foreach ($this->nodes as $node) {
            $type = $node['type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }
        return $counts;
    }

    private function countEdgeTypes(): array
    {
        $counts = [];
        foreach ($this->edges as $edge) {
            $type = $edge['type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }
        return $counts;
    }
}