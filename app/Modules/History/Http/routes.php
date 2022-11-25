<?php

$NS = MODULES_NS.'History\Http\Controllers\\';

Route::get('history', [
    'as' => 'history.index',
    'uses' =>
        $NS.'HistoryController@index',
]);
