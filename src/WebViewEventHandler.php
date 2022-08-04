<?php
/**
 * @copyright Copyright (c) 2022 BeastBytes - All Rights Reserved
 */

declare(strict_types=1);

namespace BeastBytes\SchemaDotOrg;

use Yiisoft\View\Event\WebView\WebViewEvent;

class WebViewEventHandler
{
    public function handle(WebViewEvent $event): void
    {
        $view = $event->getView();
        if ($view->hasParameter('schemaDotOrg')) {
            /**
             * @var array $schema
             * @var array|object $model
             * @psalm-suppress MixedArgument
             */
            extract($view->getParameter('schemaDotOrg'), EXTR_OVERWRITE);
            echo SchemaDotOrg::jsonLD($schema, $model);
        }
    }
}
