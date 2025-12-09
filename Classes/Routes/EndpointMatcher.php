<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api\Routes;

use InvalidArgumentException;

class EndpointMatcher
{
    private string $apiVersion = '';
    private string $endpointId = '';

    /**
     * Parse the path and extract version and endpoint
     *
     * @param string $path Full request path (e.g. /api/v1/users/list)
     * @return bool True if matched, false otherwise
     * @throws InvalidArgumentException If unsafe characters are detected
     */
    public function match(string $path): bool
    {
        $path = trim($path, '/');

        // Match /api/{version}/{everything_else_as_endpoint}
        if (!preg_match('#^api/([^/]+)/(.+)$#', $path, $matches)) {
            return false;
        }

        $version = $matches[1];
        $endpoint = $matches[2];

        // Sanitize version
        if (!$this->isValidSegment($version)) {
            throw new InvalidArgumentException('Unsafe characters in API version.');
        }

        // Endpoint can include slashes, validate each segment
        foreach (explode('/', $endpoint) as $segment) {
            if (!$this->isValidSegment($segment)) {
                throw new InvalidArgumentException('Unsafe characters in endpoint segment.');
            }
        }

        $this->apiVersion = $version;
        $this->endpointId = $endpoint;

        return true;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    public function getEndpointId(): string
    {
        return $this->endpointId;
    }

    /**
     * Validate a single path segment
     * Only allow letters, numbers, underscore, and dash.
     */
    private function isValidSegment(string $segment): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $segment) === 1;
    }

    /**
     * Convert string to camel case
     */
    public static function toCamelCase(string $string): string
    {
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        return str_replace(' ', '', $string);
    }
}
