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
        if ($view->hasParameter(SchemaDotOrg::VIEW_PARAMETER)) {
            /** @var array $schema */
            foreach ($view->getParameter(SchemaDotOrg::VIEW_PARAMETER) as $schema) {
                extract($schema, EXTR_OVERWRITE);
                /**
                 * @var array $mapping
                 * @var array|object $model
                 * @psalm-suppress MixedArgument
                 */
                echo SchemaDotOrg::generate($mapping, $model);
            }
        }
    }
}
