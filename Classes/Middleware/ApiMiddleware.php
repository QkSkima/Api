<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use QkSkima\Api\ApiRouter;
use QkSkima\Api\Routes\EndpointMatcher;

class ApiMiddleware implements MiddlewareInterface
{

    public function __construct(
        protected readonly EndpointMatcher $matcher
    )
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // If no API route matches, pass the request to next handler
        if (!$this->matcher->match($request->getUri()->getPath())) {
            return $handler->handle($request);
        }

        $version = $this->matcher->getApiVersion();
        $endpoint = $this->matcher->getEndpointId();

        $router = ApiRouter::getInstance();
        $route = $router->resolve($version, $endpoint);

        if ($route === null || !class_exists($route['controller'])) {
            // Route not registered or controller missing
            return $handler->handle($request);
        }

        $controllerClass = $route['controller'];
        $actionMethod = $route['action'] ?? 'ingress'; // default fallback to ingress

        // Instantiate controller
        $controller = GeneralUtility::makeInstance($controllerClass, $request, $handler, $version, $endpoint);

        // Call the action dynamically
        return $controller->callAction($controllerClass, $actionMethod);
    }
}