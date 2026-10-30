<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Auth\Auth;
use Isis\Core\HttpException;
use Isis\Core\Response;
use Isis\Core\Session;
use Isis\Core\View;

abstract class Controller
{
    /** Campos que nunca voltam para o formulário após erro. */
    private const array NEVER_FLASH = ['password', 'password_confirmation', 'current_password', '_token'];

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = [], ?string $layout = 'app', int $status = 200): Response
    {
        return Response::html(View::render($template, $data, $layout), $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect($path);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $input
     */
    protected function backWithErrors(string $path, array $errors, array $input = [], ?string $message = null): Response
    {
        Session::flash('_errors', $errors);
        Session::flash('_old', array_diff_key(array_filter($input, 'is_string'), array_flip(self::NEVER_FLASH)));
        Session::flash('error', $message ?? 'Revise os campos destacados.');
        return Response::redirect($path);
    }

    protected function success(string $path, string $message): Response
    {
        Session::flash('success', $message);
        return Response::redirect($path);
    }

    protected function failure(string $path, string $message): Response
    {
        Session::flash('error', $message);
        return Response::redirect($path);
    }

    protected function authorize(string $permission): void
    {
        if (!Auth::can($permission)) {
            throw new HttpException(403);
        }
    }
}
