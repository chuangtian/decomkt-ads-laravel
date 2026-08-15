<?php

namespace App\Services\StudentDiscounts;

use App\Services\Credentials\CredentialService;
use Illuminate\Support\Facades\Http;
use Throwable;

class StudentIdClassifier
{
    private const PROMPT = <<<'PROMPT'
Classify whether this image visually appears to be a student identification card or a digital student ID.
This is document-type classification only. Do not determine authenticity or whether it belongs to the submitter.
Return JSON only: {"isStudentId":true,"confidence":0.0,"reason":"short explanation"}.
Only return true when typical student-ID evidence is clearly visible, such as a school name/logo together with a person's name, photo, or student identifier. If uncertain, return false.
PROMPT;

    public function __construct(private readonly CredentialService $credentials) {}

    /** @return array{isStudentId: bool, confidence: float, provider: ?string, model: ?string, reason: string} */
    public function classify(string $storeId, string $bytes, string $mime): array
    {
        foreach (['gemini', 'openai'] as $provider) {
            try {
                $result = $provider === 'gemini'
                    ? $this->gemini($storeId, $bytes, $mime)
                    : $this->openai($storeId, $bytes, $mime);
                if ($result) {
                    return $result;
                }
            } catch (Throwable) {
                // Fail closed to manual review; never approve when an AI provider fails.
            }
        }

        return ['isStudentId' => false, 'confidence' => 0.0, 'provider' => null, 'model' => null, 'reason' => 'AI classification was unavailable; manual review is required.'];
    }

    /** @return array{isStudentId: bool, confidence: float, provider: string, model: string, reason: string}|null */
    private function gemini(string $storeId, string $bytes, string $mime): ?array
    {
        $key = $this->credentials->get('GEMINI_API_KEY', $storeId);
        if (! $key) {
            return null;
        }
        $model = $this->credentials->get('GEMINI_MODEL', $storeId, 'gemini-2.5-flash');
        $response = Http::timeout(40)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=".rawurlencode($key), [
            'contents' => [['role' => 'user', 'parts' => [
                ['text' => self::PROMPT],
                ['inlineData' => ['mimeType' => $mime, 'data' => base64_encode($bytes)]],
            ]]],
            'generationConfig' => ['temperature' => 0, 'responseMimeType' => 'application/json'],
        ])->throw()->json();
        $text = $this->textParts(data_get($response, 'candidates.0.content.parts', []));

        return $this->parse($text, 'gemini', (string) $model);
    }

    /** @return array{isStudentId: bool, confidence: float, provider: string, model: string, reason: string}|null */
    private function openai(string $storeId, string $bytes, string $mime): ?array
    {
        $key = $this->credentials->get('OPENAI_API_KEY', $storeId);
        if (! $key) {
            return null;
        }
        $model = $this->credentials->get('OPENAI_MODEL', $storeId, 'gpt-4.1-mini');
        $response = Http::withToken($key)->timeout(40)->post('https://api.openai.com/v1/responses', [
            'model' => $model,
            'temperature' => 0,
            'input' => [['role' => 'user', 'content' => [
                ['type' => 'input_text', 'text' => self::PROMPT],
                ['type' => 'input_image', 'image_url' => "data:{$mime};base64,".base64_encode($bytes)],
            ]]],
        ])->throw()->json();
        $text = (string) data_get($response, 'output_text', '');
        if ($text === '') {
            $content = [];
            $output = data_get($response, 'output', []);
            if (is_array($output)) {
                foreach ($output as $item) {
                    if (is_array($item) && is_array($item['content'] ?? null)) {
                        $content = array_merge($content, $item['content']);
                    }
                }
            }
            $text = $this->textParts($content);
        }

        return $this->parse($text, 'openai', (string) $model);
    }

    /** @return array{isStudentId: bool, confidence: float, provider: string, model: string, reason: string} */
    private function parse(string $text, string $provider, string $model): array
    {
        preg_match('/\{[\s\S]*\}/', $text, $matches);
        $json = json_decode($matches[0] ?? '', true, flags: JSON_THROW_ON_ERROR);
        $confidence = max(0, min(1, (float) ($json['confidence'] ?? 0)));

        return [
            'isStudentId' => ($json['isStudentId'] ?? false) === true,
            'confidence' => $confidence,
            'provider' => $provider,
            'model' => $model,
            'reason' => mb_substr((string) ($json['reason'] ?? ''), 0, 300),
        ];
    }

    private function textParts(mixed $parts): string
    {
        if (! is_array($parts)) {
            return '';
        }

        $text = '';
        foreach ($parts as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        return $text;
    }
}
