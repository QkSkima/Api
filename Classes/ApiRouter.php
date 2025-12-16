<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api;

class ApiRouter
{
    private static ?self $instance = null;

    /**
     * Registered routes
     * Format: ['api/v1/order/new' => [
     *     'controller' => ControllerClass::class,
     *     'action' => 'methodName',
     *     'method' => 'GET',
     *     'namespace' => 'v1',
     *     'templatePath' => 'V1/Order/New'
     * ]]
     */
    private array $routes = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Define routes using a fluent callback syntax
     *
     * @param callable $callback
     */
    public static function draw(callable $callback): void
    {
        $instance = self::getInstance();
        $builder = new RouteBuilder($instance);
        $callback($builder);
    }

    /**
     * Register a route internally
     *
     * @param string $path Full path including scope (e.g., 'api/v1/order/new')
     * @param string $controller
     * @param string|null $action
     * @param string|null $method HTTP method (GET, POST, etc.) or null for any
     * @param string $namespace Namespace path for templates (e.g., 'v1')
     */
    public function register(string $path, string $controller, ?string $action, ?string $method, string $namespace): void
    {
        $path = strtolower(trim($path, '/'));
        
        $key = $method ? "{$method}:{$path}" : $path;
        
        // Build template path: Namespace/ControllerName/ActionName
        $templatePath = $this->buildTemplatePath($namespace, $controller, $action);
        
        $this->routes[$key] = [
            'controller' => $controller,
            'action' => $action,
            'method' => $method,
            'path' => $path,
            'namespace' => $namespace,
            'templatePath' => $templatePath
        ];
    }

    /**
     * Resolve a route by path and optional HTTP method
     *
     * @param string $path
     * @param string|null $method HTTP method (GET, POST, etc.)
     * @return array|null ['controller' => string, 'action' => string|null, 'method' => string|null, 'namespace' => string, 'templatePath' => string]
     */
    public function resolve(string $path, ?string $method = null): ?array
    {
        $path = strtolower(trim($path, '/'));
        
        // Try to match with specific method first
        if ($method !== null) {
            $key = strtoupper($method) . ':' . $path;
            if (isset($this->routes[$key])) {
                return [
                    'controller' => $this->routes[$key]['controller'],
                    'action' => $this->routes[$key]['action'],
                    'method' => $this->routes[$key]['method'],
                    'namespace' => $this->routes[$key]['namespace'],
                    'templatePath' => $this->routes[$key]['templatePath']
                ];
            }
        }
        
        // Fall back to any method
        if (isset($this->routes[$path])) {
            return [
                'controller' => $this->routes[$path]['controller'],
                'action' => $this->routes[$path]['action'],
                'method' => $this->routes[$path]['method'],
                'namespace' => $this->routes[$path]['namespace'],
                'templatePath' => $this->routes[$path]['templatePath']
            ];
        }
        
        return null;
    }

    /**
     * Get controller for a route
     *
     * @param string $path
     * @param string|null $method
     * @return string|null
     */
    public function getController(string $path, ?string $method = null): ?string
    {
        $route = $this->resolve($path, $method);
        return $route['controller'] ?? null;
    }

    /**
     * Get action for a route
     *
     * @param string $path
     * @param string|null $method
     * @return string|null
     */
    public function getAction(string $path, ?string $method = null): ?string
    {
        $route = $this->resolve($path, $method);
        return $route['action'] ?? null;
    }

    /**
     * Get template path for a route
     *
     * @param string $path
     * @param string|null $method
     * @return string|null
     */
    public function getTemplatePath(string $path, ?string $method = null): ?string
    {
        $route = $this->resolve($path, $method);
        return $route['templatePath'] ?? null;
    }

    /**
     * Get all registered routes (for debugging)
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Build template path from namespace, controller, and action
     * Format: Namespace/ControllerName/ActionName
     *
     * @param string $namespace
     * @param string $controller
     * @param string|null $action
     * @return string
     */
    private function buildTemplatePath(string $namespace, string $controller, ?string $action): string
    {
        $parts = [];
        
        // Add namespace (convert to PascalCase)
        if (!empty($namespace)) {
            $parts[] = self::toCamelCase($namespace);
        }
        
        // Add controller name (without "Controller" suffix)
        $controllerName = $this->extractControllerName($controller);
        $parts[] = self::toCamelCase($controllerName);
        
        // Add action (convert to PascalCase)
        if (!empty($action)) {
            $parts[] = self::toCamelCase($action);
        }
        
        return implode('/', $parts);
    }

    /**
     * Extract controller name from fully qualified class name
     * Example: App\Controller\WorkflowController -> Workflow
     *
     * @param string $controllerClass
     * @return string
     */
    private function extractControllerName(string $controllerClass): string
    {
        $parts = explode('\\', $controllerClass);
        $className = end($parts);
        
        // Remove "Controller" suffix if present
        if (str_ends_with($className, 'Controller')) {
            $className = substr($className, 0, -10);
        }
        
        return $className;
    }

    public static function toCamelCase(string $string): string
    {
        // Split by slash for nested paths
        $segments = explode('/', $string);

        // Convert each segment to CamelCase
        $segments = array_map(function ($segment) {
            $segment = str_replace(['-', '_'], ' ', $segment);
            $segment = ucwords($segment);
            return str_replace(' ', '', $segment);
        }, $segments);

        // Join back with slashes
        return implode('/', $segments);
    }
}
