<?php

use App\Support\CatalogKindMatcher;

test('catalog kind matcher classifies physical goods as products', function () {
    $result = CatalogKindMatcher::classify([
        'name' => 'Cotton t-shirt',
        'description' => 'Soft everyday tee',
    ]);

    expect($result['kind'])->toBe('product')
        ->and($result['is_service'])->toBeFalse()
        ->and($result['unit'])->toBe('pcs')
        ->and($result['track_stock'])->toBeTrue();
});

test('catalog kind matcher classifies consulting and hourly work as services', function () {
    $result = CatalogKindMatcher::classify([
        'name' => 'Monthly website maintenance',
        'unit' => 'hour',
    ]);

    expect($result['kind'])->toBe('service')
        ->and($result['is_service'])->toBeTrue()
        ->and($result['unit'])->toBe('hour')
        ->and($result['track_stock'])->toBeFalse();
});

test('woocommerce virtual maintenance item is a service', function () {
    $result = CatalogKindMatcher::fromWooCommerce([
        'name' => 'Monthly maintenance',
        'type' => 'simple',
        'virtual' => true,
        'categories' => [['name' => 'Services']],
        'manage_stock' => false,
    ]);

    expect($result['kind'])->toBe('service')->and($result['track_stock'])->toBeFalse();
});

test('shopify service product type is a service', function () {
    $result = CatalogKindMatcher::fromShopify([
        'title' => 'Logo design',
        'product_type' => 'Service',
        'tags' => 'design, branding',
        'variants' => [['requires_shipping' => false, 'price' => '450.00']],
    ]);

    expect($result['kind'])->toBe('service')->and($result['unit'])->toBe('hour');
});
