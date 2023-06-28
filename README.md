# Schema.org Helper (schema-dot-org-helper)
A Helper for generating Schema.org schemas in JSON-LD.

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist beastbytes/schemadotorg
```

or add

```json
"beastbytes/schema-dot-org": "^2.0"
```

to the require section of your composer.json.

## Usage
To generate a schema:
```php
// In the view
use BeastBytes\SchemaDotOrg\SchemaDotOrg;

$mapping = [
    // define mapping
];
$model = [
    // model can be an array or an object
];
$schema = SchemaDotOrg::generate($model, $mapping);
// Multiple schemas can be generated
```

The generated schema can be rendered directly
```php
echo SchemaDotOrg::generate($model, $mapping);
```
or registered with the view:
```php
$this->registerJs(SchemaDotOrg::generate($model, $mapping));
```

## Defining a Schema Mapping
A schema mapping is an array that defines the mapping of model properties to Schema.org properties; it is of the form:
```php
$mapping = [
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

If a Schema.org property is to be a string literal, prepend with SchemaDotOrg::STRING_LITERAL :
```php
[
    'Type' => [
        'schemaDotOrgProperty' => SchemaDotOrg::STRING_LITERAL . 'Literal value'
    ]
]
```

If a Schema.org property is a SchemaDotOrg Enumeration value, prepend with SchemaDotOrg::ENUMERATION :
```php
[
    'Type' => [
        'schemaDotOrgProperty' => SchemaDotOrg::ENUMERATION . 'EnumerationName'
    ]
]
```

If a Schema.org property is an array of values - usually nested types - specify the mapping as an array.
The key must be or start with SchemaDotOrg::ARRAY. If it is just SchemaDotOrg::ARRAY the mapping parent key is the
model property, else the model property is the remainder of the key; both forms are shown below:
```php
[
    'EducationalOrganization' => [
        'name',
        'alumni' => [
            SchemaDotOrg::ARRAY => [ // the model property is 'alumni'
                'Person' => [
                    'familyName',
                    'givenName'
                ]
            ]
        ]
    ]
]
```
```php
[
    'EducationalOrganization' => [
        'name',
        'alumni' => [
            SchemaDotOrg::ARRAY . 'pastPupils' => [ // the model property is 'pastPupils'
                'Person' => [
                    'familyName',
                    'givenName'
                ]
            ]
        ]
    ]
]
```

Example schema mapping definition:
```php
[
    'LocalBusiness' => [ // @type always begins with an uppercase letter
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
                'additionalProperty' => [
                    'PropertyValue' => [
                        'propertyID' => SchemaDotOrg::STRING_LITERAL . 'what3words',
                        'value' => 'adr.what3words',
                    ],           
                ],
                'latitude',
                'longitude',
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

Example JSON-LD generated using the above schema mapping:
```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "Business Name",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "99 Fencott Road",
    addressLocality: "Fencott",
    addressRegion: "Oxon",
    postalCode: "OX5 2RD"    
  }
  "location": {
    "@type": "Place",
    "additionalProperty": {
      "@type": "PropertyValue",
      "propertyID": "what3words",
      "value": "tangent.migrate.commander"
    },
    "latitude": 51.84095049377005,
    "longitude": -1.1709238113995422,
  },
  "email": "getintouch@example.com",
  "telephone": "01865 369248",
  "currenciesAccepted": "GBP",
  "image": "https://example.com/images/logo.svg",
  "makesOffer": {
    "@type": "Offer",
    "name": "Awesome Product",
    "description": "The ony product you will ever need",
    "price": 999.99,
    "priceCurrency": "GBP",
    "availability": "https://schema.org/InStock"
  }  
}
</script>
```

## Twig Templates

To use the helper in a Twig templates either include it in CommonViewInjection (in the examples it is assigned to the 
schemaDotOrg variable) or in the template

```twig
{% set schemaDotOrg = get('BeastBytes\\SchemaDotOrg\\SchemaDotOrg') %}
```
Then in the template either:

to echo the schema immediately:
```twig
{{ schemaDotOrg.generate(model, mapping) }}
```
or to register the schema with the view:
```twig
{% do this.registerJs(schemaDotOrg.generate(model, mapping)) %}
```
### Defining the Mapping

+ In Twig templates the mapping must define both the SchemaDotOrg property and the model property, even if they have the same name.
+ To use the SchemaDotOrg class constants use Twig's constant() function and concatenate the string

For example:

```twig
{
    Offer: {
        name: 'name',
        description: 'description',
        price: 'price',
        priceCurrency: constant('STRING_LITERAL', schemaDotOrg) ~ 'GBP',
        availability: constant('ENUMERATION', schemaDotOrg) ~ 'InStock'
    }
}
```
# Testing
All testing is carried out from the root directory.

## Unit testing
The package is tested with [PHPUnit](https://phpunit.de/). To run tests:
composer test

## Mutation testing
The package tests are checked with [Infection mutation framework](https://infection.github.io/) with [Infection Static Analysis Plugin](https://github.com/Roave/infection-static-analysis-plugin). To run it:
composer infection

## Static analysis
The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:
composer psalm

# License
The Schema.org Helper is free software. It is released under the terms of the BSD License. Please see [LICENSE](./license.md) for more information.
