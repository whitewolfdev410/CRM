<?php

$NS = MODULES_NS.'CustomerSettings\Http\Controllers\\';

$router->name('customer-settings.')->group(function () use ($router, $NS) {
    $router->get('customer-settings/assets-types', $NS.'CustomerSettingsController@getAssetRequiredTypes');
    $router->get('customer-settings/assets-photo-types', $NS.'CustomerSettingsController@getPhotoRequiredTypes');
    $router->get(
        'customer-settings/work-order-photo-types',
        $NS.'CustomerSettingsController@getWorkOrderPhotoRequiredTypes'
    );
    $router->get('customer-settings/work-order-types', $NS.'CustomerSettingsController@getWorkOrderRequiredTypes');
    $router->get('customer-settings/system-types', $NS.'CustomerSettingsController@getAssetSystemRequiredTypes');
    $router->get('customer-settings/history', $NS.'CustomerSettingsController@history');

    $router->get('customers/{personId}/customer-settings', $NS.'CustomerSettingsController@getCustomerIdByPersonId');
    $router->get('customers/cid/{customerId}/customer-settings', $NS.'CustomerSettingsController@getCustomerId');

    $router->group(['prefix' => 'customer-settings/asset-required'], function () use ($router, $NS) {
        //Asset requirements requests
        $router->post(
            '{customerSettingsId}/create',
            $NS.'CustomerSettingsController@saveAssetRequired'
        );

        $router->post(
            '{customerSettingsId}/delete',
            $NS.'CustomerSettingsController@deleteAssetRequired'
        );
    });

    $router->group(['prefix' => 'customer-settings/assets-required-files'], function () use ($router, $NS) {
        //Asset requirements requests
        $router->post('{customerSettingsId}', $NS.'CustomerSettingsController@saveLinkFileRequired');
        $router->get('{customerSettingsId}', $NS.'CustomerSettingsController@getLinkFileRequired');
        $router->post('{customerSettingsId}/delete', $NS.'CustomerSettingsController@deleteLinkFileRequired');
    });

    $router->group(['prefix' => 'customer-settings/work-order-required-files'], function () use ($router, $NS) {
        //Asset requirements requests
        $router->post('{customerSettingsId}', $NS.'CustomerSettingsController@saveWorkOrderRequiredFiles');
        $router->get('{customerSettingsId}', $NS.'CustomerSettingsController@getWorkOrderRequiredFiles');
        $router->post('{customerSettingsId}/delete', $NS.'CustomerSettingsController@deleteWorkOrderRequiredFiles');
    });

    $router->get('customer-settings/labor-types', $NS.'CustomerSettingsController@getLaborTypes');
    $router->group(['prefix' => 'customer-settings/labor-required-files'], function () use ($router, $NS) {
        //Asset requirements requests
        $router->post('{customerSettingsId}', $NS.'CustomerSettingsController@saveLaborRequiredFiles');
        $router->get('{customerSettingsId}', $NS.'CustomerSettingsController@getLaborRequiredFiles');
    });

    $router->get('customer-settings/{customerSettingsId}/show', $NS.'CustomerSettingsController@showSettings');
    $router->put('customer-settings/{customerSettingsId}/update', $NS.'CustomerSettingsController@updateSettings');

    $router->get('customer-settings-items/{customerSettingsId}', $NS.'CustomerSettingsItemsController@show');
    $router->put('customer-settings-items/{customerSettingsId}', $NS.'CustomerSettingsItemsController@update');
});

$router->resource('customersettings', $NS.'CustomerSettingsController');
