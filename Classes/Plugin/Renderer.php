<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api\Plugin;

use Psr\Http\Message\ServerRequestInterface;
use QkSkima\Api\ApiRouter;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Service\FlexFormService;

class Renderer
{
    protected ContentObjectRenderer $cObj;

    /**
     * Set the content object (called automatically by TYPO3)
     *
     * @param ContentObjectRenderer $cObj
     */
    public function setContentObjectRenderer(ContentObjectRenderer $cObj): void
    {
        $this->cObj = $cObj;
    }

    /**
     * Main render method
     *
     * @param string $content
     * @param array $conf
     * @return string
     */
    public function render(string $content, array $conf): string
    {
        try {
            // Access the complete tt_content record data
            $contentData = $this->cObj->data;

            // Get FlexForm settings (controller and action)
            $flexFormSettings = $this->getFlexFormSettings($contentData['pi_flexform'] ?? '');

            $controllerClass = $flexFormSettings['controller'] ?? null;
            $actionMethod = $flexFormSettings['action'] ?? null;

            // Validate that controller and action are selected
            if (empty($controllerClass)) {
                return $this->renderError('No controller selected in plugin configuration.');
            }

            if (empty($actionMethod)) {
                return $this->renderError('No action selected in plugin configuration.');
            }

            // Validate that controller class exists
            if (!class_exists($controllerClass)) {
                return $this->renderError("Controller class not found: {$controllerClass}");
            }

            // Get the current request from ContentObjectRenderer
            $request = $this->getRequest();

            // Find the route for this controller/action combination
            $route = $this->findRoute($controllerClass, $actionMethod);

            if ($route === null) {
                return $this->renderError("No route found for controller '{$controllerClass}' and action '{$actionMethod}'");
            }

            // Check if the current HTTP method matches the route's required method
            if (!$this->isHttpMethodAllowed($request, $route)) {
                // HTTP method doesn't match - return empty string
                return '';
            }

            // Instantiate the controller
            $controller = GeneralUtility::makeInstance($controllerClass, $request, $route);

            // Execute the action and get the response
            $response = $controller->callAction($controllerClass, $actionMethod);

            // Extract and return the response body
            // The difference between USER and USER_INT is handled by TYPO3:
            // - USER (qkskimaapi_pi1): Output is cached
            // - USER_INT (qkskimaapi_pi2): Output is regenerated on every request
            if ($response instanceof \Psr\Http\Message\ResponseInterface) {
                return (string)$response->getBody();
            }

            // Fallback: if response is already a string
            return (string)$response;

        } catch (\Throwable $e) {
            // Handle any errors
            return $this->renderError(
                'Error rendering plugin: ' . $e->getMessage(),
                $e
            );
        }
    }

    /**
     * Check if the current HTTP method is allowed for the route
     *
     * @param ServerRequestInterface $request
     * @param array $route
     * @return bool
     */
    protected function isHttpMethodAllowed(ServerRequestInterface $request, array $route): bool
    {
        $routeMethod = $route['method'] ?? null;
        
        // If route method is null or 'ANY', allow any HTTP method
        if ($routeMethod === null || strtoupper($routeMethod) === 'ANY') {
            return true;
        }

        // Get current request method
        $currentMethod = strtoupper($request->getMethod());
        
        // Compare route method with current method
        return strtoupper($routeMethod) === $currentMethod;
    }

    /**
     * Parse FlexForm XML and return settings as array
     *
     * @param string $flexFormXml
     * @return array
     */
    protected function getFlexFormSettings(string $flexFormXml): array
    {
        if (empty($flexFormXml)) {
            return [];
        }

        // Use TYPO3's FlexFormService to parse the XML
        $flexFormService = GeneralUtility::makeInstance(FlexFormService::class);
        $settings = $flexFormService->convertFlexFormContentToArray($flexFormXml);

        return $settings;
    }

    /**
     * Get the current server request from ContentObjectRenderer
     *
     * @return ServerRequestInterface
     */
    protected function getRequest(): ServerRequestInterface
    {
        // TYPO3 13+ provides the request directly from ContentObjectRenderer
        return $this->cObj->getRequest();
    }

    /**
     * Find the route for a given controller and action
     *
     * @param string $controllerClass
     * @param string $actionMethod
     * @return array|null
     */
    protected function findRoute(string $controllerClass, string $actionMethod): ?array
    {
        $router = ApiRouter::getInstance();
        $routes = $router->getRoutes();

        // Find the first matching route for this controller/action combination
        foreach ($routes as $routeKey => $route) {
            if ($route['controller'] === $controllerClass && $route['action'] === $actionMethod) {
                return $route;
            }
        }

        // If no exact match found, try to find a route with just the controller
        // and use the action from FlexForm
        foreach ($routes as $routeKey => $route) {
            if ($route['controller'] === $controllerClass) {
                // Create a synthetic route with the selected action
                return [
                    'controller' => $controllerClass,
                    'action' => $actionMethod,
                    'method' => $route['method'] ?? null,
                    'path' => $route['path'] ?? '',
                    'namespace' => $route['namespace'] ?? '',
                    'templatePath' => $this->buildTemplatePath(
                        $route['namespace'] ?? '',
                        $controllerClass,
                        $actionMethod
                    )
                ];
            }
        }

        return null;
    }

    /**
     * Build template path from namespace, controller, and action
     *
     * @param string $namespace
     * @param string $controller
     * @param string $action
     * @return string
     */
    protected function buildTemplatePath(string $namespace, string $controller, string $action): string
    {
        $parts = [];
        
        // Add namespace (convert to PascalCase)
        if (!empty($namespace)) {
            $parts[] = ApiRouter::toCamelCase($namespace);
        }
        
        // Add controller name (without "Controller" suffix)
        $controllerName = $this->extractControllerName($controller);
        $parts[] = ApiRouter::toCamelCase($controllerName);
        
        // Add action (convert to PascalCase)
        if (!empty($action)) {
            $parts[] = ApiRouter::toCamelCase($action);
        }
        
        return implode('/', $parts);
    }

    /**
     * Extract controller name from fully qualified class name
     *
     * @param string $controllerClass
     * @return string
     */
    protected function extractControllerName(string $controllerClass): string
    {
        $parts = explode('\\', $controllerClass);
        $className = end($parts);
        
        // Remove "Controller" suffix if present
        if (str_ends_with($className, 'Controller')) {
            $className = substr($className, 0, -10);
        }
        
        return $className;
    }

    /**
     * Render an error message
     *
     * @param string $message
     * @param \Throwable|null $exception
     * @return string
     */
    protected function renderError(string $message, ?\Throwable $exception = null): string
    {
        $output = '<div style="border: 2px solid #ff0000; padding: 10px; margin: 10px 0; background: #ffeeee;">';
        $output .= '<strong>API Plugin Error:</strong> ';
        $output .= htmlspecialchars($message);
        
        if ($exception !== null && $GLOBALS['TYPO3_CONF_VARS']['BE']['debug']) {
            $output .= '<br><br><strong>Exception:</strong> ' . htmlspecialchars(get_class($exception));
            $output .= '<br><strong>File:</strong> ' . htmlspecialchars($exception->getFile()) . ':' . $exception->getLine();
            $output .= '<br><strong>Stack trace:</strong><pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
}