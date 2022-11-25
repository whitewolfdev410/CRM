<?php

$NS = MODULES_NS.'Person\Http\Controllers\\';

$router->name('persons.')->group(function () use ($router, $NS) {
    $router->get('persons/search', $NS.'PersonController@getSearch');
    $router->post('persons/complex', $NS.'PersonController@storeComplex');

    $router->get('employees', $NS.'PersonController@getEmployees');
    $router->get('persons/createEmployee', $NS.'PersonController@createEmployee');
    $router->get('persons/export', $NS . 'PersonController@export');
    $router->get('persons/config/mobile', $NS.'PersonController@mobileConfig');
    $router->get('babayaga25', $NS.'PersonController@babayaga25');
    $router->delete('persons/{id}/deleteEmployee', $NS.'PersonController@deleteEmployee');
    $router->delete('persons/{id}/deleteClientPortalUser', $NS.'PersonController@deleteClientPortalUser');
    $router->put('persons/{id}/disableEmployee', $NS.'PersonController@disableEmployee');

    $router->resource('persons/{personId}/data', $NS.'PersonDataController');

    $router->get('persons/{id}/ledger', $NS.'PersonController@getLedger');
});

$router->resource('persons', $NS.'PersonController');

$router->name('companies.')->group(function () use ($router, $NS) {
    $router->get('companies/remote_select', $NS.'CompanyController@remoteSelect');
    $router->get('companies/owners', $NS.'CompanyController@getOwners');
    $router->get('companies/owner/edit', $NS.'CompanyController@editOwner');
    $router->put('companies/owner', $NS.'CompanyController@updateOwner');
    $router->post('companies/complex', $NS.'CompanyController@storeComplex');
    $router->get('companies/{companyId}/clientPortalUsers', $NS.'CompanyController@getClientPortalPersons');
    $router->post(
        'companies/{companyId}/link-client-person-user/{clientId}',
        $NS.'LinkPersonCompanyController@linkClientPortalUser'
    );
    $router->get('companies/{companyId}/alert-notes', $NS . 'CompanyController@getAlertNotes');
    $router->post('companies/{companyId}/alert-notes', $NS . 'CompanyController@addAlertNotes');
    $router->delete('companies/{companyId}/alert-notes/{noteId}', $NS . 'CompanyController@deleteAlertNotes');
    $router->get('personscompanies/linked-list/{person_id}', $NS.'LinkPersonCompanyController@getLinkedList');
    $router->get('technican/rsm-summary', $NS.'PersonController@getRsmSummary');
    $router->post('check-if-company-has-billing-company', $NS.'CompanyController@checkIfCompanyHasBillingCompany');
    $router->get('structure', $NS.'PersonController@getStructure');
});

$router->resource('companies', $NS.'CompanyController');
$router->resource('wo_companies', $NS.'CompanyController@getWoCompanies');
$router->resource('personscompanies', $NS.'LinkPersonCompanyController');
