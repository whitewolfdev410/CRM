<?php

namespace App\Modules\Person\Http\Requests;

use App\Http\Requests\Request;

class AssignVendorAddressRequest extends Request
{
    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function rules()
    {
        $data = $this->all();
        $addressId = $this->input('id');
        $vendorPersonId = array_key_exists('vendor_person_id', $data) ? $data['vendor_person_id'] : 'NULL';
        $tradeId = array_key_exists('trade_type_id', $data) ? $data['trade_type_id'] : 'NULL';

        return [
            'vendor_person_id' => [
                'required',
                'unique:link_vendor_address,vendor_person_id,NULL,link_vendor_address_id,address_id,' . $addressId . ',trade_type_id,' . $tradeId,
            ],
            'trade_type_id'    => [
                'unique:link_vendor_address,trade_type_id,NULL,link_vendor_address_id,address_id,' . $addressId . ',vendor_person_id,' . $vendorPersonId,
            ],
        ];
    }
}
