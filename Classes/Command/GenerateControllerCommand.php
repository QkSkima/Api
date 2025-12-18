<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api\Command;

use QkSkima\Api\Command\Generator\ExtensionFolderFinder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Package\PackageManager;

class GenerateControllerCommand extends Command
{
    private PackageManager $packageManager;
    private ExtensionFolderFinder $extensionFinder;

    public function __construct(
        PackageManager $packageManager,
        ExtensionFolderFinder $extensionFinder
    ) {
        $this->packageManager = $packageManager;
        $this->extensionFinder = $extensionFinder;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command generates a controller class in a selected extension.')
            ->addArgument(
                'extensionKey',
                InputArgument::OPTIONAL,
                'The extension key (e.g., qkskima_api). If not provided, you will be prompted to select one.'
            )
            ->addArgument(
                'controllerName',
                InputArgument::OPTIONAL,
                'The controller name in PascalCase without "Controller" suffix (e.g., Address, Order)'
            )
            ->addArgument(
                'actions',
                InputArgument::OPTIONAL,
                'Space-separated list of action names in snake_case (e.g., "index show edit create")'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generate Controller');

        // Step 1: Select or get extension key
        $extensionKey = $this->getExtensionKey($input, $output, $io);
        if ($extensionKey === null) {
            $io->error('No extension selected or found.');
            return Command::FAILURE;
        }

        $io->success("Selected extension: {$extensionKey}");

        // Step 2: Get controller name
        $controllerName = $this->getControllerName($input, $output, $io);
        if ($controllerName === null) {
            $io->error('No controller name provided.');
            return Command::FAILURE;
        }

        $io->success("Controller name: {$controllerName}Controller");

        // Step 3: Get action names
        $actions = $this->getActionNames($input, $output, $io);
        if (empty($actions)) {
            $io->error('No actions provided.');
            return Command::FAILURE;
        }

        $io->info(sprintf('Actions: %s', implode(', ', $actions)));

        // Step 4: Get PSR-4 namespace
        $namespace = $this->getExtensionNamespace($extensionKey);
        if ($namespace === null) {
            $io->error("Could not determine PSR-4 namespace for extension '{$extensionKey}'.");
            return Command::FAILURE;
        }

        $io->info("Namespace: {$namespace}");

        // Step 5: Generate controller class
        $success = $this->generateControllerFile($extensionKey, $controllerName, $actions, $namespace, $io);

        if ($success) {
            $io->success('Controller class generated successfully!');
            return Command::SUCCESS;
        }

        $io->error('Failed to generate controller class.');
        return Command::FAILURE;
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
     * Get controller name in PascalCase
     */
    private function getControllerName(InputInterface $input, OutputInterface $output, SymfonyStyle $io): ?string
    {
        $controllerName = $input->getArgument('controllerName');

        if (!empty($controllerName)) {
            return $this->sanitizeControllerName($controllerName);
        }

        // Interactive input
        $helper = $this->getHelper('question');
        $question = new Question('Enter controller name in PascalCase (without "Controller" suffix, e.g., Address, Order): ');
        $question->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \RuntimeException('Controller name cannot be empty.');
            }
            return $this->sanitizeControllerName($answer);
        });

        return $helper->ask($input, $output, $question);
    }

    /**
     * Get action names in snake_case
     */
    private function getActionNames(InputInterface $input, OutputInterface $output, SymfonyStyle $io): array
    {
        $actionsArg = $input->getArgument('actions');

        if (!empty($actionsArg)) {
            $actions = explode(' ', $actionsArg);
            return array_map([$this, 'sanitizeActionName'], array_filter($actions));
        }

        // Interactive input
        $helper = $this->getHelper('question');
        $question = new Question('Enter action names in snake_case separated by spaces (e.g., "index show edit create"): ');
        $question->setValidator(function ($answer) {
            if (empty($answer)) {
                throw new \RuntimeException('At least one action name is required.');
            }
            $actions = explode(' ', $answer);
            return array_map([$this, 'sanitizeActionName'], array_filter($actions));
        });

        return $helper->ask($input, $output, $question);
    }

    /**
     * Sanitize controller name to PascalCase
     */
    private function sanitizeControllerName(string $name): string
    {
        // Remove "Controller" suffix if present
        $name = preg_replace('/Controller$/i', '', $name);
        
        // Ensure PascalCase
        return ucfirst($name);
    }

    /**
     * Sanitize action name to snake_case
     */
    private function sanitizeActionName(string $name): string
    {
        // Convert to lowercase and replace non-alphanumeric characters with underscores
        $name = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
        
        // Remove consecutive underscores
        $name = preg_replace('/_+/', '_', $name);
        
        // Remove leading/trailing underscores
        return trim($name, '_');
    }

    /**
     * Get PSR-4 namespace from extension
     */
    private function getExtensionNamespace(string $extensionKey): ?string
    {
        $extensionPath = $this->extensionFinder->getExtensionPath($extensionKey);
        
        if ($extensionPath === null) {
            return null;
        }

        // Try composer.json first
        $composerJsonPath = $extensionPath . 'composer.json';
        if (file_exists($composerJsonPath)) {
            $namespace = $this->getNamespaceFromComposerJson($composerJsonPath);
            if ($namespace !== null) {
                return $namespace;
            }
        }

        // Fallback to ext_emconf.php
        $extEmconfPath = $extensionPath . 'ext_emconf.php';
        if (file_exists($extEmconfPath)) {
            $namespace = $this->getNamespaceFromExtEmconf($extEmconfPath, $extensionKey);
            if ($namespace !== null) {
                return $namespace;
            }
        }

        return null;
    }

    /**
     * Extract namespace from composer.json
     */
    private function getNamespaceFromComposerJson(string $composerJsonPath): ?string
    {
        try {
            $composerData = json_decode(file_get_contents($composerJsonPath), true);
            
            if (isset($composerData['autoload']['psr-4'])) {
                $psr4 = $composerData['autoload']['psr-4'];
                
                // Get the first PSR-4 namespace
                foreach ($psr4 as $namespace => $path) {
                    // Remove trailing backslash
                    return rtrim($namespace, '\\');
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Extract namespace from ext_emconf.php (fallback - generate from extension key)
     */
    private function getNamespaceFromExtEmconf(string $extEmconfPath, string $extensionKey): ?string
    {
        // Generate namespace from extension key
        // Convert extension_key to ExtensionKey
        $parts = explode('_', $extensionKey);
        $parts = array_map('ucfirst', $parts);
        $namespace = implode('', $parts);

        // Assume a vendor namespace (can be customized)
        return 'Vendor\\' . $namespace;
    }

    /**
     * Generate the controller file
     */
    private function generateControllerFile(
        string $extensionKey,
        string $controllerName,
        array $actions,
        string $namespace,
        SymfonyStyle $io
    ): bool {
        try {
            $extensionPath = $this->extensionFinder->getExtensionPath($extensionKey);
            
            if ($extensionPath === null) {
                $io->error("Could not find path for extension '{$extensionKey}'.");
                return false;
            }

            // Create Classes/Controller directory if it doesn't exist
            $controllerDir = $extensionPath . 'Classes/Controller/';
            if (!is_dir($controllerDir)) {
                if (!mkdir($controllerDir, 0755, true)) {
                    $io->error("Failed to create directory: {$controllerDir}");
                    return false;
                }
            }

            $controllerFile = $controllerDir . $controllerName . 'Controller.php';

            // Check if file already exists
            if (file_exists($controllerFile)) {
                $io->error("Controller file already exists: {$controllerFile}");
                return false;
            }

            // Generate controller content
            $content = $this->generateControllerContent($namespace, $controllerName, $actions);

            // Write to file
            if (file_put_contents($controllerFile, $content) === false) {
                $io->error("Failed to write file: {$controllerFile}");
                return false;
            }

            $io->success("Controller file created at: {$controllerFile}");
            return true;

        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate controller class content
     */
    private function generateControllerContent(string $namespace, string $controllerName, array $actions): string
    {
        $methods = [];
        foreach ($actions as $action) {
            $methods[] = $this->generateActionMethod($action);
        }

        $methodsCode = implode("\n\n", $methods);

        $content = <<<PHP
<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace {$namespace}\\Controller;

use Psr\Http\Message\ResponseInterface;
use QkSkima\Api\Controller\AbstractController;

class {$controllerName}Controller extends AbstractController
{
{$methodsCode}
}

PHP;

        return $content;
    }

    /**
     * Generate a single action method
     */
    private function generateActionMethod(string $actionName): string
    {
        return <<<PHP
    public function {$actionName}(): ResponseInterface
    {
        return \$this->render();
    }
PHP;
    }
}