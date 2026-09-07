<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CORS manual y simple para permitir que el web panel (Vue) y, en desarrollo,
 * la app Ionic corriendo en el navegador, consuman la API sin bloqueos.
 * Configura los origenes permitidos en .env -> CORS_ALLOWED_ORIGINS (separados por coma, o * para todos).
 */
class CorsFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $allowed = env('CORS_ALLOWED_ORIGINS', '*');
        $origin  = $request->getHeaderLine('Origin');

        $allowOrigin = '*';
        if ($allowed !== '*') {
            $list = array_map('trim', explode(',', $allowed));
            $allowOrigin = in_array($origin, $list, true) ? $origin : '';
        }

        $response = service('response');
        $response->setHeader('Access-Control-Allow-Origin', $allowOrigin ?: '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        $response->setHeader('Access-Control-Allow-Credentials', 'true');

        // Chrome en Android (y cada vez mas en escritorio) exige este header cuando la
        // pagina le pide algo a una IP de red local (192.168.x.x, 10.x.x.x, etc) -- la
        // llamada normal "Access-Control-Allow-Origin" no basta, o el navegador la
        // bloquea silenciosamente como "Failed to fetch" sin mensaje claro de CORS.
        // Ver: https://developer.chrome.com/blog/private-network-access-preflight
        if ($request->getHeaderLine('Access-Control-Request-Private-Network') === 'true') {
            $response->setHeader('Access-Control-Allow-Private-Network', 'true');
        }

        if ($request->getMethod() === 'options') {
            return $response->setStatusCode(204);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op, los headers ya se pusieron en before()
    }
}
