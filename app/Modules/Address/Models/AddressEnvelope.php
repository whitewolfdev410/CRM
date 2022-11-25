<?php namespace App\Modules\Address\Models;

use tFPDF;

class AddressEnvelope extends tFPDF
{

    // Private properties
    public $_Avery_Name = '';                // Name of format
    public $_Margin_Left = 0;                // Left margin of labels
    public $_Margin_Top = 0;                // Top margin of labels
    public $_X_Space = 0;                // Horizontal space between 2 labels
    public $_Y_Space = 0;                // Vertical space between 2 labels
    public $_X_Number = 0;                // Number of labels horizontally
    public $_Y_Number = 0;                // Number of labels vertically
    public $_Width = 0;                // Width of label
    public $_Height = 0;                // Height of label
    public $_Char_Size = 10;                // Character size
    public $_Line_Height = 10;                // Default line height
    public $_Metric = 'mm';                // Type of metric for labels.. Will help to calculate good values
    public $_Metric_Doc = 'mm';                // Type of metric for the document
    public $_Font_Name = 'DejaVu';            // Name of the font

    public $_COUNTX = 1;
    public $_COUNTY = 1;


    // Listing of labels size
    public $_Avery_Labels
        = [
            '5160' => [
                'name' => '5160',
                'paper-size' => 'letter',
                'metric' => 'mm',
                'marginLeft' => 1.762,
                'marginTop' => 10.7,
                'NX' => 3,
                'NY' => 10,
                'SpaceX' => 3.175,
                'SpaceY' => 0,
                'width' => 66.675,
                'height' => 25.4,
                'font-size' => 8
            ],
            '5161' => [
                'name' => '5161',
                'paper-size' => 'letter',
                'metric' => 'mm',
                'marginLeft' => 0.967,
                'marginTop' => 10.7,
                'NX' => 2,
                'NY' => 10,
                'SpaceX' => 3.967,
                'SpaceY' => 0,
                'width' => 101.6,
                'height' => 25.4,
                'font-size' => 8
            ],
            '5162' => [
                'name' => '5162',
                'paper-size' => 'letter',
                'metric' => 'mm',
                'marginLeft' => 0.97,
                'marginTop' => 20.224,
                'NX' => 2,
                'NY' => 7,
                'SpaceX' => 4.762,
                'SpaceY' => 0,
                'width' => 100.807,
                'height' => 35.72,
                'font-size' => 8
            ],
            '5163' => [
                'name' => '5163',
                'paper-size' => 'letter',
                'metric' => 'mm',
                'marginLeft' => 1.762,
                'marginTop' => 10.7,
                'NX' => 2,
                'NY' => 5,
                'SpaceX' => 3.175,
                'SpaceY' => 0,
                'width' => 101.6,
                'height' => 50.8,
                'font-size' => 8
            ],
            '5164' => [
                'name' => '5164',
                'paper-size' => 'letter',
                'metric' => 'in',
                'marginLeft' => 0.148,
                'marginTop' => 0.5,
                'NX' => 2,
                'NY' => 3,
                'SpaceX' => 0.2031,
                'SpaceY' => 0,
                'width' => 4.0,
                'height' => 3.33,
                'font-size' => 12
            ],
            '8600' => [
                'name' => '8600',
                'paper-size' => 'letter',
                'metric' => 'mm',
                'marginLeft' => 7.1,
                'marginTop' => 19,
                'NX' => 3,
                'NY' => 10,
                'SpaceX' => 9.5,
                'SpaceY' => 3.1,
                'width' => 66.6,
                'height' => 25.4,
                'font-size' => 8
            ],
            'starter' => [
                'name' => 'starter',
                'paper-size' => ['102', '50'],
                'metric' => 'mm',
                'marginLeft' => 0,
                'marginTop' => 0,
                'NX' => 1,
                'NY' => 1,
                'SpaceX' => 0,
                'SpaceY' => 0,
                'width' => 102,
                'height' => 59,
                'font-size' => 8
            ],
            '30321' => [
                'name' => '30321',
                'paper-size' => ['89', '36'],
                'metric' => 'mm',
                'marginLeft' => 0,
                'marginTop' => 0,
                'NX' => 1,
                'NY' => 1,
                'SpaceX' => 0,
                'SpaceY' => 0,
                'width' => 89,
                'height' => 36,
                'font-size' => 8
            ],
            'L7163' => [
                'name' => 'L7163',
                'paper-size' => 'A4',
                'metric' => 'mm',
                'marginLeft' => 5,
                'marginTop' => 15,
                'NX' => 2,
                'NY' => 7,
                'SpaceX' => 25,
                'SpaceY' => 0,
                'width' => 99.1,
                'height' => 38.1,
                'font-size' => 9
            ]
        ];


    /**
     * Data that will be used to generate PDF
     *
     * @var array
     */
    protected $userData = [];

    /**
     * Convert units (in to mm, mm to in). $src and $dest must be 'in' or 'mm'
     *
     * @param float $value
     * @param string $src
     * @param string $dest
     *
     * @return float
     */
    protected function _Convert_Metric($value, $src, $dest)
    {
        if ($src != $dest) {
            $tab['in'] = 39.37008;
            $tab['mm'] = 1000;

            return $value * $tab[$dest] / $tab[$src];
        } else {
            return $value;
        }
    }


    /**
     * Give the height for a char size given.
     *
     * @param int $pt
     *
     * @return int
     */
    protected function _Get_Height_Chars($pt)
    {
        // Array matching character sizes and line heights
        $_Table_Hauteur_Chars = [
            6 => 2,
            7 => 2.5,
            8 => 3,
            9 => 4,
            10 => 5,
            11 => 6,
            12 => 7,
            13 => 8,
            14 => 9,
            15 => 10
        ];
        if (in_array($pt, array_keys($_Table_Hauteur_Chars))) {
            return $_Table_Hauteur_Chars[$pt];
        } else {
            return 100; // There is a prob..
        }
    }


    /**
     * Customizes PDF document settings based on $format
     *
     * @param string $format
     */
    protected function _Set_Format($format)
    {
        $this->_Metric = $format['metric'];
        $this->_Avery_Name = $format['name'];
        $this->_Margin_Left = $this->_Convert_Metric(
            $format['marginLeft'],
            $this->_Metric,
            $this->_Metric_Doc
        );
        $this->_Margin_Top = $this->_Convert_Metric(
            $format['marginTop'],
            $this->_Metric,
            $this->_Metric_Doc
        );
        $this->_X_Space = $this->_Convert_Metric(
            $format['SpaceX'],
            $this->_Metric,
            $this->_Metric_Doc
        );
        $this->_Y_Space = $this->_Convert_Metric(
            $format['SpaceY'],
            $this->_Metric,
            $this->_Metric_Doc
        );
        $this->_X_Number = $format['NX'];
        $this->_Y_Number = $format['NY'];
        $this->_Width = $this->_Convert_Metric(
            $format['width'],
            $this->_Metric,
            $this->_Metric_Doc
        );
        $this->_Height = $this->_Convert_Metric(
            $format['height'],
            $this->_Metric,
            $this->_Metric_Doc
        );
        $this->Set_Font_Size($format['font-size']);
    }


    /**
     * Class constructor - sets correct PDF format
     *
     * @param string $format
     * @param string $unit
     * @param int $posX
     * @param int $posY
     */
    public function __construct($format, $unit = 'mm', $posX = 1, $posY = 1)
    {
        if (is_array($format)) {
            // Custom format
            $Tformat = $format;
        } else {
            // Avery format
            $Tformat = $this->_Avery_Labels[$format];
        }

        // changed due to tFPDF version update
        // parent::tFPDF('L', $Tformat['metric'], $Tformat['paper-size']);
        parent::__construct('L', $Tformat['metric'], $posX, $posY);

        $this->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);

//        parent::FPDF('P', $Tformat['metric'], array("50","100"));
        $this->_Set_Format($Tformat);
        $this->Set_Font_Name('DejaVu');
        $this->SetMargins(0, 0);
        $this->SetAutoPageBreak(false);

        $this->_Metric_Doc = $unit;
        // Start at the given label position
        if ($posX > 1) {
            $posX--;
        } else {
            $posX = 0;
        }
        if ($posY > 1) {
            $posY--;
        } else {
            $posY = 0;
        }
        if ($posX >= $this->_X_Number) {
            $posX = $this->_X_Number - 1;
        }
        if ($posY >= $this->_Y_Number) {
            $posY = $this->_Y_Number - 1;
        }
        $this->_COUNTX = $posX;
        $this->_COUNTY = $posY;
    }


    /**
     * Sets the character size. This changes the line height too
     *
     * @param float $pt
     */
    public function Set_Font_Size($pt)
    {
        if ($pt > 3) {
            $this->_Char_Size = $pt;
            $this->_Line_Height = $this->_Get_Height_Chars($pt);
            $this->SetFontSize($this->_Char_Size);
        }
    }


    /**
     * Changes font name
     *
     * @param string $fontname
     */
    public function Set_Font_Name($fontname)
    {
        if ($fontname != '') {
            $this->_Font_Name = $fontname;
            $this->SetFont($this->_Font_Name);
        }
    }

    /**
     * Generates PDF document from data and return its content as string
     *
     * @param array $data
     *
     * @return string
     */
    public function generate(array $data)
    {
        $this->userData = $data;

        if ($data['kind'] == 'company') {
            $count = count($data['person_companies_with_details']);
            if ($count == 0) {
                $this->addNewLabel(0);
            } else {
                for ($i = 0; $i < $count; ++$i) {
                    $this->addNewLabel($i);
                }
            }
        } else {
            $this->addNewLabel(0);
        }

        return $this->Output('', "S");
    }


    /**
     * Adds Label to PDF based on current index $i
     *
     * @param integer $i
     */
    public function addNewLabel($i)
    {
        // We are in a new page, then we must add a page
        if (($this->_COUNTX == 0) and ($this->_COUNTY == 0)) {
            $this->AddPage();
        }

        $_PosX = $this->_Margin_Left + ($this->_COUNTX * ($this->_Width
                    + $this->_X_Space));
        $_PosY = $this->_Margin_Top + ($this->_COUNTY * ($this->_Height
                    + $this->_Y_Space));
        $_PosX += 7;

        $x = 15;
        $font_size = 12;

        if ($this->userData["custom_1"] != "") {
            $this->SetXY($_PosX + 2, $_PosY + $x);
            $this->SetFont('DejaVu', '', $font_size);
            $this->MultiCell(
                $this->_Width,
                $this->_Line_Height,
                $this->userData["custom_1"]
            );
            $x = $x + 5;
        }

        // adding related person name and position
        if (isset($this->userData['person_companies_with_details'][$i])
            &&
            $this->userData['person_companies_with_details'][$i]["rel_1_custom_1"]
            != ""
        ) {
            $this->SetXY($_PosX + 2, $_PosY + $x);
            $this->SetFont('DejaVu', '', $font_size);
            if ($this->userData['person_companies_with_details'][$i]["rel_1_position"]
                != ""
            ) {
                $this->userData['person_companies_with_details'][$i]["rel_1_custom_3"]
                    .= ", "
                    .
                    $this->userData['person_companies_with_details'][$i]["rel_1_position"];
            }
            $this->MultiCell(
                $this->_Width,
                $this->_Line_Height,
                $this->userData['person_companies_with_details'][$i]["rel_1_custom_1"]
                . " "
                .
                $this->userData['person_companies_with_details'][$i]["rel_1_custom_3"]
            );
            $x = $x + 5;
        }

        $this->SetXY($_PosX + 2, $_PosY + $x);
        $this->SetFont('DejaVu', '', $font_size);
        $this->MultiCell(
            $this->_Width,
            $this->_Line_Height,
            $this->userData['address_1']
        );
        $x = $x + 5;
        if ($this->userData['address_2'] != "") {
            $this->SetXY($_PosX + 2, $_PosY + $x);
            $this->SetFont('DejaVu', '', $font_size);
            $this->MultiCell(
                $this->_Width,
                $this->_Line_Height,
                $this->userData['address_2']
            );
            $x = $x + 5;
        }

        $this->SetXY($_PosX + 2, $_PosY + $x);
        $this->SetFont('DejaVu', '', $font_size);
        $this->MultiCell(
            $this->_Width,
            $this->_Line_Height,
            $this->userData['city'] . ", "
            . strtoupper($this->userData['state']) . " "
            . $this->userData['zip_code']
        );
        $x = $x + 5;
        if ($this->userData['country'] != "US") {
            if ($this->userData['country_rel']) {
                $this->SetXY($_PosX + 2, $_PosY + $x);
                $this->SetFont('DejaVu', '', $font_size);
                $this->MultiCell(
                    $this->_Width,
                    $this->_Line_Height,
                    $this->userData['country_rel']['name']
                );
                $x = $x + 5;
            }
        }

        $this->_COUNTY++;

        if ($this->_COUNTY == $this->_Y_Number) {
            // End of column reached, we start a new one
            $this->_COUNTX++;
            $this->_COUNTY = 0;
        }

        if ($this->_COUNTX == $this->_X_Number) {
            // Page full, we start a new one
            $this->_COUNTX = 0;
            $this->_COUNTY = 0;
        }
    }
}
