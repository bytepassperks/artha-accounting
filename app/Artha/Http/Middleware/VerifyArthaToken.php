<?php

declare(strict_types=1);

namespace App\Artha\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyArthaToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('artha.api_token');

        if ($expected === '') {
            return new JsonResponse(['message' => 'Artha API is not enabled.'], 404);
        }

        $presented = $this->presentedToken($request);

        if ($presented === null || ! hash_equals($expected, $presented)) {
            return new JsonResponse(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }

    private function presentedToken(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        if (is_string($bearer) && $bearer !== '') {
            return $bearer;
        }

        $header = $request->header('X-Artha-Token');

        return is_string($header) && $header !== '' ? $header : null;
    }
}
