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

        // $request->ip() は bootstrap/app.php の trustProxies で許可された経路のみ X-Forwarded-For を信頼する。
        // 改ざんの恐れがある経路 (TRUSTED_PROXIES の範囲外) からの値はそのまま remote_addr として扱われ、
        // AuditLog 上の IP は信頼可能な範囲に限定される。
        $ipAddress = $this->normalize($request->ip());
        // User-Agent はクライアントが任意に設定可能なため、監査ログでは「自己申告値」として扱う前提。
        // 整合性検証用途ではなく、トリアージ・統計用のヒントとしてのみ信頼すべき。
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
