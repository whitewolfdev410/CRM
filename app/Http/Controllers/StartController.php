<?php

namespace App\Http\Controllers;

class StartController extends Controller
{
    public function getApp()
    {
        return view('start.index');
    }
    
    public function getAppOld()
    {
        return view('start.indexOld');
    }

    public function getTest()
    {
        $options = [
            'margin-top' => 14, // in mm
            'margin-bottom' => 10, // in mm
            'margin-left' => 0,
            'margin-right' => 0,
            //    'header-spacing' => 20 // in mm
        ];

        $data = [
            'companyName' => 'My test company',
        ];

        $pdf = \PDF::loadView('pdfs.fax', $data);
        $pdf->setPaper('A4')
            ->setOptions($options)
            ->setHeader($data)
            ->setFooter($data);

        return $pdf->stream('test.pdf');
    }
}
