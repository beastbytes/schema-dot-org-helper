<?php
/**
 * @copyright Copyright (c) 2022 BeastBytes - All Rights Reserved
 */

declare(strict_types=1);

namespace BeastBytes\SchemaDotOrg;

use Yiisoft\View\Event\WebView\WebViewEvent;

final class WebViewEventHandler
{
    public function handle(WebViewEvent $event): void
    {
        /** @psalm-suppress InternalMethod */
        $view = $event->getView();
        if ($view->hasParameter('schema')) {
            /** @var array $schema */
            foreach ($view->getParameter('schema') as $schema) {
                /**
                 * @var array $mapping
                 * @var array|object $model
                 * @psalm-suppress MixedArgument
                 */
                extract($schema, EXTR_OVERWRITE);
                echo SchemaDotOrg::generate($mapping, $model);
            }
        }
    }
}
