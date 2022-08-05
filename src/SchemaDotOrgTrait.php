<?php
/**
 * @copyright Copyright © 2022 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\SchemaDotOrg;

/**
 * Use SchemaDotOrgTrait in a view
 */
trait SchemaDotOrgTrait
{
    public function addSchema(array $mapping, $model): void
    {
        /** @var \Yiisoft\View\WebView $this */
        $schemas = $this->hasParameter('schemaDotOrg')
            ? $this->getParameter('schemaDotOrg')
            : []
        ;

        $schemas[] = compact('mapping', 'model');
        $this->setParameter('schemaDotOrg', $schemas);
    }
}