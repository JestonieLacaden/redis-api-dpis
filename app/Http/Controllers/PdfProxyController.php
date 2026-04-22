<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PdfProxyController extends Controller
{
    public function libreofficeConvert(Request $request): Response
    {
        return $this->forwardMultipart($request, '/forms/libreoffice/convert');
    }

    public function chromiumConvertHtml(Request $request): Response
    {
        return $this->forwardMultipart($request, '/forms/chromium/convert/html');
    }

    public function chromiumConvertUrl(Request $request): Response
    {
        return $this->forwardMultipart($request, '/forms/chromium/convert/url');
    }

    protected function forwardMultipart(Request $request, string $endpoint): Response
    {
        $baseUrl = rtrim((string) config('services.gotenberg.url'), '/');
        $targetUrl = $baseUrl . $endpoint;

        if (!$request->hasFile('files') && !$request->filled('url')) {
            return response()->json([
                'success' => false,
                'message' => 'Missing required multipart fields.',
                'data' => null,
            ], 422);
        }

        try {
            $client = Http::timeout(180)->accept('application/pdf');

            $internalApiKey = config('services.gotenberg.key');
            if (!empty($internalApiKey)) {
                $client = $client->withHeaders([
                    'X-Api-Key' => $internalApiKey,
                ]);
            }

            $files = $request->file('files');
            if ($files instanceof UploadedFile) {
                $files = [$files];
            }

            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file instanceof UploadedFile) {
                        $client = $client->attach(
                            'files',
                            file_get_contents($file->getRealPath()),
                            $file->getClientOriginalName(),
                            ['Content-Type' => $file->getMimeType() ?: 'application/octet-stream']
                        );
                    }
                }
            }

            foreach ($request->except(['files']) as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $item) {
                        $client = $client->attach($key, (string) $item, null, ['Content-Type' => 'text/plain']);
                    }
                    continue;
                }

                $client = $client->attach($key, (string) $value, null, ['Content-Type' => 'text/plain']);
            }

            $upstream = $client->post($targetUrl);

            $headers = [
                'Content-Type' => $upstream->header('Content-Type', 'application/pdf'),
            ];

            if ($upstream->header('Content-Disposition')) {
                $headers['Content-Disposition'] = $upstream->header('Content-Disposition');
            }

            return response($upstream->body(), $upstream->status(), $headers);
        } catch (\Throwable $e) {
            Log::error('PDF proxy request failed', [
                'endpoint' => $endpoint,
                'target_url' => $targetUrl,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'PDF proxy request failed.',
                'data' => config('app.debug') ? $e->getMessage() : null,
            ], 502);
        }
    }
}
