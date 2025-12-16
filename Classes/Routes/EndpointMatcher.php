<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api\Routes;

use InvalidArgumentException;
use QkSkima\Api\ApiRouter;

class EndpointMatcher
{
    private string $routePath = '';
    private string $httpMethod = '';
    private ?array $resolvedRoute = null;

    /**
     * Parse the path and resolve it against registered routes
     *
     * @param string $path Request path (e.g. /v1/users/show or v1/users/show)
     * @param string $httpMethod HTTP method (GET, POST, etc.)
     * @return bool True if matched and resolved, false otherwise
     * @throws InvalidArgumentException If unsafe characters are detected
     */
    public function match(string $path, string $httpMethod = 'GET'): bool
    {
        $path = trim($path, '/');
        $this->httpMethod = strtoupper($httpMethod);

        if (empty($path)) {
            return false;
        }

        // Validate each segment of the route path
        foreach (explode('/', $path) as $segment) {
            if (!$this->isValidSegment($segment)) {
                return false;
            }
        }

        $this->routePath = $path;

        // Try to resolve the route using ApiRouter
        $router = ApiRouter::getInstance();
        $this->resolvedRoute = $router->resolve($this->routePath, $this->httpMethod);

        return $this->resolvedRoute !== null;
    }

    /**
     * Get the extracted route path
     *
     * @return string
     */
    public function getRoutePath(): string
    {
        return $this->routePath;
    }

    /**
     * Get the HTTP method
     *
     * @return string
     */
    public function getHttpMethod(): string
    {
        return $this->httpMethod;
    }

    /**
     * Get the resolved route information
     *
     * @return array|null ['controller' => string, 'action' => string|null, 'method' => string|null, 'namespace' => string, 'templatePath' => string]
     */
    public function getResolvedRoute(): ?array
    {
        return $this->resolvedRoute;
    }

    /**
     * Get the controller class from resolved route
     *
     * @return string|null
     */
    public function getController(): ?string
    {
        return $this->resolvedRoute['controller'] ?? null;
    }

    /**
     * Get the action method from resolved route
     *
     * @return string|null
     */
    public function getAction(): ?string
    {
        return $this->resolvedRoute['action'] ?? null;
    }

    /**
     * Get the namespace from resolved route
     *
     * @return string|null
     */
    public function getNamespace(): ?string
    {
        return $this->resolvedRoute['namespace'] ?? null;
    }

    /**
     * Get the template path from resolved route
     * Format: Namespace/ControllerName/ActionName (e.g., V1/Workflow/Login)
     *
     * @return string|null
     */
    public function getTemplatePath(): ?string
    {
        return $this->resolvedRoute['templatePath'] ?? null;
    }

    /**
     * Validate a single path segment
     * Only allow letters, numbers, underscore, and dash.
     */
    private function isValidSegment(string $segment): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $segment) === 1;
    }

    /**
     * Convert string to camel case
     */
    public static function toCamelCase(string $string): string
    {
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        return str_replace(' ', '', $string);
    }
}