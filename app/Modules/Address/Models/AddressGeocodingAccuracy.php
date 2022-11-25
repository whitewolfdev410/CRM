<?php

namespace App\Modules\Address\Models;

class AddressGeocodingAccuracy
{
    const FULL = 99;
    const WITHOUT_STATE = 80;
    const WITHOUT_ADDRESS = 50;
    const WITHOUT_ADDRESS_AND_STATE = 30;
    const TOO_FAR_GPS_LOCATION = 10;
    const NONE = 0;
}
