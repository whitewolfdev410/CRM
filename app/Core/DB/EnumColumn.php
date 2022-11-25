<?php

namespace App\Core\DB;

use Illuminate\Database\DatabaseManager;

class EnumColumn
{
    /**
     * Database instance
     *
     * @var DatabaseManager
     */
    private $db;

    /**
     * Initialize DB
     *
     * @param DatabaseManager $db
     */
    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }

    /**
     * Get possible enum values from column in table in database
     *
     * @param string $table
     * @param string $column
     *
     * @return array
     */
    public function get($table, $column)
    {
        $data =
            $this->db->select(
                "show columns FROM {$table} WHERE `field` = ?",
                [$column]
            );
        if (!count($data)) {
            return [];
        }
        $data = $data[0];

        preg_match_all("/'(.*?)'/", $data->Type, $enumArray);

        return $enumArray[1];
    }
}
