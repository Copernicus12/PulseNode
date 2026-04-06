<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $language = $request->cookie('interface_language');
        $supportedLanguages = ['en', 'ro'];

        if (! in_array($language, $supportedLanguages, true)) {
            $language = config('app.locale', 'en');
        }

        App::setLocale($language);

        View::share('appearance', $request->cookie('appearance') ?? 'system');
        View::share('interfaceLanguage', $language);

        return $next($request);
    }
}
