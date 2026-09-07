<?php

namespace App\Controllers;

/**
 * Sirve los archivos que se guardan en writable/uploads (fotos de empleados,
 * selfies de checadas). Se guardan FUERA de public/ a proposito -no son
 * accesibles por Apache directamente-, este controlador es la unica puerta
 * de entrada, asi luego se puede meter auth/firma de URL sin mover archivos.
 *
 * GET /uploads/{folder}/{filename}  ej: /uploads/employees/ab12cd34.jpg
 */
class FilesController extends BaseController
{
    public function show(string $folder, string $filename)
    {
        // Evita path traversal (../../etc/passwd) y solo permite nombres de archivo "planos"
        $folder   = basename($folder);
        $filename = basename($filename);

        $path = WRITEPATH . 'uploads/' . $folder . '/' . $filename;

        if (!is_file($path)) {
            return $this->response->setStatusCode(404)->setBody('No encontrado');
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody(file_get_contents($path));
    }
}
