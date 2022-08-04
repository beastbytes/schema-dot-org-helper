# Schema.org Helper (schema-dot-org-helper)
A Helper for generating Schema.org schemas in JSON-LD.

For license information see the [LICENSE](LICENSE.md) file.

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist beastbytes/schemadotorg
```

or add

```json
"beastbytes/schema-dot-org": "^1.0.0"
```

to the require section of your composer.json.

## Usage
In a view:
```php
$schema = [
    // define schema
];
$model = [
    // model can be an array or an object
];
echo SchemaDotOrg::jsonLD($schema, $model);
```

To generate the Schema.org JSON-LD in response to a view event - Yiisoft\View\Event\WebView\BodyEnd is preferred, in the view:
```php
$schema = [
    // define schema
];
$model = [
    // model
];

$this->setParameter('schemaDotOrg', compact('schema', 'model'));
```

In config/events-web.php :

```php
use BeastBytes\SchemaDotOrg\WebViewEventHandler;
use Yiisoft\View\Event\WebView\BodyEnd;

return [
    // other event handlers
    BodyEnd::class => [
        [WebViewEventHandler::class, 'handle']
    ],
    // other event handlers
];
```
The schema is an array of the form:
```php
$schema = [
    'Type' => [
        'schemaDotOrgProperty' => 'model.property', // or
        'model.property' // if the Schema.org and property names are the same
    ]
];
```

Where a Schema.org property is defined as a Schema.org type, the type is a nested array:
```php
[
    'Type' => [
        'schemaDotOrgProperty' => [
            'NestedType' => [
                // ...
            ]
        ]
    ]
]
```

Example schema definition:
```php
[
    'LocalBusiness' => [ // @type: *always* begin with an uppercase letter
        'name' => 'org', // maps the 'org' property of the model to the Schema.org 'name' property
        'address' => [ // the Schema.org 'address' property is a PostalAddress type
            'PostalAddress' => [ // @type
                'adr.streetAddress', // no need for mapping if the Schema.org and model property names are the same
                'addressLocality' => 'adr.locality', // define the mapping if different property names 
                'addressRegion' => 'adr.region',
                'adr.postalCode'
            ]
        ],
        'location' => [
            'Place' => [
                'geo' => [
                    'GeoCoordinates' => [
                        'geo.elevation',
                        'geo.latitude',
                        'geo.longitude'
                    ]
                ]
            ],
        ],
        'email',
        'telephone' => 'tel.cell.value',
        'currenciesAccepted' => SchemaDotOrg::STRING_LITERAL . 'GBP',
        'image' => SchemaDotOrg::STRING_LITERAL . 'https://example.com/images/logo.svg',
        'makesOffer' => [
            'Offer' => [
                'name',
                'description',
                'price',
                'priceCurrency' => SchemaDotOrg::STRING_LITERAL . 'GBP',
                'availability' => SchemaDotOrg::ENUMERATION . 'InStock'
            ]
        ]
    ]
]
```