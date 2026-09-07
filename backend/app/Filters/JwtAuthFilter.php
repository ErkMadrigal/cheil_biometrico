<?php

namespace App\Filters;

use App\Libraries\Jwt;
use App\Libraries\AuthContext;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if (empty($header) || !preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return service('response')
                ->setJSON(['status' => 'error', 'message' => 'Token no proporcionado.'])
                ->setStatusCode(401);
        }

        $jwt = new Jwt();
        $claims = $jwt->verify($m[1]);

        if ($claims === null) {
            return service('response')
                ->setJSON(['status' => 'error', 'message' => 'Token invalido o expirado.'])
                ->setStatusCode(401);
        }

        // Restriccion opcional por rol: filtro('jwtAuth:admin,supervisor')
        if (!empty($arguments)) {
            $allowedRoles = $arguments;
            if (!in_array($claims['role'] ?? '', $allowedRoles, true)) {
                return service('response')
                    ->setJSON(['status' => 'error', 'message' => 'No tienes permiso para esta accion.'])
                    ->setStatusCode(403);
            }
        }

        // Se deja disponible para los controladores via AuthContext::user()
        AuthContext::setUser($claims);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op
    }
}
