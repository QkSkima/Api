<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api\Command\Generator;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Package\PackageManager;

class ExtensionFolderFinder
{
    private PackageManager $packageManager;

    public function __construct(PackageManager $packageManager)
    {
        $this->packageManager = $packageManager;
    }

    /**
     * Get list of local extensions
     * In Composer mode: checks composer.json repositories with type 'path'
     * In non-Composer mode: checks public/typo3conf/ext/
     *
     * @return array<string, string> Array of extension keys
     */
    public function getLocalExtensions(): array
    {
        $extensions = [];
        
        if ($this->isComposerMode()) {
            // Composer mode: Read from composer.json repositories
            $extensions = $this->getComposerLocalExtensions();
        } else {
            // Classic mode: Read from typo3conf/ext/
            $extensions = $this->getClassicModeExtensions();
        }
        
        return $extensions;
    }

    /**
     * Check if TYPO3 is running in Composer mode
     *
     * @return bool
     */
    public function isComposerMode(): bool
    {
        return Environment::isComposerMode();
    }

    /**
     * Get local extensions from composer.json repositories
     *
     * @return array<string, string>
     */
    public function getComposerLocalExtensions(): array
    {
        $extensions = [];
        $projectRoot = Environment::getProjectPath();
        $composerJsonPath = $projectRoot . '/composer.json';
        
        if (!file_exists($composerJsonPath)) {
            return $extensions;
        }
        
        try {
            $composerJson = json_decode(file_get_contents($composerJsonPath), true);
            
            if (!isset($composerJson['repositories']) || !is_array($composerJson['repositories'])) {
                return $extensions;
            }
            
            // Find all 'path' type repositories
            foreach ($composerJson['repositories'] as $repository) {
                if (!is_array($repository) || ($repository['type'] ?? '') !== 'path') {
                    continue;
                }
                
                $url = $repository['url'] ?? '';
                if (empty($url)) {
                    continue;
                }
                
                // Resolve the path pattern (e.g., ./packages/*)
                $pathPattern = $projectRoot . '/' . ltrim($url, './');
                
                // Handle wildcard patterns
                if (str_contains($pathPattern, '*')) {
                    $paths = glob($pathPattern, GLOB_ONLYDIR);
                } else {
                    $paths = is_dir($pathPattern) ? [$pathPattern] : [];
                }
                
                // Check each path for valid extensions
                foreach ($paths as $path) {
                    $extensionKey = $this->extractExtensionKeyFromPath($path);
                    
                    if ($extensionKey !== null && $this->packageManager->isPackageAvailable($extensionKey)) {
                        $extensions[$extensionKey] = $extensionKey;
                    }
                }
            }
            
        } catch (\Exception $e) {
            // Silently fail and return empty array
            return [];
        }
        
        return $extensions;
    }

    /**
     * Get local extensions from typo3conf/ext/ (classic mode)
     *
     * @return array<string, string>
     */
    public function getClassicModeExtensions(): array
    {
        $extensions = [];
        $extPath = Environment::getPublicPath() . '/typo3conf/ext/';
        
        if (!is_dir($extPath)) {
            return $extensions;
        }
        
        $directories = scandir($extPath);
        
        foreach ($directories as $directory) {
            if ($directory === '.' || $directory === '..') {
                continue;
            }
            
            $fullPath = $extPath . $directory;
            
            if (!is_dir($fullPath)) {
                continue;
            }
            
            // Check if it's a valid extension (has ext_emconf.php or composer.json)
            if (!file_exists($fullPath . '/ext_emconf.php') && !file_exists($fullPath . '/composer.json')) {
                continue;
            }
            
            // Use directory name as extension key
            $extensionKey = $directory;
            
            if ($this->packageManager->isPackageAvailable($extensionKey)) {
                $extensions[$extensionKey] = $extensionKey;
            }
        }
        
        return $extensions;
    }

    /**
     * Extract extension key from a path
     * Tries to read composer.json or use directory name
     *
     * @param string $path
     * @return string|null
     */
    public function extractExtensionKeyFromPath(string $path): ?string
    {
        if (!is_dir($path)) {
            return null;
        }
        
        // Try to get extension key from composer.json
        $composerJsonPath = $path . '/composer.json';
        if (file_exists($composerJsonPath)) {
            try {
                $composerData = json_decode(file_get_contents($composerJsonPath), true);
                
                // Extract extension key from package name (e.g., "vendor/extension-key" -> "extension_key")
                if (isset($composerData['name'])) {
                    $parts = explode('/', $composerData['name']);
                    $extensionKey = end($parts);
                    
                    // Convert hyphens to underscores (TYPO3 convention)
                    $extensionKey = str_replace('-', '_', $extensionKey);
                    
                    return $extensionKey;
                }
                
                // Check for TYPO3 extra configuration
                if (isset($composerData['extra']['typo3/cms']['extension-key'])) {
                    return $composerData['extra']['typo3/cms']['extension-key'];
                }
            } catch (\Exception $e) {
                // Fall through to directory name
            }
        }
        
        // Fallback: use directory name as extension key
        $extensionKey = basename($path);
        
        // Convert hyphens to underscores
        $extensionKey = str_replace('-', '_', $extensionKey);
        
        return $extensionKey;
    }

    /**
     * Get the full path to an extension
     *
     * @param string $extensionKey
     * @return string|null
     */
    public function getExtensionPath(string $extensionKey): ?string
    {
        if (!$this->packageManager->isPackageAvailable($extensionKey)) {
            return null;
        }

        try {
            $package = $this->packageManager->getPackage($extensionKey);
            return $package->getPackagePath();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if an extension is a local extension (not from vendor)
     *
     * @param string $extensionKey
     * @return bool
     */
    public function isLocalExtension(string $extensionKey): bool
    {
        $path = $this->getExtensionPath($extensionKey);
        
        if ($path === null) {
            return false;
        }

        // Extension is local if it's not in sysext or vendor
        return !str_contains($path, '/typo3/sysext/') && !str_contains($path, '/vendor/');
    }
}