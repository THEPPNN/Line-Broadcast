<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\LineMessage;
use Illuminate\Support\Facades\Storage;

class UploadLineMediaToS3 implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 60;

    public function __construct(
        public string $messageId,
        public string $type,
        public array  $source,
        public string $group_id,
    ) {}

    public function handle(): void
    {
        $userId  = $this->source['userId'] ?? null;
        $groupId = $this->source['groupId'] ?? null;

        // ดึง displayName และ download content พร้อมกัน
        $displayName = $this->fetchDisplayName($userId, $groupId);
        $r2Path      = $this->downloadAndUpload();

        LineMessage::where('message_id', $this->messageId)->update(array_filter([
            'file_url'  => $r2Path,
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

    private function downloadAndUpload(): ?string
    {
        $ext = match ($this->type) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'm4a',
            default => 'bin',
        };

        $r2Path = "line_media/{$this->group_id}/{$this->messageId}.{$ext}";

        $response = Http::withToken(config('services.line.token'))
            ->timeout(30)
            ->get("https://api-data.line.me/v2/bot/message/{$this->messageId}/content");

        // content หายแล้ว (unsend/expired) ไม่ต้อง retry
        if ($response->status() === 404) {
            Log::warning('LINE content expired', ['messageId' => $this->messageId]);
            LineMessage::where('message_id', $this->messageId)
                ->update(['file_url' => 'CONTENT_EXPIRED']);
            return null;
        }

        if (!$response->successful()) {
            throw new \RuntimeException("LINE API {$response->status()}");
        }

        // ✅ stream ตรงไป R2 เลย ไม่แตะ disk
        Storage::disk('s3')->put(
            $r2Path,
            $response->body(),
            ['visibility' => 'public']
        );

        return $r2Path;
    }

    public function backoff(): array
    {
        return [3, 5, 10, 20, 30];
    }

    public function failed(\Throwable $e): void
    {
        Log::critical('ProcessLineMedia failed', [
            'messageId' => $this->messageId,
            'error'     => $e->getMessage(),
        ]);

        LineMessage::where('message_id', $this->messageId)
            ->update(['file_url' => 'UPLOAD_FAILED']);
    }
}