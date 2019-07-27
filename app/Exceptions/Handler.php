<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        FacilityNotFoundException::class,
        ReturnPathNotFoundException::class,
        //JWTTokenException::class,
        NotSecuredException::class
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param \Exception $exception
     *
     * @return void
     * @throws \Exception
     */
    public function report(Exception $exception)
    {
        if (app()->bound('sentry') && $this->shouldReport($exception)) {
            app('sentry')->captureException($exception);
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Exception               $exception
     *
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        if ($exception instanceof AuthenticationException) {
            return response()->unauthenticated();
        }
        if ($exception instanceof NotFoundHttpException) {
            return response()->notfound();
        }
        if (method_exists($exception, 'getStatus')) {
            $status = $exception->getStatus();
        } else {
            $status = 500;
        }
        if (method_exists($exception, 'getSeverity') && $exception->getSeverity() == E_USER_DEPRECATED) {
            return false;
        }

        return response()->json([
            'status'    => 'error',
            'msg'       => $exception->getMessage(),
            'exception' => get_class($exception)
        ], $status);
        //return parent::render($request, $exception);
    }

    /**
     * Override the unauthenicated method to return JSON and in our format.
     *
     * @param \Illuminate\Http\Request $request
     * @param AuthenticationException  $exception
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function unauthenticated($request, \Illuminate\Auth\AuthenticationException $exception)
    {
        return response()->json([
            'status' => 'error',
            'msg'    => 'Unauthenticated'
        ], 401);
    }
}
