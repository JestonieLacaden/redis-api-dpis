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
            'files.*' => ['file', 'max:102400'],
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
        $contentType = (string) $request->header('Content-Type', '');
        $rawBody = $request->getContent();

        if ($rawBody !== '' && str_contains(strtolower($contentType), 'multipart/form-data')) {
            return $this->forwardRawMultipartRequest(
                '/forms/chromium/convert/html',
                $rawBody,
                $contentType
            );
        }

        $validated = $request->validate([
            'files' => ['required'],
            'files.*' => ['file', 'max:102400'],
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

    protected function forwardRawMultipartRequest(
        string $endpoint,
        string $rawBody,
        string $contentType
    ): Response {
        $targetUrl = $this->targetUrl($endpoint);

        $headers = [
            'Content-Type: ' . $contentType,
            'Accept: application/pdf',
            'Content-Length: ' . strlen($rawBody),
        ];

        $internalApiKey = (string) config('services.gotenberg.key');
        if ($internalApiKey !== '') {
            $headers[] = 'X-Api-Key: ' . $internalApiKey;
        }

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error('PDF proxy raw request error', [
                'endpoint' => $endpoint,
                'message' => $error,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to the PDF service. Please try again later.',
                'data' => null,
            ], 502);
        }

        if ($httpCode !== 200) {
            Log::warning('PDF upstream raw request failed', [
                'endpoint' => $endpoint,
                'status' => $httpCode,
                'body_preview' => mb_substr((string) $result, 0, 500),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'PDF conversion failed. Please try again later.',
                'data' => null,
            ], 502);
        }

        return response($result, 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    protected function forwardMultipartRequest(
        Request $request,
        string $endpoint,
        array $validated,
        bool $normalizeHtmlFiles = false
    ): Response {
        try {
            $client = Http::timeout(180)->accept('application/pdf');

            $internalApiKey = (string) config('services.gotenberg.key');
            if ($internalApiKey !== '') {
                $client = $client->withHeaders([
                    'X-Api-Key' => $internalApiKey,
                ]);
            }

            $indexAssigned = false;

            foreach ($this->extractFiles($request) as $file) {
                $filename = $this->resolveFilename($file, $normalizeHtmlFiles, $indexAssigned);
                $contents = file_get_contents($file->getRealPath());

                if ($contents === false) {
                    throw new \RuntimeException('Failed to read uploaded file contents.');
                }

                $client = $client->attach(
                    'files',
                    $contents,
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
                        '',
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

            return response($response->body(), 200, [
                'Content-Type' => $response->header('Content-Type', 'application/pdf'),
            ]);
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

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    protected function resolveFilename(
        UploadedFile $file,
        bool $normalizeHtmlFiles,
        bool &$indexAssigned
    ): string {
        $originalName = $file->getClientOriginalName();
        $lowerName = strtolower($originalName);

        if (!$normalizeHtmlFiles) {
            return $originalName;
        }

        if ($lowerName === 'index.html') {
            $indexAssigned = true;
            return 'index.html';
        }

        if ($lowerName === 'header.html') {
            return 'header.html';
        }

        if ($lowerName === 'footer.html') {
            return 'footer.html';
        }

        if (!$indexAssigned && str_ends_with($lowerName, '.html')) {
            $indexAssigned = true;
            return 'index.html';
        }

        return $originalName;
    }

    protected function targetUrl(string $endpoint): string
    {
        return rtrim((string) config('services.gotenberg.url'), '/') . $endpoint;
    }
}
