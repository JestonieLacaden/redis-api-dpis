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
        $validated = $request->validate([
            'files' => ['required'],
            'files.*' => ['file', 'max:51200'],
        ]);

        return $this->forwardMultipartRequest(
            $request,
            '/forms/libreoffice/convert',
            $validated,
            false
        );
    }

    public function chromiumConvertHtml(Request $request): Response
    {
        $validated = $request->validate([
            'files' => ['required'],
            'files.*' => ['file', 'max:51200'],
            'paperWidth' => ['nullable', 'numeric'],
            'paperHeight' => ['nullable', 'numeric'],
            'marginTop' => ['nullable', 'numeric'],
            'marginBottom' => ['nullable', 'numeric'],
            'marginLeft' => ['nullable', 'numeric'],
            'marginRight' => ['nullable', 'numeric'],
            'scale' => ['nullable', 'numeric'],
            'printBackground' => ['nullable'],
            'landscape' => ['nullable'],
            'nativePageRanges' => ['nullable', 'string'],
            'preferCssPageSize' => ['nullable'],
            'generateDocumentOutline' => ['nullable'],
            'generateTaggedPdf' => ['nullable'],
            'singlePage' => ['nullable'],
        ]);

        return $this->forwardMultipartRequest(
            $request,
            '/forms/chromium/convert/html',
            $validated,
            true
        );
    }

    public function chromiumConvertUrl(Request $request): Response
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'paperWidth' => ['nullable', 'numeric'],
            'paperHeight' => ['nullable', 'numeric'],
            'marginTop' => ['nullable', 'numeric'],
            'marginBottom' => ['nullable', 'numeric'],
            'marginLeft' => ['nullable', 'numeric'],
            'marginRight' => ['nullable', 'numeric'],
            'scale' => ['nullable', 'numeric'],
            'printBackground' => ['nullable'],
            'landscape' => ['nullable'],
            'nativePageRanges' => ['nullable', 'string'],
            'preferCssPageSize' => ['nullable'],
            'generateDocumentOutline' => ['nullable'],
            'generateTaggedPdf' => ['nullable'],
            'singlePage' => ['nullable'],
        ]);

        return $this->forwardMultipartRequest(
            $request,
            '/forms/chromium/convert/url',
            $validated,
            false
        );
    }

    protected function forwardMultipartRequest(
        Request $request,
        string $endpoint,
        array $validated,
        bool $forceIndexHtml = false
    ): Response {
        try {
            $client = Http::timeout(180)->accept('application/pdf');

            $internalApiKey = (string) config('services.gotenberg.key');
            if ($internalApiKey !== '') {
                $client = $client->withHeaders([
                    'X-Api-Key' => $internalApiKey,
                ]);
            }

            $files = $this->extractFiles($request);

            foreach ($files as $index => $file) {
                $filename = $file->getClientOriginalName();

                if ($forceIndexHtml && $index === 0) {
                    $filename = 'index.html';
                }

                $client = $client->attach(
                    'files',
                    file_get_contents($file->getRealPath()),
                    $filename,
                    ['Content-Type' => $file->getMimeType() ?: 'application/octet-stream']
                );
            }

            foreach ($this->extractTextFields($validated) as $key => $value) {
                $values = is_array($value) ? $value : [$value];

                foreach ($values as $item) {
                    $client = $client->attach(
                        $key,
                        $this->normalizeScalarValue($item),
                        null,
                        ['Content-Type' => 'text/plain']
                    );
                }
            }

            $response = $client->post($this->targetUrl($endpoint));

            if (!$response->successful()) {
                Log::warning('PDF upstream request failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body_preview' => mb_substr($response->body(), 0, 500),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'PDF conversion failed. Please try again later.',
                    'data' => null,
                ], 502);
            }

            $headers = [
                'Content-Type' => $response->header('Content-Type', 'application/pdf'),
            ];

            $contentDisposition = $response->header('Content-Disposition');
            if ($contentDisposition) {
                $headers['Content-Disposition'] = $contentDisposition;
            }

            return response($response->body(), 200, $headers);
        } catch (\Throwable $e) {
            Log::error('PDF proxy request error', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to the PDF service. Please try again later.',
                'data' => null,
            ], 502);
        }
    }

    /**
     * @return UploadedFile[]
     */
    protected function extractFiles(Request $request): array
    {
        $files = $request->file('files');

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (is_array($files)) {
            return array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));
        }

        return [];
    }

    protected function extractTextFields(array $validated): array
    {
        unset($validated['files']);

        return $validated;
    }

    protected function normalizeScalarValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    protected function targetUrl(string $endpoint): string
    {
        return rtrim((string) config('services.gotenberg.url'), '/') . $endpoint;
    }
}
