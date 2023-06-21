<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

use BeastBytes\SchemaDotOrg\SchemaDotOrg;
use Yiisoft\View\Event\WebView\BodyEnd;

return [
    BodyEnd::class => [
        [SchemaDotOrg::class, 'handle']
    ]
];
