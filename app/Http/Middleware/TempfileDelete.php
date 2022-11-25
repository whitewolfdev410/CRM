<?php

namespace App\Http\Middleware;

use Closure;

class TempfileDelete
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    /**
     * Removes temporary file from filesystem
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param \Symfony\Component\HttpFoundation\Response $response
     */
    public function terminate($request, $response)
    {
        $tempFile = \Session::get('temp_file_delete', '');
        if ($tempFile) {
            @unlink($tempFile);
            \Session::forget('temp_file_delete');
        }
    }
}
