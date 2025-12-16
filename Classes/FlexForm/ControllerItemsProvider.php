<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api\FlexForm;

use QkSkima\Api\ApiRouter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ControllerItemsProvider
{
    /**
     * itemsProcFunc for FlexForm "controller" select field
     *
     * @param array $config
     *   Expected keys:
     *   - items: array<int, array{0:string,1:string}>
     *   - row: tt_content row
     *   - field: field name
     *   - flexParentDatabaseRow: full tt_content row
     *   - flexParentDatabaseRow['pi_flexform']: raw XML
     *
     * @return void
     */
    public function getControllers(array &$config): void
    {
        /*
         * TYPO3 expects:
         * $config['items'][] = [
         *     'Label shown in BE',
         *     'stored_value'
         * ];
         */

        // Get ApiRouter instance and fetch all registered routes
        $router = ApiRouter::getInstance();
        $routes = $router->getRoutes();

        // Extract unique controllers from routes
        $controllers = [];
        foreach ($routes as $route) {
            if (isset($route['controller'])) {
                $controllerClass = $route['controller'];
                
                // Extract the controller name (without namespace and "Controller" suffix)
                $controllerName = $this->extractControllerName($controllerClass);
                
                // Store both the display name and the full class name
                // Using the class name as key ensures uniqueness
                $controllers[$controllerClass] = $controllerName;
            }
        }

        // Sort by display name for better UX
        asort($controllers);

        // Populate items array
        foreach ($controllers as $fullClass => $displayName) {
            $config['items'][] = [
                $displayName,           // Label shown in BE
                $fullClass,            // Stored value (full class name)
            ];
        }
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
}