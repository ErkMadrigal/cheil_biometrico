<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController estandar de CodeIgniter 4.
 * Todos los controladores Api\* heredan de aqui (directa o indirectamente).
 *
 * OJO: $request, $response y $logger NO se redeclaran aqui a proposito -
 * CodeIgniter\Controller ya los declara sin tipo, y PHP no permite que una
 * clase hija le agregue un tipo a una propiedad heredada (fatal error).
 */
abstract class BaseController extends Controller
{
    protected $helpers = ['url', 'form'];

    /** @var \CodeIgniter\Validation\ValidationInterface|null */
    protected $validator;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
    }

    /**
     * Valida un arreglo de datos (body JSON, etc) contra reglas de CI4.
     * Deja el validador en $this->validator para leer errores con getErrors().
     */
    protected function validateInput(array $data, array $rules, array $messages = []): bool
    {
        $this->validator = \Config\Services::validation();
        $this->validator->setRules($rules, $messages);

        return $this->validator->run($data);
    }

    /**
     * Respuesta JSON de error homogenea para toda la API.
     */
    protected function fail(string $message, int $status = 400, array $errors = [])
    {
        $payload = ['status' => 'error', 'message' => $message];
        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }
        return $this->response->setStatusCode($status)->setJSON($payload);
    }

    /**
     * Respuesta JSON de exito homogenea para toda la API.
     */
    protected function ok($data = [], string $message = 'OK', int $status = 200)
    {
        return $this->response->setStatusCode($status)->setJSON([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ]);
    }
}
