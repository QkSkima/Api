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
     * Format: ['v1' => ['users' => ['controller' => ControllerClass::class, 'action' => 'methodName']]]
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
     * Register a route with a controller class and optional action method
     *
     * @param string $version
     * @param string $endpointId
     * @param string $controllerClass
     * @param string|null $actionMethod Optional, default null
     */
    public function draw(string $version, string $endpointId, string $controllerClass, ?string $actionMethod = null): void
    {
        $version = strtolower($version);
        $endpointId = strtolower($endpointId);

        if (!isset($this->routes[$version])) {
            $this->routes[$version] = [];
        }

        $this->routes[$version][$endpointId] = [
            'controller' => $controllerClass,
            'action' => $actionMethod
        ];
    }

    /**
     * Return controller class for a route
     */
    public function getController(string $version, string $endpointId): ?string
    {
        $route = $this->getRoute($version, $endpointId);
        return $route['controller'] ?? null;
    }

    /**
     * Return action method for a route (if set)
     */
    public function getAction(string $version, string $endpointId): ?string
    {
        $route = $this->getRoute($version, $endpointId);
        return $route['action'] ?? null;
    }

    /**
     * Return both controller and action together
     */
    public function resolve(string $version, string $endpointId): ?array
    {
        $route = $this->getRoute($version, $endpointId);
        return $route ? [
            'controller' => $route['controller'],
            'action' => $route['action'] ?? null
        ] : null;
    }

    private function getRoute(string $version, string $endpointId): ?array
    {
        $version = strtolower($version);
        $endpointId = strtolower($endpointId);
        return $this->routes[$version][$endpointId] ?? null;
    }

    public static function toCamelCase(string $string): string
    {
        // Split by slash
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
