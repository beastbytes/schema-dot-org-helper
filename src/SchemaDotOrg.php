<?php
/**
 * @copyright Copyright (c) 2022 BeastBytes - All Rights Reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\SchemaDotOrg;

use RuntimeException;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Html\Tag\Script;
use Yiisoft\Json\Json;
use Yiisoft\VarDumper\VarDumper;
use Yiisoft\View\Event\WebView\BodyEnd;
use Yiisoft\View\WebView;

/**
 * {@link https://schema.org/ Schema.org} helper methods for generating JSON-LD markup
 */
final class SchemaDotOrg
{
    /**
     * Schema.org context for JSON-LD
     */
    private const CONTEXT = 'https://schema.org';

    /**
     * Character denoting that a value is a schema.org enumeration value.
     * The value is expanded to SchemaDotOrg::CONTEXT . '/' . 'enumerationValue'
     * Usage:
    ```php
    [
    ...
    'name' => SchemaDotOrg::ENUMERATION . 'enumerationValue',
    ...
    ]
    ```
     */
    public const ENUMERATION = '@';

    /**
     * Character denoting that a value is a string literal.
     * Usage:
    ```php
    [
    ...
    'name' => SchemaDotOrg::STRING_LITERAL . 'string literal value',
    ...
    ]
    ```
     */
    public const STRING_LITERAL = ':';
    public const VIEW_PARAMETER = 'SchemaDotOrgSchemas';

    /**
     * Add a schema to the view.
     * The schema is processed on the BodyEnd event.
     *
     * @param \Yiisoft\View\WebView $view
     * @param object|array $model
     * @param array $mapping
     * @return void
     * @see \BeastBytes\SchemaDotOrg\SchemaDotOrg::handle()
     */
    public static function addSchema(WebView $view, object|array $model, array $mapping): void
    {
        /** @var array $schemas */
        $schemas = $view->hasParameter(self::VIEW_PARAMETER)
            ? $view->getParameter(self::VIEW_PARAMETER)
            : []
        ;

        $schemas[] = compact('model', 'mapping');
        $view->setParameter(self::VIEW_PARAMETER, $schemas);
    }

    /**
     * Handle the BodyEnd event.
     * Outputs the JSON-LD for schemas registered in the view.
     *
     * @param BodyEnd $event
     * @throws \JsonException
     */
    public static function handle(BodyEnd $event): void
    {
        /** @psalm-suppress InternalMethod */
        $view = $event->getView();
        if ($view->hasParameter(self::VIEW_PARAMETER)) {
            /** @var array $schema */
            foreach ($view->getParameter(self::VIEW_PARAMETER) as $schema) {
                /** @psalm-suppress MixedArgument */
                echo self::generate($schema['model'], $schema['mapping']);
            }
        }
    }

    /**
     * Returns a JSON-LD string for a schema.org type
     *
     * @param object|array $model
     * @param array $mapping
     * @return string
     * @throws \JsonException
     */
    public static function generate(object|array $model, array $mapping): string
    {
        return Script::tag()
            ->content(
                Json::encode(
                    array_merge(
                        ['@context' => self::CONTEXT],
                        self::jsonLD($model, $mapping)
                    )
                )
            )
            ->type('application/ld+json')
            ->render()
        ;
    }

    /**
     * Returns an array of JSON-LD for a schema.org type
     *
     * @param object|array $model
     * @param array $mapping
     * @return array
     */
    private static function jsonLD(object|array $model, array $mapping): array
    {
        $jsonLD = [];

        /**
         * @var int|string $key
         * @var mixed $value
         */
        foreach ($mapping as $key => $value) {
            if (is_string($key)) {
                if (preg_match('/^[A-Z]/', $key)) {
                    if (isset($value[0]) && is_array($value[0])) { // array of types of the type $key
                        $types = [];

                        /** @var array $type */
                        foreach ($value as $type) {
                            $types[] = array_merge(
                                ['@type' => $key],
                                self::jsonLD($model, $type)
                            );
                        }

                        return $types;
                    }

                    /** @var array $value */
                    return array_merge(
                        ['@type' => $key],
                        self::jsonLD($model, $value)
                    );
                }
            } elseif (is_string($value)) {
                $ary = explode('.', $value);
                $key = array_pop($ary);
            }

            /** @psalm-suppress MixedAssignment */
            $jsonLD[$key] = match(gettype($value)) {
                'array' => self::jsonLD($model, $value),
                'boolean', 'double', 'integer' => $value,
                'string' => match($value[0]) {
                    self::STRING_LITERAL => substr($value, 1),
                    self::ENUMERATION => self::CONTEXT . '/' . substr($value, 1),
                    default => ArrayHelper::getValueByPath($model, $value)
                },
                /** @psalm-suppress ArgumentTypeCoercion */
                'object' => ArrayHelper::getValueByPath($model, $value), // Closure
                default => throw new RuntimeException(strtr(
                    'Invalid mapping type `{type}` for `{key}`',
                    ['{type}' => gettype($value), '{key}' => $key]
                ))
            };
        }

        return $jsonLD;
    }
}
