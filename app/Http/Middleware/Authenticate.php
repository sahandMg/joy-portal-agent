<?php

namespace App\Http\Middleware;

use App\Responses\ErrorResponse;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Auth;
use function PHPUnit\Framework\isJson;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (!$request->expectsJson() && $request->is('admin/*')) {
            return route('admin.login');
        }
//        if (!$request->isJson()) {
//            if (!Auth::check()) {
//                return redirect()->route('chat.login');
//            }
//            return redirect()->route('chat');
//        }
        ErrorResponse::Error(401, 'دسترسی نامعتبر');
//        if (! $request->expectsJson()) {
//            return route('login');
//        } else {
//
//        }
    }
}
