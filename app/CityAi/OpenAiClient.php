<?php

namespace App\CityAi;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OpenAiClient
{
    private const URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(private ?string $key, private ?string $model, private int $timeout) {}

    public function enabled(): bool
    {
        return filled($this->key) && filled($this->model);
    }

    public function json(string $system, string $user, string $schemaName, array $schema): array
    {
        $content = $this->chat($system, $user, [
            'type' => 'json_schema',
            'json_schema' => ['name' => $schemaName, 'strict' => true, 'schema' => $schema],
        ]);

        return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }

    public function text(string $system, string $user): string
    {
        return trim($this->chat($system, $user, null));
    }

    private function chat(string $system, string $user, ?array $format): string
    {
        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ];
        if ($format !== null) {
            $payload['response_format'] = $format;
        }

        $content = Http::withToken($this->key)->timeout($this->timeout)->acceptJson()
            ->post(self::URL, $payload)->throw()->json('choices.0.message.content');

        return is_string($content) && $content !== '' ? $content : throw new RuntimeException('Empty OpenAI response');
    }
}
