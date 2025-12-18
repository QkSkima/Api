<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api\Command;

use QkSkima\Api\ApiRouter;
use QkSkima\Api\Command\Generator\ExtensionFolderFinder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Site\SiteFinder;

class GenerateRoutesCommand extends Command
{
    private PackageManager $packageManager;
    private SiteFinder $siteFinder;
    private ExtensionFolderFinder $extensionFinder;

    public function __construct(
        PackageManager $packageManager,
        SiteFinder $siteFinder,
        ExtensionFolderFinder $extensionFinder
    ) {
        $this->packageManager = $packageManager;
        $this->siteFinder = $siteFinder;
        $this->extensionFinder = $extensionFinder;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command generates a routes.yaml file for a selected extension based on all ApiRouter routes and adds it to the site configuration.')
            ->addArgument(
                'extensionKey',
                InputArgument::OPTIONAL,
                'The extension key (e.g., qkskima_api). If not provided, you will be prompted to select one.'
            )
            ->addOption(
                'site',
                's',
                InputOption::VALUE_REQUIRED,
                'The site identifier (e.g., main). If not provided, you will be prompted to select one.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generate Routes Configuration');

        // Step 1: Select or get extension key
        $extensionKey = $this->getExtensionKey($input, $output, $io);
        if ($extensionKey === null) {
            $io->error('No extension selected or found.');
            return Command::FAILURE;
        }

        $io->success("Selected extension: {$extensionKey}");

        // Step 2: Select or get site identifier
        $siteIdentifier = $this->getSiteIdentifier($input, $output, $io);
        if ($siteIdentifier === null) {
            $io->error('No site selected or found.');
            return Command::FAILURE;
        }

        $io->success("Selected site: {$siteIdentifier}");

        // Step 3: Get all available routes from ApiRouter
        $availableRoutes = $this->getAllRoutes();
        if (empty($availableRoutes)) {
            $io->error('No routes found in ApiRouter.');
            return Command::FAILURE;
        }

        $io->info(sprintf('Found %d route(s) to generate.', count($availableRoutes)));

        // Step 4: Generate routes.yaml content for all routes
        $routesConfig = $this->generateRoutesConfiguration($availableRoutes);

        // Step 5: Write routes.yaml to extension folder
        $routesFilePath = $this->writeRoutesFile($extensionKey, $routesConfig, $io);
        if ($routesFilePath === null) {
            $io->error('Failed to write routes configuration file.');
            return Command::FAILURE;
        }

        $io->success('Routes configuration file generated successfully!');

        // Step 6: Add import to site configuration
        $importAdded = $this->addImportToSiteConfig($siteIdentifier, $extensionKey, $io);
        if ($importAdded) {
            $io->success('Import statement added to site configuration!');
            $io->note('Remember to clear caches for the routes to take effect: ./vendor/bin/typo3 cache:flush');
        } else {
            $io->warning('Failed to add import to site configuration. You may need to add it manually.');
            $io->note("Add this to your site config.yaml imports section:");
            $io->text("  - { resource: \"EXT:{$extensionKey}/Configuration/Routes/routes.yaml\" }");
        }

        return Command::SUCCESS;
    }

    /**
     * Get extension key either from argument or interactive selection
     */
    private function getExtensionKey(InputInterface $input, OutputInterface $output, SymfonyStyle $io): ?string
    {
        $extensionKey = $input->getArgument('extensionKey');

        if (!empty($extensionKey)) {
            // Validate that the extension exists
            if ($this->packageManager->isPackageAvailable($extensionKey)) {
                return $extensionKey;
            }
            $io->error("Extension '{$extensionKey}' not found.");
            return null;
        }

        // Interactive selection
        $extensions = $this->extensionFinder->getLocalExtensions();
        if (empty($extensions)) {
            $io->error('No local extensions found.');
            return null;
        }

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'Please select an extension:',
            $extensions
        );
        $question->setErrorMessage('Extension %s is invalid.');

        $selectedExtension = $helper->ask($input, $output, $question);
        return $selectedExtension;
    }

    /**
     * Get site identifier either from option or interactive selection
     */
    private function getSiteIdentifier(InputInterface $input, OutputInterface $output, SymfonyStyle $io): ?string
    {
        $siteIdentifier = $input->getOption('site');

        if (!empty($siteIdentifier)) {
            // Validate that the site exists
            try {
                $this->siteFinder->getSiteByIdentifier($siteIdentifier);
                return $siteIdentifier;
            } catch (\Exception $e) {
                $io->error("Site '{$siteIdentifier}' not found.");
                return null;
            }
        }

        // Interactive selection
        $sites = $this->getAvailableSites();
        if (empty($sites)) {
            $io->error('No sites found.');
            return null;
        }

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'Please select a site:',
            $sites
        );
        $question->setErrorMessage('Site %s is invalid.');

        $selectedSite = $helper->ask($input, $output, $question);
        return $selectedSite;
    }

    /**
     * Get list of available sites
     */
    private function getAvailableSites(): array
    {
        $sites = [];
        $allSites = $this->siteFinder->getAllSites();

        foreach ($allSites as $site) {
            $identifier = $site->getIdentifier();
            $sites[$identifier] = sprintf(
                '%s (%s)',
                $identifier,
                $site->getBase()->getHost()
            );
        }

        return $sites;
    }

    /**
     * Get all routes from ApiRouter
     */
    private function getAllRoutes(): array
    {
        $router = ApiRouter::getInstance();
        $routes = $router->getRoutes();

        $allRoutes = [];
        foreach ($routes as $routeKey => $route) {
            $allRoutes[] = $route;
        }

        return $allRoutes;
    }

    /**
     * Generate routes.yaml configuration from all routes
     */
    private function generateRoutesConfiguration(array $selectedRoutes): array
    {
        $config = ['routeEnhancers' => []];

        foreach ($selectedRoutes as $route) {
            $path = $route['path'] ?? '';
            $routeName = $this->generateRouteName($path);

            // Parse parameters from path (e.g., :id, :user_id)
            $parameters = $this->extractParameters($path);
            
            // Convert Rails-style path to TYPO3 route path
            $typo3Path = $this->convertToTypo3Path($path);

            $routeConfig = [
                'type' => 'Plugin',
                'routePath' => $typo3Path,
                'namespace' => 'tx_qkskimaapi_pi1'
            ];

            // Add requirements for each parameter
            if (!empty($parameters)) {
                $routeConfig['requirements'] = [];
                foreach ($parameters as $param) {
                    $routeConfig['requirements'][$param] = '.+'; // Match any non-empty value
                }
            }

            $config['routeEnhancers'][$routeName] = $routeConfig;
        }

        return $config;
    }

    /**
     * Generate a route name from path
     * Example: /api/v1/orders/:id -> api_v1_orders_show
     */
    private function generateRouteName(string $path): string
    {
        // Remove leading/trailing slashes
        $path = trim($path, '/');
        
        // Remove parameter placeholders and replace with generic names
        $path = preg_replace('/:\w+/', 'show', $path);
        
        // Replace slashes and special characters with underscores
        $name = preg_replace('/[^a-zA-Z0-9]+/', '_', $path);
        
        // Remove consecutive underscores
        $name = preg_replace('/_+/', '_', $name);
        
        // Convert to lowercase
        $name = strtolower($name);
        
        // Remove trailing underscores
        $name = trim($name, '_');
        
        return $name ?: 'route_' . md5($path);
    }

    /**
     * Extract parameters from path
     * Example: /api/v1/orders/:id/items/:item_id -> ['id', 'item_id']
     */
    private function extractParameters(string $path): array
    {
        $parameters = [];
        
        // Match :parameter_name pattern
        if (preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $path, $matches)) {
            $parameters = $matches[1];
        }
        
        return $parameters;
    }

    /**
     * Convert Rails-style path to TYPO3 route path
     * Example: /api/v1/orders/:id -> /api/v1/orders/{id}
     */
    private function convertToTypo3Path(string $path): string
    {
        // Replace :parameter with {parameter}
        return preg_replace('/:([a-zA-Z_][a-zA-Z0-9_]*)/', '{$1}', $path);
    }

    /**
     * Write routes.yaml file to extension's Configuration/Routes/ directory
     */
    private function writeRoutesFile(string $extensionKey, array $routesConfig, SymfonyStyle $io): ?string
    {
        try {
            $package = $this->packageManager->getPackage($extensionKey);
            $packagePath = $package->getPackagePath();
            
            // Create Configuration/Routes directory if it doesn't exist
            $routesDir = $packagePath . 'Configuration/Routes/';
            if (!is_dir($routesDir)) {
                if (!mkdir($routesDir, 0755, true)) {
                    $io->error("Failed to create directory: {$routesDir}");
                    return null;
                }
            }

            $routesFile = $routesDir . 'routes.yaml';
            
            // Check if file already exists
            if (file_exists($routesFile)) {
                $io->warning("File already exists: {$routesFile}");
                $io->note('Operation cancelled. Remove the file and restart generation.');
                return null;
            }

            // Generate YAML content
            $yamlContent = Yaml::dump($routesConfig, 6, 2);
            
            // Add header comment
            $header = <<<YAML
# Generated by qkskima:generate-routes command
# Extension: {$extensionKey}
# Generated at: %s

YAML;
            $header = sprintf($header, date('Y-m-d H:i:s'));
            $yamlContent = $header . $yamlContent;

            // Write to file
            if (file_put_contents($routesFile, $yamlContent) === false) {
                $io->error("Failed to write file: {$routesFile}");
                return null;
            }

            $io->success("Routes file created at: {$routesFile}");
            
            return $routesFile;
            
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Add import statement to site configuration
     */
    private function addImportToSiteConfig(string $siteIdentifier, string $extensionKey, SymfonyStyle $io): bool
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
            $configFile = Environment::getConfigPath() . '/sites/' . $siteIdentifier . '/config.yaml';

            if (!file_exists($configFile)) {
                $io->error("Site configuration file not found: {$configFile}");
                return false;
            }

            // Read existing configuration
            $existingConfig = Yaml::parseFile($configFile);

            // Initialize imports array if it doesn't exist
            if (!isset($existingConfig['imports'])) {
                $existingConfig['imports'] = [];
            }

            // Build the import resource string
            $importResource = "EXT:{$extensionKey}/Configuration/Routes/routes.yaml";

            // Check if import already exists
            $importExists = false;
            foreach ($existingConfig['imports'] as $import) {
                if (isset($import['resource']) && $import['resource'] === $importResource) {
                    $importExists = true;
                    break;
                }
            }

            if ($importExists) {
                $io->note("Import statement already exists in site configuration.");
                return true;
            }

            // Add the import
            $existingConfig['imports'][] = ['resource' => $importResource];

            // Write back to file
            $yamlContent = Yaml::dump($existingConfig, 10, 2);
            
            if (file_put_contents($configFile, $yamlContent) === false) {
                $io->error("Failed to write site configuration file: {$configFile}");
                return false;
            }

            return true;

        } catch (\Exception $e) {
            $io->error('Error updating site configuration: ' . $e->getMessage());
            return false;
        }
    }
}