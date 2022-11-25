<?php

$NS = MODULES_NS . 'WorkOrder\Http\Controllers\\';

$router->name('workorders.')->group(function () use ($router, $NS) {
    $router->get('workorders/{id}/tech_status_history', $NS.'WorkOrderController@techStatusHistory');

    $router->post('workorders/mobile', $NS.'WorkOrderController@mobileStore');
    $router->get('workorders/available-tabs', $NS.'WorkOrderController@availableTabs');
    $router->get('workorders/assigned-meetings', $NS.'WorkOrderController@assignedMeetings');
    $router->get('workorders/assigned-tasks', $NS.'WorkOrderController@assignedTasks');
    $router->get('workorders/dashboard', $NS.'WorkOrderController@dashboard');

    $router->resource('workorders/extensions', $NS.'WorkOrderExtensionController');
    $router->resource('workorders/templates', $NS.'WorkOrderTemplateController');

    $router->get('workorders/{id}/extensions', $NS.'WorkOrderExtensionController@showWOExtensions');
    $router->get('workorders/mobile/{id}', $NS.'WorkOrderController@mobileShow');

    $router->get('workorders/labors', $NS.'LinkLaborWoController@getLabors');
    $router->get('workorders/labors-to-accept', $NS.'LinkLaborWoController@getLaborsToAccept');
    $router->get('workorders/labors/pricing', $NS.'LinkLaborWoController@getPricing');
    $router->post('workorders/labors/accept', $NS.'LinkLaborWoController@accept');

    $router->get('workorders/links/mobile/{id}', $NS.'LinkPersonWoController@mobileShow');
    $router->post('workorders/links/bulk-complete', $NS . 'LinkPersonWoController@bulkComplete');
    $router->get('workorders/links/resolved_wo', $NS.'LinkPersonWoController@getResolvedWO');
    $router->get('workorders/links/unresolved_wo', $NS.'LinkPersonWoController@getUnresolvedWO');
    $router->put('workorders/links/{id}/resolve', $NS.'LinkPersonWoController@resolveWorkOrder');
    $router->put('workorders/links/{id}/complete', $NS.'LinkPersonWoController@completeWorkOrder');
    $router->put('workorders/links/{id}/status', $NS.'LinkPersonWoController@changeStatus');
    $router->get('workorders/links/{id}/job-description/create', $NS.'LinkPersonWoController@createJobDescription');
    $router->get('workorders/links/{id}/job-description', $NS.'LinkPersonWoController@getJobDescription');
    $router->put('workorders/links/{id}/job-description', $NS.'LinkPersonWoController@saveJobDescription');
    $router->get('workorders/links/{id}/print/choose', $NS.'PrintController@choose');
    $router->get('workorders/links/{id}/print/email', $NS.'PrintController@emailInfo');
    $router->post('workorders/links/{id}/print/email', $NS.'PrintController@sendEmail');
    $router->get('workorders/links/{id}/print/fax', $NS.'PrintController@faxInfo');
    $router->post('workorders/links/{id}/print/fax', $NS.'PrintController@sendFax');

    $router->get('workorders/links/{id}/print/generate-pdf', $NS.'PrintController@generatePdf');
    $router->post('workorders/links/{id}/print/generate-pdf', $NS.'PrintController@generatePdf');
    $router->get('workorders/links/countalerts', $NS.'LinkPersonWoController@countAlerts');
    $router->resource('workorders/links', $NS.'LinkPersonWoController');
    $router->get('workorders/technician-summary', $NS.'WorkOrderController@technicianSummary');
    $router->get('workorders/assets', $NS.'WorkOrderController@assets');
    $router->get('workorders/assigned-for-tomorrow', $NS.'RouteOptimizerDataController@getAssignedForTomorrow');
    $router->get('workorders/person/{id}', $NS.'WorkOrderController@showPersonForWo');
    $router->get('workorders/{id}/status-history', $NS.'WorkOrderController@statusHistory');
    $router->put('workorders/{id}/cancel', $NS.'WorkOrderController@cancel');
    $router->put('workorders/{id}/unlock', $NS.'WorkOrderController@unlock');
    $router->put('workorders/{id}/pickup', $NS.'WorkOrderController@pickup');
    $router->get('workorders/{id}/tech-vendor-details', $NS.'WorkOrderController@getVendorDetails');
    $router->get('workorders/{id}/tech-vendor-summary', $NS.'WorkOrderController@getVendorSummary');
    $router->get('workorders/{id}/basicedit', $NS.'WorkOrderController@basicEdit');
    //$router->put('workorders/{id}', $NS . 'WorkOrderController@fieldsUpdate');
    $router->put('workorders/{id}/basicupdate', $NS.'WorkOrderController@basicUpdate');
    $router->put('workorders/{id}/noteupdate', $NS.'WorkOrderController@noteUpdate');
    $router->get('workorders/{id}/vendorstoassign', $NS.'WorkOrderController@vendorsToAssign');
    $router->post('workorders/{id}/assignvendors', $NS.'WorkOrderController@assignVendors');
    $router->get('workorders/{id}/activities', $NS.'WorkOrderController@activities');
    $router->get('workorders/completiongrid', $NS.'WorkOrderController@completionGrid');
    $router->get('workorders/{id}/locations', $NS.'WorkOrderController@locations');
    $router->get('workorders/{id}/locations/photos', $NS.'WorkOrderController@locationsPhotos');
    $router->get('workorders/{id}/locations/vendors', $NS.'WorkOrderController@locationsVendors');
    $router->get('workorders/{id}/profitability', $NS.'WorkOrderController@profitability');
    $router->put('workorders/links/{id}/change-order/{direction}', $NS.'LinkPersonWoController@changeOrder');
    $router->get('workorders/tech-progress-grid-summary', $NS.'LinkPersonWoController@getTechGrid');
    $router->get('workorders/{id}/link-contact-monitor', $NS.'WorkOrderController@getRelatedWith');

    // new requests added by Pawel Kaczmarski on 2019-04-24
    $router->get('workorders/{id}/reassign', $NS.'WorkOrderController@getWOReassign');
    $router->post('workorders/{id}/reassign', $NS.'WorkOrderController@storeWOReassign');
    $router->get('workorders/{id}/problem-details', $NS.'WorkOrderController@getProblemDetails');
    $router->post('workorders/{id}/problem-note', $NS.'WorkOrderController@storeProblemNote');
    $router->get('workorders/{id}/customer-details', $NS.'WorkOrderController@getCustomerDetails');
    $router->post('workorders/{id}/customer-details', $NS.'WorkOrderController@storeCustomerDetails');
    $router->post('workorders/{id}/customer-note', $NS.'WorkOrderController@storeCustomerNote');
    $router->get('workorders/{id}/site-details', $NS.'WorkOrderController@getSiteDetails');
    $router->post('workorders/{id}/site-details', $NS.'WorkOrderController@storeSiteDetails');
    $router->post('workorders/{id}/site-note', $NS.'WorkOrderController@storeSiteNote');
    $router->get('workorders/call-types', $NS.'WorkOrderController@getCallTypes');
    $router->get('workorders/priority', $NS.'WorkOrderController@getPriority');
    $router->get('workorders/wo-status', $NS.'WorkOrderController@getWoStatus');
    $router->get('workorders/invoice-status', $NS.'WorkOrderController@getInvoiceStatus');
    $router->get('workorders/not-invoiced', $NS.'WorkOrderController@getNotInvoiced');
    $router->get('workorders/sl-wo-statuses', $NS.'WorkOrderController@getSlWoStatuses');
    $router->get('workorders/sl-tech-statuses', $NS.'WorkOrderController@getSlTechStatuses');
    $router->get('workorders/sl-technicians', $NS.'WorkOrderController@getSlTechnicians');
    $router->get('workorders/locations', $NS.'WorkOrderController@getLocations');
    $router->post('workorders/{id}/edit', $NS.'WorkOrderController@updateBfc');
    // end of new requests

    $router->get('workorders/{id}/client-ivr', $NS.'WorkOrderController@getClientIvr');
    $router->get('workorders/{id}/client-note', $NS.'WorkOrderController@getClientNote');
    $router->get('workorders/release-calls/data', $NS.'WorkOrderController@getReleaseCallsData');
    $router->get('workorders/regions', $NS.'WorkOrderController@getRegions');
    $router->get('workorders/trades', $NS.'WorkOrderController@getTrades');
    $router->get('workorders/non_closed_list', $NS.'WorkOrderController@getNonClosedList');
    $router->get('workorders/{id}/history', $NS.'WorkOrderController@getHistoryBfc');

    $router->post('workorders/{id}/set-comment', $NS.'WorkOrderController@updateComment');

    //article
    $router->get('workorders/{id}/linked-articles', $NS.'WorkOrderController@getLinkedArticles');
    $router->post('workorders/{id}/link-article/{articleId}', $NS.'WorkOrderController@linkArticle');

    $router->post('workorders/{fromWoId}/merge/{toWoId}', $NS.'WorkOrderController@merge');
    $router->get('workorders/{id}/userActivities', $NS.'WorkOrderController@userActivities');

    $router->get('workorders/open-work-orders', $NS.'WorkOrderController@getOpenWorkOrders');
    $router->get('workorders/filters', $NS.'WorkOrderController@filters');
    $router->get('workorders/columns', $NS.'WorkOrderController@columns');

    $router->post('workorders/{id}/customer-note', $NS.'WorkOrderController@sendExternalNoteBfc');
    $router->post('workorders/{id}/customer-file', $NS.'WorkOrderController@sendExternalFileBfc');
});

$router->put('workorders/links/{id}/confirm', [
    'as'   => 'work_order.confirm',
    'uses' => $NS.'LinkPersonWoController@confirmWorkOrder',
]);

$router->get('workorders/links/{id}/print/download', [
    'as'   => 'work_order.print_download',
    'uses' => $NS.'PrintController@download',
]);

$router->resource('workorders', $NS . 'WorkOrderController');
