<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\AuthContext;
use App\Libraries\Jwt;
use App\Models\UserModel;

class AuthController extends BaseController
{
    /**
     * POST /api/v1/auth/login
     * Usado tanto por el web panel (admin/supervisor) como por la app movil (rol employee).
     */
    public function login()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        $data = $this->request->getJSON(true) ?? $this->request->getPost();

        if (!$this->validateInput($data, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'errors' => $this->validator->getErrors(),
            ]);
        }

        $userModel = new UserModel();
        $user = $userModel->select('users.*, roles.name as role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.email', $data['email'])
            ->first();

        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Correo o contraseña incorrectos.',
            ]);
        }

        if ((int) $user['is_active'] !== 1) {
            return $this->response->setStatusCode(403)->setJSON([
                'status' => 'error',
                'message' => 'Este usuario esta deshabilitado.',
            ]);
        }

        $userModel->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

        $jwt = new Jwt();
        $token = $jwt->issue([
            'sub'         => $user['id'],
            'role'        => $user['role_name'],
            'employee_id' => $user['employee_id'],
            'name'        => $user['name'],
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'token'  => $token,
            'user'   => [
                'id'          => $user['id'],
                'name'        => $user['name'],
                'email'       => $user['email'],
                'role'        => $user['role_name'],
                'employee_id' => $user['employee_id'],
            ],
        ]);
    }

    /**
     * GET /api/v1/auth/me  (requiere JWT)
     */
    public function me()
    {
        $claims = AuthContext::user();

        return $this->response->setJSON([
            'status' => 'success',
            'user'   => [
                'id'          => $claims['sub'] ?? null,
                'name'        => $claims['name'] ?? null,
                'role'        => $claims['role'] ?? null,
                'employee_id' => $claims['employee_id'] ?? null,
            ],
        ]);
    }
}
