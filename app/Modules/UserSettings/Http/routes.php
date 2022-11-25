<?php

$NS = MODULES_NS.'UserSettings\Http\Controllers\\';

$router->get('usersettings/types/{type}', $NS.'UserSettingsController@getByType');
$router->get('usersettings/types', $NS.'UserSettingsController@types');
$router->resource('usersettings', $NS.'UserSettingsController');
