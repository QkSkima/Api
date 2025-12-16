<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api\FlexForm;

use QkSkima\Api\ApiRouter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ActionItemsProvider
{
    /**
     * itemsProcFunc for FlexForm "action" select field
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
    public function getActions(array &$config): void
    {
        /*
         * TYPO3 expects:
         * $config['items'][] = [
         *     'Label shown in BE',
         *     'stored_value'
         * ];
         */

        // Read FlexForm array
        $flexFormData = $config['flexParentDatabaseRow']['pi_flexform'] ?? [];

        // Extract selected controller from sheet 'general', field 'controller'
        // FlexForm structure: data -> [sheetName] -> lDEF -> [fieldName] -> vDEF
        $selectedController =
            $flexFormData['data']['general']['lDEF']['controller']['vDEF']
                ?? null;

        // If no controller is selected, show no actions
        // (The displayCond in FlexForm already handles this, but good to be safe)
        if (empty($selectedController)) {
            return;
        }

        // Get ApiRouter instance and fetch all registered routes
        $router = ApiRouter::getInstance();
        $routes = $router->getRoutes();

        // Find all actions for the selected controller
        $actions = [];
        foreach ($routes as $routeKey => $route) {
            if (($route['controller'] ?? '') === $selectedController) {
                $actionName = $route['action'] ?? 'index';
                $httpMethod = $route['method'] ?? 'ANY';
                
                // Group actions by name and collect their HTTP methods
                if (!isset($actions[$actionName])) {
                    $actions[$actionName] = [];
                }
                $actions[$actionName][] = $httpMethod;
            }
        }

        // If no actions found for the controller, return early
        if (empty($actions)) {
            return;
        }

        // Sort actions alphabetically
        ksort($actions);

        // Populate items array with HTTP verb prefix
        foreach ($actions as $actionName => $httpMethods) {
            // Remove duplicates and sort HTTP methods
            $httpMethods = array_unique($httpMethods);
            sort($httpMethods);
            
            // Format: "GET, POST: index" or "ANY: index"
            $methodsLabel = implode(', ', $httpMethods);
            $label = sprintf('%s: %s', $methodsLabel, $actionName);
            
            $config['items'][] = [
                $label,         // Label shown in BE (e.g., "GET: index")
                $actionName,    // Stored value (just the action name)
            ];
        }
    }
}