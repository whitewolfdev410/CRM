<?php

$NS = MODULES_NS . 'Invoice\Http\Controllers\\';

$router->name('invoices.')->group(function () use ($router, $NS) {
    $router->post('invoices/quote', $NS.'InvoiceController@storeFromQuote');
    $router->post('invoices/pm', $NS.'InvoiceController@storeForPm');
    $router->get('invoices/import/{id}', $NS.'InvoiceController@import');
    $router->post('invoices/send', $NS.'InvoiceController@send');
    $router->get('invoices/sendingStatus', $NS.'InvoiceController@sendingStatus');
    $router->put('invoices/updateStatus/{id}', $NS.'InvoiceController@updateStatus');
    $router->put('invoices/updateDescription/{id}', $NS . 'InvoiceController@updateDescription');
    
    $router->get('invoices/export-excel', $NS.'InvoiceController@exportExcel');
    $router->post('invoices/group', $NS.'InvoiceController@groupInvoices');
    $router->get('invoices/batches', $NS.'InvoiceController@getBatches');
    $router->get('invoices/batchesStatuses', $NS.'InvoiceController@getBatchesStatuses');
    $router->get('invoices/batch/{id}', $NS.'InvoiceController@getBatch');

    $router->get('invoices/import-exceptions', $NS.'InvoiceImportExceptionController@index');
    $router->post('invoices/import-exceptions/resolve', $NS.'InvoiceImportExceptionController@resolve');
    $router->post('invoices/import-exceptions/reopen', $NS.'InvoiceImportExceptionController@reopen');

    $router->get('invoices/{id}/activities', $NS.'InvoiceController@activities');
    $router->get('invoices/{id}/pdf', $NS.'InvoiceController@pdf');
    $router->get('invoices/services', $NS.'InvoiceController@services');

    $router->get('invoice_entries_search', $NS . 'InvoiceEntryController@getSearch');
    $router->post('invoices/delivery-stats', $NS . 'InvoiceController@deliveryStats');
});

$router->resource('invoice_entries', $NS . 'InvoiceEntryController');
$router->resource('invoice-repeats', $NS . 'InvoiceRepeatController');
$router->resource('invoice-templates', $NS . 'InvoiceTemplateController');
$router->resource('invoices', $NS . 'InvoiceController');
