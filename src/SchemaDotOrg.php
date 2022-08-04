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
 *
 * @author Chris Yates
 */
class SchemaDotOrg
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
     * @param array $schema Schema.org type definition
     * @param array|Object $model Data model
     * @return string JSON-LD format schema
     * @throws \Exception
     */
    public static function jsonLD(array $schema, $model = []): string
    {
        return Script::tag()
            ->content(
                Json::encode(
                    array_merge(
                        ['@context' => self::CONTEXT],
                        self::_jsonLD($schema, $model)
                    )
                )
            )
            ->type('application/ld+json')
            ->render();
    }

    /**
     * Returns an array of JSON-LD for a schema.org type
     *
     * @param array $schema
     * @param array|Object $model
     * @return array
     * @throws \Exception
     */
    private static function _jsonLD(array $schema, $model): array
    {
        $jsonLD = [];

        foreach ($schema as $key => $value) {
            if (is_string($key)) {
                if (preg_match('/^[A-Z]/', $key)) {
                    if (isset($value[0]) && is_array($value[0])) { // array of objects of type $key
                        $objects = [];

                        foreach ($value as $object) {
                            $objects[] = array_merge(
                                ['@type' => $key],
                                self::_jsonLD($object, $model)
                            );
                        }
                        
                        return $objects;
                    }

                    return array_merge(
                        ['@type' => $key],
                        self::_jsonLD($value, $model)
                    );
                }
            } elseif (is_string($value)) {
                $key = explode('.', $value);
                $key = array_pop($key);
            }

            if (is_array($value)) {
                $jsonLD[$key] = self::_jsonLD($value, $model);
            } elseif (is_numeric($value)) {
                $jsonLD[$key] = $value;
            } elseif ($value[0] === self::STRING_LITERAL) { // check for string literal
                $jsonLD[$key] = substr($value, 1);
            } elseif ($value[0] === self::ENUMERATION) { // check for enumeration value
                $jsonLD[$key] = self::CONTEXT . '/' . substr($value, 1);
            } else {
                $jsonLD[$key] = ArrayHelper::getValueByPath($model, $value);
            }
        }

        return $jsonLD;
    }
}
