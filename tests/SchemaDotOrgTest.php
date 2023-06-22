<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\SchemaDotOrg\tests;

use BeastBytes\SchemaDotOrg\SchemaDotOrg;
use PHPUnit\Framework\Attributes\DataProvider;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\ListenerCollection;
use Yiisoft\EventDispatcher\Provider\Provider;
use Yiisoft\View\Event\WebView\BodyEnd;
use Yiisoft\View\WebView;

class SchemaDotOrgTest extends \PHPUnit\Framework\TestCase
{
    private WebView|null $view = null;

    protected function tearDown(): void
    {
        $this->view = null;
    }

    #[DataProvider('schemaProvider')]
    public function test_schema(array $model, array $mapping, string $expected)
    {
        $this->assertSame($expected, SchemaDotOrg::generate($model, $mapping));
        $this->addSchemaToView($model, $mapping);
        $this->assertSame($expected, $this->getSchemasFromView());
    }

    public function test_multiple_schemas()
    {
        $expected = '';

        foreach ([
            [
                'model' => [
                    'streetAddress' => '10 Downing Street',
                    'locality' => 'City of Westminster',
                    'region' => 'London',
                    'postalCode' => 'SW1A 1AA'
                ],
                'mapping' => [
                    'PostalAddress' => [
                        'streetAddress',
                        'addressLocality' => 'locality',
                        'addressRegion' => 'region',
                        'postalCode'
                    ]
                ],
                'expected' => '<script type="application/ld+json">{'
                    . '"@context":"https://schema.org",'
                    . '"@type":"PostalAddress",'
                    . '"streetAddress":"10 Downing Street",'
                    . '"addressLocality":"City of Westminster",'
                    . '"addressRegion":"London",'
                    . '"postalCode":"SW1A 1AA"'
                    . '}</script>'
            ],
            [
                'model' => [
                    'org' => 'UK Government',
                    'adr' => [
                        'streetAddress' => 'Palace of Westminster',
                        'locality' => 'City of Westminster',
                        'region' => 'London',
                        'postalCode' => 'SW1A 0AA'
                    ],
                    'tel' => '+44-207-219-3000'
                ],
                'mapping' => [
                    'GovernmentOrganization' => [
                        'name' => 'org',
                        'address' => [
                            'PostalAddress' => [
                                'adr.streetAddress',
                                'addressLocality' => 'adr.locality',
                                'addressRegion' => 'adr.region',
                                'adr.postalCode'
                            ]
                        ],
                        'telephone' => 'tel'
                    ],
                ],
                'expected' => '<script type="application/ld+json">{'
                    . '"@context":"https://schema.org",'
                    . '"@type":"GovernmentOrganization",'
                    . '"name":"UK Government",'
                    . '"address":{'
                    . '"@type":"PostalAddress",'
                    . '"streetAddress":"Palace of Westminster",'
                    . '"addressLocality":"City of Westminster",'
                    . '"addressRegion":"London",'
                    . '"postalCode":"SW1A 0AA"'
                    . '},'
                    . '"telephone":"+44-207-219-3000"'
                    . '}</script>'
            ],
        ] as $schema) {
            $this->assertSame($schema['expected'], SchemaDotOrg::generate($schema['model'], $schema['mapping']));

            $expected .= $schema['expected'];
            $this->addSchemaToView($schema['model'], $schema['mapping']);
        }

        $this->assertSame($expected, $this->getSchemasFromView());
    }

    public function test_list()
    {
        $model = [
            'name' => '20th Century UK Prime Ministers',
            'alumni' => [
                ['givenName' => 'Robert', 'familyName' => 'Gascoyne-Cecil'],
                ['givenName' => 'Arthur', 'familyName' => 'Balfour'],
                ['givenName' => 'Henry', 'familyName' => 'Campbell-Bannerman'],
                ['givenName' => 'Herbert', 'familyName' => 'Asquith'],
                ['givenName' => 'David', 'familyName' => 'Lloyd George'],
                ['givenName' => 'Bonar', 'familyName' => 'Law'],
                ['givenName' => 'Stanley', 'familyName' => 'Baldwin'],
                ['givenName' => 'Ramsay', 'familyName' => 'MacDonald'],
                ['givenName' => 'Neville', 'familyName' => 'Chamberlain'],
                ['givenName' => 'Winston', 'familyName' => 'Churchill'],
                ['givenName' => 'Clement', 'familyName' => 'Attlee'],
                ['givenName' => 'Anthony', 'familyName' => 'Eden'],
                ['givenName' => 'Harold', 'familyName' => 'Macmillan'],
                ['givenName' => 'Alec', 'familyName' => 'Douglas-Home'],
                ['givenName' => 'Harold', 'familyName' => 'Wilson'],
                ['givenName' => 'Edward', 'familyName' => 'Heath'],
                ['givenName' => 'James', 'familyName' => 'Callaghan'],
                ['givenName' => 'Margaret', 'familyName' => 'Thatcher'],
                ['givenName' => 'John', 'familyName' => 'Major'],
                ['givenName' => 'Tony', 'familyName' => 'Blair'],
            ]
        ];

        $mapping = [
            'Organization' => [
                'name',
                'alumni' => [
                    SchemaDotOrg::ARRAY => [
                        'Person' => [
                            'givenName',
                            'familyName'
                        ]
                   ]
                ]
            ]
        ];

        $alumni = '';
        foreach ($model['alumni'] as $alumnus) {
            $alumni .= '{';
            $alumni .= '"@type":"Person",';
            $alumni .= '"givenName":"' . $alumnus['givenName'] . '",';
            $alumni .= '"familyName":"' . $alumnus['familyName'] . '"';
            $alumni .= '},';
        }

        $expected = '<script type="application/ld+json">{'
            . '"@context":"https://schema.org",'
            . '"@type":"Organization",'
            . '"name":"20th Century UK Prime Ministers",'
            . '"alumni":['
            . substr($alumni, 0, -1)
            . ']'
            . '}</script>'
        ;

        $this->assertSame($expected, SchemaDotOrg::generate($model, $mapping));

        $model['officeHolders'] = $model['alumni'];
        unset($model['alumni']);
        $mapping = [
            'Organization' => [
                'name',
                'alumni' => [
                    SchemaDotOrg::ARRAY  . 'officeHolders' => [
                        'Person' => [
                            'givenName',
                            'familyName'
                        ]
                    ]
                ]
            ]
        ];

        $this->assertSame($expected, SchemaDotOrg::generate($model, $mapping));
    }

    public static function schemaProvider()
    {
        foreach ([
            'Simple Schema' => [
                'model' => [
                    'streetAddress' => '10 Downing Street',
                    'locality' => 'City of Westminster',
                    'region' => 'London',
                    'postalCode' => 'SW1A 1AA'
                ],
                'mapping' => [
                    'PostalAddress' => [
                        'streetAddress',
                        'addressLocality' => 'locality',
                        'addressRegion' => 'region',
                        'postalCode'
                    ]
                ],
                 'expected' => '<script type="application/ld+json">{'
                     . '"@context":"https://schema.org",'
                     . '"@type":"PostalAddress",'
                     . '"streetAddress":"10 Downing Street",'
                     . '"addressLocality":"City of Westminster",'
                     . '"addressRegion":"London",'
                     . '"postalCode":"SW1A 1AA"'
                     . '}</script>'
            ],
             'Nested Schema' => [
                 'model' => [
                     'org' => 'UK Government',
                     'adr' => [
                         'streetAddress' => 'Palace of Westminster',
                         'locality' => 'City of Westminster',
                         'region' => 'London',
                         'postalCode' => 'SW1A 0AA'
                     ],
                     'tel' => '+44-207-219-3000'
                 ],
                 'mapping' => [
                     'GovernmentOrganization' => [
                         'name' => 'org',
                         'address' => [
                             'PostalAddress' => [
                                 'adr.streetAddress',
                                 'addressLocality' => 'adr.locality',
                                 'addressRegion' => 'adr.region',
                                 'adr.postalCode'
                             ]
                         ],
                         'telephone' => 'tel'
                     ],
                 ],
                 'expected' => '<script type="application/ld+json">{'
                     . '"@context":"https://schema.org",'
                     . '"@type":"GovernmentOrganization",'
                     . '"name":"UK Government",'
                     . '"address":{'
                     . '"@type":"PostalAddress",'
                     . '"streetAddress":"Palace of Westminster",'
                     . '"addressLocality":"City of Westminster",'
                     . '"addressRegion":"London",'
                     . '"postalCode":"SW1A 0AA"'
                     . '},'
                     . '"telephone":"+44-207-219-3000"'
                     . '}</script>'
             ],
             'With string literal' => [
                 'model' => [
                     'name' => 'The Ultimate Product',
                     'description' => 'The only product you will ever need',
                     'quantityAvailable' => '{quantityAvailable}',
                     'price' => '{price}',
                     'currency' => 'GBP'
                 ],
                 'mapping' => [
                     'Product' => [
                         'name',
                         'description',
                         'offers' => [
                             'Offer' => [
                                 'availability' => '{availability}',
                                 'price',
                                 'priceCurrency' => 'currency'
                             ]
                         ]
                     ]
                 ],
                 'expected' => '<script type="application/ld+json">{'
                     . '"@context":"https://schema.org",'
                     . '"@type":"Product",'
                     . '"name":"The Ultimate Product",'
                     . '"description":"The only product you will ever need",'
                     . '"offers":{'
                     . '"@type":"Offer",'
                     . '"availability":"https://schema.org/{availability}",'
                     . '"price":{price},'
                     . '"priceCurrency":"GBP"'
                     . '}'
                     . '}</script>'
             ],
             'With Enumeration' => [
                 'model' => [
                     'name' => 'Another Product',
                     'description' => 'The other product you need',
                     'quantityAvailable' => '{quantityAvailable}',
                     'price' => '{price}',
                 ],
                 'mapping' => [
                     'Product' => [
                         'name',
                         'description',
                         'offers' => [
                             'Offer' => [
                                 'availability' => '{availability}',
                                 'price',
                                 'priceCurrency' => SchemaDotOrg::STRING_LITERAL . 'EUR',
                             ]
                         ]
                     ]
                 ],
                 'expected' => '<script type="application/ld+json">{'
                     . '"@context":"https://schema.org",'
                     . '"@type":"Product",'
                     . '"name":"Another Product",'
                     . '"description":"The other product you need",'
                     . '"offers":{'
                     . '"@type":"Offer",'
                     . '"availability":"https://schema.org/{availability}",'
                     . '"price":{price},'
                     . '"priceCurrency":"EUR"'
                     . '}'
                     . '}</script>'
             ]
        ] as $name => $yield) {
            if (array_key_exists('Product', $yield['mapping'])) {
                $price = random_int(99, 99999) / 100;
                $quantityAvailable = random_int(0, 1);
                $availability = $quantityAvailable > 0
                    ? SchemaDotOrg::ENUMERATION . 'InStock'
                    : SchemaDotOrg::ENUMERATION . 'OutOfStock';

                $yield['model']['price'] = $price;
                $yield['model']['quantityAvailable'] = $quantityAvailable === 1
                    ? $quantityAvailable * random_int(1, 9999)
                    : 0;
                $yield['mapping']['Product']['offers']['Offer']['availability'] = $availability;
                $yield['expected'] = strtr($yield['expected'], [
                    '{availability}' => substr($availability, 1),
                    '{price}' => (string)$price,
                ]);
            }

            yield $name => $yield;
        }
    }

    private function addSchemaToView(array $model, array $mapping): void
    {
        if ($this->view === null) {
            $this->view = $this->createView();
        }

        SchemaDotOrg::addSchema($this->view, $model, $mapping);
    }

    private function getSchemasFromView(): string
    {
        ob_start();
        $this->view->endBody();
        return preg_replace('|<!\[CDATA\[YII-BLOCK-BODY-END-.+]]>|', '', ob_get_clean());
    }

    private function createView(): WebView
    {
        $listeners = (new ListenerCollection())
            ->add([SchemaDotOrg::class, 'handle'], BodyEnd::class);

        $provider = new Provider($listeners);
        return new WebView('', new Dispatcher($provider));
    }
}
