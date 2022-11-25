<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Modules\Mobile\Exceptions\NoMoreDiskSpaceException;

class CheckDiskFreeSpace
{
    /**
     * Check if is free space on disk if no then return 500 code
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (config('mobile.check_disk_free_space_on_server', true)) {
            $diskPartition = config('mobile.disk_partition_where_is_crm', '/');
            $minAllowedFreeSpace = (int)config('mobile.min_allowed_disk_free_space_in_mb', 50000000000000000);
            $freeSpace = disk_free_space($diskPartition);
            if ($freeSpace < $minAllowedFreeSpace * 1048576) {
                Storage::put('MaintenanceFlag', '1');
                throw \App::make(NoMoreDiskSpaceException::class);
            } else {
                Storage::put('MaintenanceFlag', '0');
            }
        }

        return $next($request);
    }
}
