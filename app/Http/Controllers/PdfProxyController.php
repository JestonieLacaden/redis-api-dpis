<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PdfProxyController extends Controller
{
    public function libreofficeConvert(Request $request): Response
    {
        return $this->proxyMultipartRequest($request, '/forms/libreoffice/convert');
    }

    public function chromiumConvertHtml(Request $request): Response
    {
        return $this->proxyMultipartRequest($request, '/forms/chromium/convert/html');
    }

    public function chromiumConvertUrl(Request $request): Response
    {
        return $this->proxyMultipartRequest($request, '/forms/chromium/convert/url');
    }

    protected function proxyMultipartRequest(Request $request, string $endpoint): Response
    {
        $baseUrl = rtrim((string) config('services.gotenberg.url'), '/');
        $targetUrl = $baseUrl . $endpoint;
        $contentType = (string) $request->header('Content-Type', '');
        $body = $request->getContent();

        if ($contentType === '' || $body === '') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid multipart request body.',
                'data' => null,
            ], 422);
        }

        try {
            $headers = [
                'Content-Type' => $contentType,
                'Accept' => 'application/pdf',
            ];

            $internalApiKey = config('services.gotenberg.key');
            if (!empty($internalApiKey)) {
                $headers['X-Api-Key'] = $internalApiKey;
            }

            $upstream = Http::withHeaders($headers)
                ->withBody($body, $contentType)
                ->timeout(180)
                ->send('POST', $targetUrl);

            $responseHeaders = [
                'Content-Type' => $upstream->header('Content-Type', 'application/pdf'),
            ];

            $contentDisposition = $upstream->header('Content-Disposition');
            if ($contentDisposition) {
                $responseHeaders['Content-Disposition'] = $contentDisposition;
            }

            return response($upstream->body(), $upstream->status(), $responseHeaders);
        } catch (\Throwable $e) {
            Log::error('PDF proxy request failed', [
                'endpoint' => $endpoint,
                'exception' => class_basename($e),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'PDF proxy request failed.',
                'data' => config('app.debug') ? [
                    'endpoint' => $endpoint,
                    'exception' => class_basename($e),
                ] : null,
            ], 502);
        }
    }
}
