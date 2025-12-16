<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api;

/**
 * Route builder for fluent route definition
 */
class RouteBuilder
{
    private ApiRouter $router;
    private array $scopeStack = [];
    private array $namespaceStack = [];

    public function __construct(ApiRouter $router, array $scopeStack = [], array $namespaceStack = [])
    {
        $this->router = $router;
        $this->scopeStack = $scopeStack;
        $this->namespaceStack = $namespaceStack;
    }

    /**
     * Create a scope (affects route path only, not template path)
     *
     * @param string $path
     * @param callable $callback
     */
    public function scope(string $path, callable $callback): void
    {
        $newScopeStack = array_merge($this->scopeStack, [$path]);
        $builder = new RouteBuilder($this->router, $newScopeStack, $this->namespaceStack);
        $callback($builder);
    }

    /**
     * Create a namespace (affects both route path and template path)
     *
     * @param string $path
     * @param callable $callback
     */
    public function namespace(string $path, callable $callback): void
    {
        $newScopeStack = array_merge($this->scopeStack, [$path]);
        $newNamespaceStack = array_merge($this->namespaceStack, [$path]);
        $builder = new RouteBuilder($this->router, $newScopeStack, $newNamespaceStack);
        $callback($builder);
    }

    /**
     * Register a GET route
     *
     * @param string $path
     * @param string $controller
     * @param string|null $action
     */
    public function get(string $path, string $controller, ?string $action = null): void
    {
        $this->addRoute($path, $controller, $action, 'GET');
    }

    /**
     * Register a POST route
     *
     * @param string $path
     * @param string $controller
     * @param string|null $action
     */
    public function post(string $path, string $controller, ?string $action = null): void
    {
        $this->addRoute($path, $controller, $action, 'POST');
    }

    /**
     * Register a PUT route
     *
     * @param string $path
     * @param string $controller
     * @param string|null $action
     */
    public function put(string $path, string $controller, ?string $action = null): void
    {
        $this->addRoute($path, $controller, $action, 'PUT');
    }

    /**
     * Register a PATCH route
     *
     * @param string $path
     * @param string $controller
     * @param string|null $action
     */
    public function patch(string $path, string $controller, ?string $action = null): void
    {
        $this->addRoute($path, $controller, $action, 'PATCH');
    }

    /**
     * Register a DELETE route
     *
     * @param string $path
     * @param string $controller
     * @param string|null $action
     */
    public function delete(string $path, string $controller, ?string $action = null): void
    {
        $this->addRoute($path, $controller, $action, 'DELETE');
    }

    /**
     * Register a route for any HTTP method
     *
     * @param string $path
     * @param string $controller
     * @param string|null $action
     */
    public function any(string $path, string $controller, ?string $action = null): void
    {
        $this->addRoute($path, $controller, $action, null);
    }

    /**
     * Add a route with the current scope and namespace stacks
     *
     * @param string $path
     * @param string $controller
     * @param string|null $action
     * @param string|null $method
     */
    private function addRoute(string $path, string $controller, ?string $action, ?string $method): void
    {
        $fullPath = $this->buildFullPath($path);
        $namespace = $this->buildNamespacePath();
        $this->router->register($fullPath, $controller, $action, $method, $namespace);
    }

    /**
     * Build full path from scope stack and current path
     *
     * @param string $path
     * @return string
     */
    private function buildFullPath(string $path): string
    {
        $segments = array_merge($this->scopeStack, [$path]);
        $segments = array_filter($segments, fn($s) => $s !== '');
        return implode('/', $segments);
    }

    /**
     * Build namespace path from namespace stack only
     *
     * @return string
     */
    private function buildNamespacePath(): string
    {
        $segments = array_filter($this->namespaceStack, fn($s) => $s !== '');
        return implode('/', $segments);
    }
}