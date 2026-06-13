<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    /**
     * Bloqueia o acesso ao painel sem a flag de sessão de admin.
     * Guarda a URL pretendida para redirecionar após o login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('admin_logged_in')) {
            return redirect()->guest(route('admin.login'));
        }

        return $next($request);
    }
}
