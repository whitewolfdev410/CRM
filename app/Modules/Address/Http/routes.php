<?php

$NS = MODULES_NS.'Address\Http\Controllers\\';
$router->name('addresses.')->group(function () use ($router, $NS) {
    $router->resource('addresses/countries', $NS.'CountryController');
    $router->resource('addresses/states', $NS.'StateController');
    $router->resource('addresses/currencies', $NS.'CurrencyController');

    $router->get('addresses_search', $NS.'AddressController@getSearch');
    $router->get('addresses/customer', $NS.'AddressController@getCustomerAddresses');
    $router->put('addresses/{id}/verify', $NS.'AddressController@verify');
    $router->get('addresses/{id}/label', $NS.'AddressController@envelope');

    $router->get('addresses/{id}/photos', MODULES_NS.'File\Http\Controllers\FileController@getAddressPhotos');
    $router->get('addresses/{id}/vendors', MODULES_NS.'Person\Http\Controllers\CompanyController@getVendorsByAddress');
    $router->post(
        'addresses/{id}/vendors/assign',
        MODULES_NS.'Person\Http\Controllers\CompanyController@assignVendorsToAddress'
    );
    $router->post(
        'addresses/{id}/vendors/rearrange',
        MODULES_NS.'Person\Http\Controllers\CompanyController@rearrangeVendorsAtAddress'
    );
    $router->post(
        'addresses/{id}/vendors/unassign',
        MODULES_NS.'Person\Http\Controllers\CompanyController@unassignVendorsToAddress'
    );
});
$router->name('blade.')->group(function () use ($router, $NS) {
    $router->resource('blade/addresses', $NS.'BladeController');
});
$router->resource('addresses', $NS.'AddressController');
