<?php

$NS = MODULES_NS . 'User\Http\Controllers\\';

$router->name('users.')->group(function () use ($router, $NS) {
    $router->get('users/devices/history', $NS.'UserDeviceController@getHistory');
    $router->resource('users/devices/tokens', $NS.'UserDeviceTokenController');
    $router->resource('users/devices', $NS.'UserDeviceController');
    $router->get('users/{id}/generate-direct-login', $NS.'UserController@generateDirectLogin');

    // client portal
    $router->post('users/client-portal/create', $NS.'ClientPortalUserController@create');
    $router->get('users/client-portal/list', $NS.'ClientPortalUserController@index');
    $router->get('users/client-portal/customers', $NS.'ClientPortalUserController@indexCustomers');
    $router->post('users/client-portal/upload-logo', $NS.'ClientPortalUserController@uploadLogo');
});

$router->resource('users', $NS.'UserController');
