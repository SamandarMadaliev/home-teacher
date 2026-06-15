<?php

namespace App\Http\Middleware;

use App\Support\HttpsUrlConfigurator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttpsUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        HttpsUrlConfigurator::apply($request);

        return $next($request);
    }
}
