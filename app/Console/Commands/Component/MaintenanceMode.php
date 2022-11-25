<?php

namespace App\Console\Commands\Component;

use Illuminate\Support\Facades\Storage;

/**
 * Shuts down commands if MaintenanceFlag is set
 */
trait MaintenanceMode
{
    /**
     * Shuts down commands if MaintenanceFlag is set
     */
    private function maintenance()
    {
        if (Storage::exists('MaintenanceFlag')) {
            $flag = Storage::get('MaintenanceFlag');
            
            if ($flag == 1) {
                fwrite(STDERR, "There is no disk space left.\n");
                sleep(30);
                die(1);
            }
        }
    }
}
