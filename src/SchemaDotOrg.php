<?php
/**
 * @copyright Copyright (c) 2022 BeastBytes - All Rights Reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\SchemaDotOrg;

use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Html\Tag\Script;
use Yiisoft\Json\Json;

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

    /**
     * Returns a JSON-LD schema for a model
     *
     * @param array $mapping Schema.org mapping definition
     * @param array|Object $model Data model
     * @return string JSON-LD format schema
     * @throws \Exception
     */
    public static function generate(array $mapping, $model = []): string
    {
        return Script::tag()
            ->content(
                Json::encode(
                    array_merge(
                        ['@context' => self::CONTEXT],
                        self::jsonLD($mapping, $model)
                    )
                )
            )
            ->type('application/ld+json')
            ->render();
    }

    /**
     * Returns an array of JSON-LD for a schema.org type
     *
     * @param array $mapping
     * @param array|Object $model
     * @return array
     * @throws \Exception
     */
    private static function jsonLD(array $mapping, $model): array
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
                                self::jsonLD($type, $model)
                            );
                        }
                        
                        return $types;
                    }

                    /** @var array $value */
                    return array_merge(
                        ['@type' => $key],
                        self::jsonLD($value, $model)
                    );
                }
            } elseif (is_string($value)) {
                $ary = explode('.', $value);
                $key = array_pop($ary);
            }

            /** @psalm-suppress MixedArrayAccess */
            if (is_array($value)) {
                $jsonLD[$key] = self::jsonLD($value, $model);
            } elseif (is_numeric($value)) {
                $jsonLD[$key] = $value;
            } elseif ($value[0] === self::STRING_LITERAL) { // check for string literal
                /** @var string $value */
                $jsonLD[$key] = substr($value, 1);
            } elseif ($value[0] === self::ENUMERATION) { // check for enumeration value
                /** @var string $value */
                $jsonLD[$key] = self::CONTEXT . '/' . substr($value, 1);
            } else {
                /** @var string $value */
                /** @psalm-suppress MixedAssignment */
                $jsonLD[$key] = ArrayHelper::getValueByPath($model, $value);
            }
        }

        return $jsonLD;
    }
}
