<?php

namespace App\Services\AI;

use Generator;
use Illuminate\Support\Facades\Log;

/**
 * Gemini AI client — drop-in replacement for AnthropicClient.
 *
 * Extends AnthropicClient so it satisfies all existing type hints
 * (QaAssistant, MedicalExplainer, etc.) without code changes.
 * All public Anthropic methods are overridden to use the Gemini REST API.
 *
 * Gemini limitations vs Anthropic:
 *   - No extended thinking blocks (streamWithThinking yields text-only chunks)
 *   - No prompt caching (withCacheControl text is extracted and used as plain system prompt)
 *   - model option in $options is ignored (always uses GEMINI_MODEL)
 */
class GeminiClient extends AnthropicClient
{
    private const GEMINI_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    private const GEMINI_MAX_RETRIES = 2;

    private const GEMINI_BACKOFF_SECONDS = [1, 2];

    private string $geminiApiKey;

    private string $geminiModel;

    public function __construct()
    {
        // Do NOT call parent::__construct() — we use Gemini, not the Anthropic SDK.
        $this->geminiApiKey = config('gemini.api_key', '');
        $this->geminiModel = config('gemini.default_model', 'gemini-2.0-flash');
    }

    /**
     * Send a non-streaming chat request and return the full response text.
     *
     * @param  string|array  $systemPrompt  String or TextBlockParam[] (cache control stripped)
     */
    public function chat(string|array $systemPrompt, array $messages, array $options = []): string
    {
        $maxTokens = $options['max_tokens'] ?? 4096;
        $body = $this->buildGeminiBody($systemPrompt, $messages, $maxTokens);
        $response = $this->geminiRequest('generateContent', $body);

        return $this->extractTextFromResponse($response);
    }

    /**
     * Chat with thinking. Gemini Flash has no reasoning blocks — returns empty thinking.
     *
     * @param  string|array  $systemPrompt
     * @return array{text: string, thinking: string, usage: array}
     */
    public function chatWithThinking(string|array $systemPrompt, array $messages, array $options = []): array
    {
        $maxTokens = $options['max_tokens'] ?? 16000;
        $body = $this->buildGeminiBody($systemPrompt, $messages, $maxTokens);
        $response = $this->geminiRequest('generateContent', $body);

        return [
            'text' => $this->extractTextFromResponse($response),
            'thinking' => '',
            'usage' => [
                'input_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
                'output_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
            ],
        ];
    }

    /**
     * Stream a chat request, yielding text chunks progressively.
     *
     * @param  string|array  $systemPrompt
     * @return Generator<string> Yields response text chunks
     */
    public function stream(string|array $systemPrompt, array $messages, array $options = []): Generator
    {
        $maxTokens = $options['max_tokens'] ?? 4096;
        $body = $this->buildGeminiBody($systemPrompt, $messages, $maxTokens);

        yield from $this->geminiStream($body, withThinking: false);
    }

    /**
     * Stream with thinking. Gemini has no thinking blocks — yields text-typed chunks.
     *
     * @param  string|array  $systemPrompt
     * @return Generator<array{type: string, content: string}>
     */
    public function streamWithThinking(string|array $systemPrompt, array $messages, array $options = []): Generator
    {
        $maxTokens = $options['max_tokens'] ?? 16000;
        $body = $this->buildGeminiBody($systemPrompt, $messages, $maxTokens);

        yield from $this->geminiStream($body, withThinking: true);
    }

    /**
     * Agentic tool-use loop. Handles function calling until end_turn.
     *
     * @param  string|array  $systemPrompt
     * @param  callable(string, array): array  $toolExecutor
     * @param  (callable(string, array): void)|null  $onToolUse
     * @return array{text: string, thinking: string, tools_used: array}
     */
    public function chatWithTools(
        string|array $systemPrompt,
        array $messages,
        array $tools,
        callable $toolExecutor,
        ?callable $onToolUse = null,
        array $options = [],
    ): array {
        $maxTokens = $options['max_tokens'] ?? 16000;
        $maxIterations = 5;
        $toolsUsed = [];

        $geminiMessages = $this->convertMessages($messages);
        $geminiTools = $this->convertAnthropicToolsToGemini($tools);

        for ($i = 0; $i < $maxIterations; $i++) {
            $body = [
                'generationConfig' => ['maxOutputTokens' => $maxTokens],
                'contents' => $geminiMessages,
            ];

            $systemText = $this->extractSystemText($systemPrompt);
            if ($systemText !== '') {
                $body['systemInstruction'] = ['parts' => [['text' => $systemText]]];
            }

            if (! empty($geminiTools)) {
                $body['tools'] = $geminiTools;
            }

            $response = $this->geminiRequest('generateContent', $body);
            $candidate = $response['candidates'][0] ?? [];
            $parts = $candidate['content']['parts'] ?? [];

            // Check for function calls in this response
            $functionCallParts = array_values(array_filter($parts, fn ($p) => isset($p['functionCall'])));

            if (! empty($functionCallParts)) {
                // Add model's response (with function calls) to history
                $geminiMessages[] = ['role' => 'model', 'parts' => $parts];

                $functionResponses = [];
                foreach ($functionCallParts as $part) {
                    $fc = $part['functionCall'];
                    $name = $fc['name'];
                    $input = $fc['args'] ?? [];

                    if ($onToolUse) {
                        $onToolUse($name, $input);
                    }

                    $result = $toolExecutor($name, $input);
                    $toolsUsed[] = ['name' => $name, 'input' => $input];

                    $functionResponses[] = [
                        'functionResponse' => [
                            'name' => $name,
                            'response' => ['output' => json_encode($result)],
                        ],
                    ];
                }

                $geminiMessages[] = ['role' => 'user', 'parts' => $functionResponses];

                continue;
            }

            // No function calls — extract final text and return
            $text = '';
            foreach ($parts as $part) {
                $text .= $part['text'] ?? '';
            }

            Log::channel('ai')->info('Gemini chatWithTools request', [
                'model' => $this->geminiModel,
                'tools_used' => count($toolsUsed),
                'tool_names' => array_column($toolsUsed, 'name'),
            ]);

            return ['text' => $text, 'thinking' => '', 'tools_used' => $toolsUsed];
        }

        throw new \RuntimeException('Gemini tool use loop exceeded max iterations ('.$maxIterations.')');
    }

    // ─── Private helpers ────────────────────────────────────────────────────────

    /**
     * Build the Gemini API request body from Anthropic-style inputs.
     */
    private function buildGeminiBody(string|array $systemPrompt, array $messages, int $maxTokens): array
    {
        $body = [
            'generationConfig' => ['maxOutputTokens' => $maxTokens],
            'contents' => $this->convertMessages($messages),
        ];

        $systemText = $this->extractSystemText($systemPrompt);
        if ($systemText !== '') {
            $body['systemInstruction'] = ['parts' => [['text' => $systemText]]];
        }

        return $body;
    }

    /**
     * Convert Anthropic messages to Gemini contents format.
     *
     * - role "assistant" → "model"
     * - role "user" → "user"
     * - string content → parts[{text}]
     * - array content (structured blocks) → mapped parts
     */
    private function convertMessages(array $messages): array
    {
        $converted = [];

        foreach ($messages as $msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            $content = $msg['content'] ?? '';

            if (is_string($content)) {
                if ($content === '') {
                    continue;
                }

                $converted[] = ['role' => $role, 'parts' => [['text' => $content]]];

                continue;
            }

            // Array content (Anthropic structured blocks)
            $parts = [];
            foreach ($content as $block) {
                $type = $block['type'] ?? '';

                if ($type === 'text') {
                    $parts[] = ['text' => $block['text'] ?? ''];
                } elseif ($type === 'tool_result') {
                    $parts[] = [
                        'functionResponse' => [
                            'name' => $block['tool_use_id'] ?? 'unknown',
                            'response' => ['output' => $block['content'] ?? ''],
                        ],
                    ];
                }
                // tool_use blocks are handled in chatWithTools agentic loop — skip here
            }

            if (! empty($parts)) {
                $converted[] = ['role' => $role, 'parts' => $parts];
            }
        }

        return $converted;
    }

    /**
     * Extract plain text from a system prompt (string or TextBlockParam[]).
     *
     * Handles Anthropic's cache-control TextBlockParam objects by calling
     * jsonSerialize() and extracting the text field — cache metadata is discarded.
     */
    private function extractSystemText(string|array $systemPrompt): string
    {
        if (is_string($systemPrompt)) {
            return $systemPrompt;
        }

        $text = '';
        foreach ($systemPrompt as $block) {
            if ($block instanceof \JsonSerializable) {
                $serialized = $block->jsonSerialize();
                $text .= $serialized['text'] ?? '';
            } elseif (is_array($block)) {
                $text .= $block['text'] ?? '';
            }
        }

        return $text;
    }

    /**
     * Convert Anthropic tool definitions to Gemini functionDeclarations format.
     *
     * Anthropic: {name, description, input_schema: {type, properties, required}}
     * Gemini:    {functionDeclarations: [{name, description, parameters: {...}}]}
     */
    private function convertAnthropicToolsToGemini(array $tools): array
    {
        if (empty($tools)) {
            return [];
        }

        $declarations = [];
        foreach ($tools as $tool) {
            $decl = [
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
            ];

            if (isset($tool['input_schema'])) {
                $schema = $tool['input_schema'];
                // Gemini expects uppercase type strings ("OBJECT" not "object")
                if (isset($schema['type'])) {
                    $schema['type'] = strtoupper($schema['type']);
                }
                $decl['parameters'] = $schema;
            }

            $declarations[] = $decl;
        }

        return [['functionDeclarations' => $declarations]];
    }

    /**
     * Progressive streaming via raw curl + curl_multi (mirrors AnthropicClient approach).
     *
     * Gemini streaming endpoint: streamGenerateContent?alt=sse
     * Each SSE data event is a JSON object with candidates[0].content.parts[].text
     *
     * @return Generator Yields string (text-only) or array{type, content} (withThinking mode)
     */
    private function geminiStream(array $body, bool $withThinking): Generator
    {
        $url = self::GEMINI_BASE_URL.'/'.$this->geminiModel.':streamGenerateContent?key='.$this->geminiApiKey.'&alt=sse';
        $buffer = '';
        $httpError = null;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$buffer) {
                $buffer .= $data;

                return strlen($data);
            },
        ]);

        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $ch);

        $running = null;

        do {
            curl_multi_exec($mh, $running);

            // Normalize CRLF → LF (Gemini uses \r\n\r\n, not \n\n)
            $buffer = str_replace("\r\n", "\n", $buffer);

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $chunk = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                foreach (explode("\n", $chunk) as $line) {
                    if (! str_starts_with($line, 'data: ')) {
                        continue;
                    }

                    $payload = substr($line, 6);
                    $decoded = json_decode($payload, true);

                    if (! $decoded) {
                        continue;
                    }

                    // API-level errors embedded in SSE stream
                    if (isset($decoded['error'])) {
                        $httpError = $decoded['error']['message'] ?? 'Unknown Gemini error';
                        Log::error('Gemini streaming API error', ['error' => $decoded['error']]);

                        break 3;
                    }

                    // Extract text from candidates
                    $text = '';
                    foreach ($decoded['candidates'][0]['content']['parts'] ?? [] as $part) {
                        $text .= $part['text'] ?? '';
                    }

                    if ($text === '') {
                        continue;
                    }

                    if ($withThinking) {
                        yield ['type' => 'text', 'content' => $text];
                    } else {
                        yield $text;
                    }
                }
            }

            if ($running) {
                curl_multi_select($mh, 0.05);
            }
        } while ($running);

        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_multi_remove_handle($mh, $ch);
        curl_multi_close($mh);
        curl_close($ch);

        if ($curlError) {
            Log::error('Gemini stream curl error', ['error' => $curlError]);

            throw new \RuntimeException("Gemini API connection error: {$curlError}");
        }

        if ($httpError) {
            throw new \RuntimeException("Gemini API error: {$httpError}");
        }

        if ($httpCode >= 400) {
            Log::error('Gemini stream HTTP error', ['code' => $httpCode, 'model' => $this->geminiModel]);

            throw new \RuntimeException("Gemini API returned HTTP {$httpCode}");
        }
    }

    /**
     * Non-streaming Gemini API request with exponential backoff retry.
     *
     * @return array<string, mixed>
     */
    private function geminiRequest(string $action, array $body): array
    {
        $url = self::GEMINI_BASE_URL.'/'.$this->geminiModel.':'.$action.'?key='.$this->geminiApiKey;
        $maxAttempts = self::GEMINI_MAX_RETRIES + 1;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($body),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                if ($attempt < $maxAttempts) {
                    $delay = self::GEMINI_BACKOFF_SECONDS[$attempt - 1] ?? 2;
                    Log::warning("Gemini curl error, attempt {$attempt}/{$maxAttempts}, retrying in {$delay}s", ['error' => $curlError]);
                    $this->retrySleep($delay);

                    continue;
                }

                throw new \RuntimeException("Gemini API connection error: {$curlError}");
            }

            if ($httpCode === 429 || $httpCode >= 500) {
                if ($attempt < $maxAttempts) {
                    $delay = self::GEMINI_BACKOFF_SECONDS[$attempt - 1] ?? 2;
                    Log::warning("Gemini HTTP {$httpCode}, attempt {$attempt}/{$maxAttempts}, retrying in {$delay}s");
                    $this->retrySleep($delay);

                    continue;
                }
            }

            if ($httpCode >= 400) {
                Log::error('Gemini API error', ['code' => $httpCode, 'body' => substr((string) $response, 0, 500)]);

                throw new \RuntimeException("Gemini API returned HTTP {$httpCode}: ".substr((string) $response, 0, 200));
            }

            $decoded = json_decode((string) $response, true);

            if (! is_array($decoded)) {
                throw new \RuntimeException('Gemini API returned invalid JSON');
            }

            $this->logGeminiUsage($decoded);

            return $decoded;
        }

        throw new \RuntimeException('Gemini API: all retry attempts exhausted');
    }

    /**
     * Extract text from a non-streaming Gemini generateContent response.
     */
    private function extractTextFromResponse(array $response): string
    {
        $text = '';
        foreach ($response['candidates'][0]['content']['parts'] ?? [] as $part) {
            $text .= $part['text'] ?? '';
        }

        return $text;
    }

    /**
     * Log Gemini token usage to the ai channel.
     */
    private function logGeminiUsage(array $response): void
    {
        $usage = $response['usageMetadata'] ?? [];
        Log::channel('ai')->info('Gemini request', [
            'model' => $this->geminiModel,
            'input_tokens' => $usage['promptTokenCount'] ?? null,
            'output_tokens' => $usage['candidatesTokenCount'] ?? null,
        ]);
    }
}
