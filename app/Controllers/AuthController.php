<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Usuario;

final class AuthController extends Controller
{
    public function login(): string
    {
        if (Auth::check()) {
            $this->redirect('/');
        }

        $error = '';

        if ($this->isPost()) {
            $rut      = trim((string) ($_POST['rut'] ?? ''));
            $password = (string) ($_POST['clave'] ?? '');

            $usuario = $rut !== '' ? Usuario::findByRut($rut) : null;

            if ($usuario !== null && $usuario->verifyPassword($password)) {
                Auth::login($usuario->id, $usuario->nombre, $usuario->rol);
                $this->redirect('/');
            }

            $error = 'Usuario o contraseña incorrectos.';
        }

        return $this->view('auth/login', [
            'title' => 'Iniciar sesión',
            'error' => $error,
            'rut'   => trim((string) ($_POST['rut'] ?? '')),
        ], null);
    }

    public function logout(): never
    {
        Auth::logout();
        $this->redirect('/login');
    }
}
