<?php

$NS = MODULES_NS.'Service\Http\Controllers\\';

$router->name('services.')->group(function () use ($router, $NS) {
    $router->get('services/cities/{state}', $NS.'ServiceController@getCities');
});

$router->resource('services', $NS.'ServiceController');
