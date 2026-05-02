<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CspReportController extends Controller
{
    /**
     * 受信本文の最大サイズ。CSP レポートは数 KB 程度のはずで、これ以上は DoS 試行と見なし破棄する。
     */
    private const MAX_PAYLOAD_BYTES = 16384;

    public function __invoke(Request $request): Response
    {
        // 本文サイズで早期に切り、ログ蓄積による DoS とディスク逼迫を抑える。
        if (strlen($request->getContent()) > self::MAX_PAYLOAD_BYTES) {
            return response()->noContent(Response::HTTP_PAYLOAD_TOO_LARGE);
        }

        foreach ($this->extractReports($request) as $report) {
            Log::warning('Content Security Policy violation reported.', $report);
        }

        return response()->noContent();
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function extractReports(Request $request): array
    {
        $payload = $this->decodePayload($request->getContent());

        if ($payload === null) {
            return [];
        }

        if (isset($payload['csp-report']) && is_array($payload['csp-report'])) {
            return [$this->normalizeLegacyReport($payload['csp-report'], $request)];
        }

        if (array_is_list($payload)) {
            return array_values(array_filter(
                array_map(fn (mixed $report): ?array => is_array($report)
                    ? $this->normalizeStructuredReport($report, $request)
                    : null, $payload)
            ));
        }

        return [$this->normalizeLegacyReport($payload, $request)];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(string $content): ?array
    {
        $content = trim($content);

        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, int|string>
     */
    private function normalizeLegacyReport(array $report, Request $request): array
    {
        return $this->withRequestContext(array_filter([
            'kind' => 'csp-report',
            'document_uri' => $this->limitString($report['document-uri'] ?? null),
            'blocked_uri' => $this->limitString($report['blocked-uri'] ?? null),
            'violated_directive' => $this->limitString($report['violated-directive'] ?? null),
            'effective_directive' => $this->limitString($report['effective-directive'] ?? null),
            'original_policy' => $this->limitString($report['original-policy'] ?? null, 2048),
            'referrer' => $this->limitString($report['referrer'] ?? null),
            'source_file' => $this->limitString($report['source-file'] ?? null),
            'line_number' => $this->normalizeInteger($report['line-number'] ?? null),
            'column_number' => $this->normalizeInteger($report['column-number'] ?? null),
            'disposition' => $this->limitString($report['disposition'] ?? null),
            'status_code' => $this->normalizeInteger($report['status-code'] ?? null),
            'script_sample' => $this->limitString($report['script-sample'] ?? null, 255),
        ], static fn (mixed $value): bool => $value !== null), $request);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, int|string>|null
     */
    private function normalizeStructuredReport(array $report, Request $request): ?array
    {
        if (($report['type'] ?? null) !== 'csp-violation' || ! isset($report['body']) || ! is_array($report['body'])) {
            return null;
        }

        /** @var array<string, mixed> $body */
        $body = $report['body'];

        return $this->withRequestContext(array_filter([
            'kind' => 'report-to',
            'type' => 'csp-violation',
            'age' => $this->normalizeInteger($report['age'] ?? null),
            'document_uri' => $this->limitString($report['url'] ?? $body['documentURL'] ?? null),
            'blocked_uri' => $this->limitString($body['blockedURL'] ?? null),
            'violated_directive' => $this->limitString($body['violatedDirective'] ?? null),
            'effective_directive' => $this->limitString($body['effectiveDirective'] ?? null),
            'original_policy' => $this->limitString($body['originalPolicy'] ?? null, 2048),
            'referrer' => $this->limitString($body['referrer'] ?? null),
            'source_file' => $this->limitString($body['sourceFile'] ?? null),
            'line_number' => $this->normalizeInteger($body['lineNumber'] ?? null),
            'column_number' => $this->normalizeInteger($body['columnNumber'] ?? null),
            'disposition' => $this->limitString($body['disposition'] ?? null),
            'status_code' => $this->normalizeInteger($body['statusCode'] ?? null),
        ], static fn (mixed $value): bool => $value !== null), $request);
    }

    /**
     * @param  array<string, int|string>  $report
     * @return array<string, int|string>
     */
    private function withRequestContext(array $report, Request $request): array
    {
        $report['user_agent'] = $this->limitString($request->userAgent(), 512) ?? 'unknown';
        $report['ip_address'] = $this->limitString($request->ip(), 64) ?? 'unknown';

        return $report;
    }

    private function limitString(mixed $value, int $limit = 1024): ?string
    {
        return is_string($value) && $value !== '' ? Str::limit($value, $limit, '') : null;
    }

    private function normalizeInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
