<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;

class CheckToken
{

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $cpp_session = $request->cookie('cpp_session');
        if (empty(session()->getId())) {
            return response('Unauthorized. No active session.', 401);
        }
        if (empty(auth()->user())) {
            return response('Unauthorized. No active user.', 401);
        }
        $UserSession = UserSession::find(session()->getId());
        if (empty($UserSession)) {
            return response('Unauthorized. No active user session.', 401);
        }
//        logger('time(): ' . time());
//        logger('UserSession->expiration_date: '. $UserSession->expiration_date);
//        logger('strtotime($UserSession->expiration_date): ' . strtotime($UserSession->expiration_date));
        if (time() > strtotime($UserSession->expiration_date)) {
            return response('Unauthorized. User session has expired.', 401);
        }
        if ($request->route()->getActionMethod() !== 'checkToken') {
            $expiration_date = time() + (env('SESSION_LIFETIME') * 60);
            $UserSession->update([
                'expiration_date' => date('Y-m-d H:i:s', $expiration_date)
            ]);
        }
        return $next($request);
    }
}
