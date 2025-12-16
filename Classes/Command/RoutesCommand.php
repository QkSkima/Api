<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api\Command;

use QkSkima\Api\ApiRouter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to list all registered API routes
 */
class RoutesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('List all registered API routes')
            ->setHelp('This command displays all registered API routes with their HTTP methods, paths, controllers, actions, and template paths.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Registered API Routes');

        $router = ApiRouter::getInstance();
        $routes = $router->getRoutes();

        if (empty($routes)) {
            $io->warning('No routes registered.');
            return Command::SUCCESS;
        }

        // Prepare table data
        $tableData = [];
        foreach ($routes as $key => $route) {
            $method = $route['method'] ?? 'ANY';
            $path = $route['path'] ?? $key;
            $controller = $this->formatClassName($route['controller']);
            $action = $route['action'] ?? '-';
            $templatePath = $route['templatePath'] ?? '-';

            $tableData[] = [
                $method,
                '/' . $path,
                $controller,
                $action,
                $templatePath
            ];
        }

        // Sort by path for better readability
        usort($tableData, function ($a, $b) {
            return strcmp($a[1], $b[1]);
        });

        // Create and render table
        $table = new Table($output);
        $table
            ->setHeaders(['Method', 'Path', 'Controller', 'Action', 'Template Path'])
            ->setRows($tableData);

        $table->render();

        $io->success(sprintf('Found %d registered route(s).', count($routes)));

        return Command::SUCCESS;
    }

    /**
     * Format class name to show only the class name without namespace
     *
     * @param string $className
     * @return string
     */
    private function formatClassName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }
}