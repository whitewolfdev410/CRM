<?php

$NS = MODULES_NS . 'Type\Http\Controllers\\';

$router->name('types.')->group(function () use ($router, $NS) {
    $router->put('types/rearrange', MODULES_NS.'Type\Http\Controllers\TypeController@rearrange');
});

$router->resource('types', $NS . 'TypeController');
