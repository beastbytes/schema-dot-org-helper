<?php
/**
 * @copyright Copyright (c) 2023 BeastBytes - All Rights Reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\SchemaDotOrg;

use JsonException;
use RuntimeException;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Html\Tag\Script;
use Yiisoft\Json\Json;
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
     * Character denoting that a mapped value is an array of properties.
     * The model's property must be iterable; the mapped type and its mapping definition is applied to all elements of
     * the model's property
     * Usage:
     ```php
     $model = [
         ...
         'iterableProperty' = [
            ['p0' => 'v0', 'p1' => 'v1', ..., 'pn' => 'vn'],
            ['p0' => 'v0', 'p1' => 'v1', ..., 'pn' => 'vn'],
            ....
            ['p0' => 'v0', 'p1' => 'v1', ..., 'pn' => 'vn'],
         ],
         ....
     ];
    $mapping = [
        ...
        'sdoProperty' => [ // The Schema dot Org property maps to ...
            '[iterableProperty' => [ // iterableProperty in the model
                'sdoType' => [ // The type and its mapping are applied to all elements of iterableProperty
                    'sdoP0' => 'p0', // The Schema dot Org sdoP0 property maps to the element p0 property
                    'p1', // The Schema dot Org p1 property maps to the element p1 property
                    ...
                    'pn'
                ]
            ],
        ],
        ...
    ];
     ```
     * If the Schema dot Org property and model property have the same name the property name can be omitted from the
     * mapping definition:
    ```php
    $mapping = [
        ...
        'iterableProperty' => [ // The Schema dot Org property has the same name as the model property ...
            '[' => [ // so can be omitted
                'sdoType' => [ // The type and its mapping are applied to all elements of iterableProperty
                    'sdoP0' => 'p0', // The Schema dot Org sdoP0 property maps to the element p0 property
                    'p1', // The Schema dot Org p1 property maps to the element p1 property
                    ...
                    'pn'
                ]
            ],
        ],
        ...
    ];
    ```
     */
    public const ARRAY = '[';

    /**
     * Character denoting that a mapped value is a schema.org enumeration value.
     * The value is expanded to SchemaDotOrg::CONTEXT . '/' . 'enumerationValue'
     * Usage:
    ```php
    $mapping = [
        ...
        'sdoProperty' => SchemaDotOrg::ENUMERATION . 'enumerationValue',
        ...
    ];
    ```
     */
    public const ENUMERATION = '@';

    /**
     * Character denoting that a mapped value is a string literal.
     * Usage:
    ```php
    $mapping = [
        ...
        'sdoProperty' => SchemaDotOrg::STRING_LITERAL . 'string literal value',
        ...
    ];
    ```
     */
    public const STRING_LITERAL = ':';

    /**
     * Add a schema to the view.
     * The schema is processed on the BodyEnd event.
     *
     * @param WebView $view
     * @param object|array $model
     * @param array $mapping
     * @return void
     * @see \BeastBytes\SchemaDotOrg\SchemaDotOrg::handle()
     */
    public static function addSchema(WebView $view, object|array $model, array $mapping): void
    {
        /** @var array $schemas */
        $schemas = $view->hasParameter(self::class)
            ? $view->getParameter(self::class)
            : []
        ;

        $schemas[] = compact('model', 'mapping');
        $view->setParameter(self::class, $schemas);
    }

    /**
     * Handle the BodyEnd event.
     * Outputs the JSON-LD for schemas registered in the view.
     *
     * @param BodyEnd $event
     * @throws JsonException
     */
    public static function handle(BodyEnd $event): void
    {
        /** @psalm-suppress InternalMethod */
        $view = $event->getView();
        if ($view->hasParameter(self::class)) {
            /** @var array $schema */
            foreach ($view->getParameter(self::class) as $schema) {
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
     * @throws JsonException
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
     * @param string|null $k property key for lists
     * @return array
     */
    private static function jsonLD(object|array $model, array $mapping, ?string $k = null): array
    {
        $jsonLD = [];

        /**
         * @var int|string $key
         * @var mixed $map
         */
        foreach ($mapping as $key => $map) {
            if (is_string($key)) {
                if ($key[0] === self::ARRAY) { // array of types
                    $property = strlen($key) === 1 ? $k : substr($key, 1);
                    $type = array_key_first($mapping[$key]);
                    /** @var array $values */
                    $values = ArrayHelper::getValueByPath($model, $property);

                    foreach ($values as $value) {
                        $jsonLD[] = array_merge(
                            ['@type' => $type],
                            self::jsonLD($value, $mapping[$key][$type])
                        );
                    }

                    return $jsonLD;
                }

                if (preg_match('/^[A-Z]/', $key)) {
                    /** @var array $map */
                    return array_merge(
                        ['@type' => $key],
                        self::jsonLD($model, $map)
                    );
                }
            } elseif (is_string($map)) {
                $ary = explode('.', $map);
                $key = array_pop($ary);
            }

            /** @psalm-suppress MixedAssignment */
            $jsonLD[$key] = match(gettype($map)) {
                'array' => self::jsonLD($model, $map, $key),
                'boolean', 'double', 'integer' => $map,
                'string' => match($map[0]) {
                    self::STRING_LITERAL => substr($map, 1),
                    self::ENUMERATION => self::CONTEXT . '/' . substr($map, 1),
                    default => ArrayHelper::getValueByPath($model, $map)
                },
                /** @psalm-suppress ArgumentTypeCoercion */
                'object' => ArrayHelper::getValueByPath($model, $map), // Closure
                default => throw new RuntimeException(strtr(
                    'Invalid mapping type `{type}` for `{key}`',
                    ['{type}' => gettype($map), '{key}' => $key]
                ))
            };
        }

        return $jsonLD;
    }
}
