<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

class RequestContextResolver
{
    public function __construct(
        private Application $app
    ) {}

    public function resolve(): RequestContext
    {
        /** @var Request $request */
        $request = $this->app->make('request');

        $ipAddress = $this->normalize($request->ip());
        $userAgent = $this->normalize($request->userAgent());

        if ($this->app->runningInConsole() && $userAgent === 'Symfony') {
            return new RequestContext(null, null);
        }

        return new RequestContext($ipAddress, $userAgent);
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
