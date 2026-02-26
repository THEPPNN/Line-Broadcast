<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\LineMessage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class UploadLineMediaToS3 implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 60;

    public function __construct(
        public string $messageId,
        public string $type,
        public array  $source,
        public ?string $group_id = null,
    ) {}

    public function handle(): void
    {
        $userId  = $this->source['userId'] ?? null;
        $groupId = $this->source['groupId'] ?? null;

        $r2Path      = $this->downloadAndUpload();

        LineMessage::where('message_id', $this->messageId)->update(array_filter([
            'file_url'  => $r2Path,
        ], fn($v) => $v !== null));
    }

    private function downloadAndUpload(): ?string
    {
        $ext = match ($this->type) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'm4a',
            default => 'bin',
        };

        $groupId = $this->source['groupId']
            ?? $this->source['roomId']
            ?? $this->source['userId']
            ?? 'unknown';
        $r2Path = "line_media/{$groupId}/{$this->messageId}.{$ext}";

        $response = Http::withToken(config('services.line.token'))
            ->timeout(30)
            ->withOptions(['stream' => true])
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

        Storage::disk('s3')->writeStream(
            $r2Path,
            $response->toPsrResponse()->getBody()->detach()
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
