<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use App\Models\LineMessage;
class FetchLineDisplayName implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 15;

    public function __construct(public string $messageId, public array $event) {
        $this->messageId = $messageId;
        $this->event = $event;
    }

    public function handle(): void
    {
        $userId = $this->event['source']['userId'] ?? null;
        $groupId = $this->event['source']['groupId'] ?? null;
        $displayName = $this->fetchDisplayName($userId, $groupId);
        LineMessage::where('message_id', $this->messageId)->update(array_filter([
            'user_name' => $displayName,
        ], fn($v) => $v !== null));
    }
    private function fetchDisplayName(?string $userId, ?string $groupId): ?string
    {
        if (!$userId || !$groupId) return null;

        try {
            $res = Http::withToken(config('services.line.token'))
                ->timeout(10)
                ->get("https://api.line.me/v2/bot/group/{$groupId}/member/{$userId}");

            return $res->successful() ? ($res->json()['displayName'] ?? null) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
