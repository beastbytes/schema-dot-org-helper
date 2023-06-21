<?php
/**
 * @copyright Copyright © 2023 BeastBytes - All rights reserved
 * @license BSD 3-Clause
 */

declare(strict_types=1);

namespace BeastBytes\SchemaDotOrg\tests;

use BeastBytes\SchemaDotOrg\SchemaDotOrg;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\EventDispatcher\Provider\ListenerCollection;
use Yiisoft\EventDispatcher\Provider\Provider;
use Yiisoft\View\Event\WebView\BodyEnd;
use Yiisoft\View\WebView;

class SchemaDotOrgTest extends \PHPUnit\Framework\TestCase
{
    private array $adr = [
        'streetAddress' => '10 Downing Street',
        'locality' => 'City of Westminster',
        'region' => 'London',
        'postalCode' => 'SW1A'
    ];

    private array $org = [
        'org' => 'UK Government',
        'adr' => null,
        'tel' => '+44-20-7925-0918'
    ];

    public function testSimpleSchema()
    {
        $mapping = [
            'PostalAddress' => [
                'streetAddress',
                'addressLocality' => 'locality',
                'addressRegion' => 'region',
                'postalCode'
            ]
        ];

        $expected = '<script type="application/ld+json">{';
        $expected .= '"@context":"https://schema.org",';
        $expected .= '"@type":"PostalAddress",';
        $expected .= '"streetAddress":"10 Downing Street",';
        $expected .= '"addressLocality":"City of Westminster",';
        $expected .= '"addressRegion":"London",';
        $expected .= '"postalCode":"SW1A"';
        $expected .= '}</script>';

        $this->assertSame($expected, SchemaDotOrg::generate($this->adr, $mapping));
        $this->assert($expected, $this->adr, $mapping);
    }

    public function testNestedSchema()
    {
        $mapping = [
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
        ];

        $expected = '<script type="application/ld+json">{';
        $expected .= '"@context":"https://schema.org",';
        $expected .= '"@type":"GovernmentOrganization",';
        $expected .= '"name":"UK Government",';
        $expected .= '"address":{';
        $expected .= '"@type":"PostalAddress",';
        $expected .= '"streetAddress":"10 Downing Street",';
        $expected .= '"addressLocality":"City of Westminster",';
        $expected .= '"addressRegion":"London",';
        $expected .= '"postalCode":"SW1A"';
        $expected .= '},';
        $expected .= '"telephone":"+44-20-7925-0918"';
        $expected .= '}</script>';

        $this->org['adr'] = $this->adr;

        $this->assertSame($expected, SchemaDotOrg::generate($this->org, $mapping));
        $this->assert($expected, $this->org, $mapping);
    }

    public function testEnumeration()
    {
        $model = [
            'name' => 'The Ultimate Product',
            'description' => 'The only product you will ever need',
            'quantityAvailable' => random_int(0, 1),
            'price' => random_int(99, 99999) / 100,
            'currency' => 'GBP'
        ];

        $mapping = [
            'Product' => [
                'name',
                'description',
                'offers' => [
                    'Offer' => [
                        'availability' => $model['quantityAvailable'] > 0
                            ? SchemaDotOrg::ENUMERATION . 'InStock'
                            : SchemaDotOrg::ENUMERATION . 'OutOfStock',
                        'price',
                        'priceCurrency' => 'currency'
                    ]
                ]
            ]
        ];

        $expected = '<script type="application/ld+json">{';
        $expected .= '"@context":"https://schema.org",';
        $expected .= '"@type":"Product",';
        $expected .= '"name":"The Ultimate Product",';
        $expected .= '"description":"The only product you will ever need",';
        $expected .= '"offers":{';
        $expected .= '"@type":"Offer",';
        $expected .= '"availability":"https://schema.org/' . ($model['quantityAvailable'] > 0 ? 'In' : 'OutOf') . 'Stock",';
        $expected .= '"price":' . (string)$model['price']  . ',';
        $expected .= '"priceCurrency":"GBP"';
        $expected .= '}';
        $expected .= '}</script>';

        $this->assertSame($expected, SchemaDotOrg::generate($model, $mapping));
        $this->assert($expected, $model, $mapping);
    }

    public function testStringLiteral() {
        $model = [
            'name' => 'The Ultimate Product',
            'description' => 'The only product you will ever need',
            'quantityAvailable' => random_int(0, 1),
            'price' => random_int(99, 99999) / 100,
        ];

        $mapping = [
            'Product' => [
                'name',
                'description',
                'offers' => [
                    'Offer' => [
                        'availability' => $model['quantityAvailable'] > 0
                            ? SchemaDotOrg::ENUMERATION . 'InStock'
                            : SchemaDotOrg::ENUMERATION . 'OutOfStock',
                        'price',
                        'priceCurrency' => SchemaDotOrg::STRING_LITERAL . 'EUR'
                    ]
                ]
            ]
        ];

        $expected = '<script type="application/ld+json">{';
        $expected .= '"@context":"https://schema.org",';
        $expected .= '"@type":"Product",';
        $expected .= '"name":"The Ultimate Product",';
        $expected .= '"description":"The only product you will ever need",';
        $expected .= '"offers":{';
        $expected .= '"@type":"Offer",';
        $expected .= '"availability":"https://schema.org/' . ($model['quantityAvailable'] > 0 ? 'In' : 'OutOf') . 'Stock",';
        $expected .= '"price":' . $model['price']  . ',';
        $expected .= '"priceCurrency":"EUR"';
        $expected .= '}';
        $expected .= '}</script>';

        $this->assertSame($expected, SchemaDotOrg::generate($model, $mapping));
        $this->assert($expected, $model, $mapping);
    }

    private function assert(string $expected, array $model, array $mapping): void {
        $view = $this->createView();

        SchemaDotOrg::addSchema($view, $model, $mapping);
        ob_start();
        $view->endBody();
        $actual = preg_replace('|<!\[CDATA\[YII-BLOCK-BODY-END-.+]]>|', '', ob_get_clean());

        $this->assertSame($expected, $actual);
    }

    private function createView(): WebView {
        $listeners = (new ListenerCollection())
            ->add([SchemaDotOrg::class, 'handle'], BodyEnd::class);

        $provider = new Provider($listeners);
        return new WebView('', new Dispatcher($provider));
    }
}
