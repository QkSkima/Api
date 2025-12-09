<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Api\Controller\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class BeforeFilter
{
    public function __construct(
        public string $method,
        public array $only = [],
        public array $except = []
    ) {}
}
