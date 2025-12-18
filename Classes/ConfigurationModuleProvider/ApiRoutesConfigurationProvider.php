<?php

declare(strict_types=1);

namespace QkSkima\Api\ConfigurationModuleProvider;

use QkSkima\Api\ApiRouter;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Lowlevel\ConfigurationModuleProvider\AbstractProvider;

final class ApiRoutesConfigurationProvider extends AbstractProvider
{
    public function getConfiguration(): array
    {
        $router = ApiRouter::getInstance();
        $routes = $router->getRoutes();

        $configuration = [];

        foreach ($routes as $key => $route) {
            [$method, $path] = $this->splitMethodAndPath($key);

            // Human-readable configuration key
            $label = sprintf(
                '%s %s',
                $method,
                $path
            );

            $configuration[$label] = [
                'path'         => $path,
                'method'       => $route['method'],
                'controller'   => $route['controller'],
                'action'       => $route['action'],
                'namespace'    => $route['namespace'],
                'templatePath' => $route['templatePath'],
            ];
        }

        ArrayUtility::naturalKeySortRecursive($configuration);
        return $configuration;
    }

    private function splitMethodAndPath(string $key): array
    {
        if (str_contains($key, ':')) {
            [$method, $path] = explode(':', $key, 2);
            return [$method, $path];
        }

        return ['ANY', $key];
    }
}
