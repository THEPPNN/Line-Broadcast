<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\LineMessage;
use App\Jobs\UploadLineMediaToS3;

class DownloadLineMediaToTemp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public string $messageId,
        public string $type,
        public ?string $groupId,
    ) {}

    public function handle(): void
    {
        $ext = match ($this->type) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'm4a',
            default => 'bin',
        };

        $tempPath = "temp/{$this->messageId}.{$ext}";

        $response = Http::withToken(config('services.line.token'))
            ->timeout(20)
            ->get("https://api-data.line.me/v2/bot/message/{$this->messageId}/content");

        if ($response->status() === 404) {
            LineMessage::where('message_id', $this->messageId)
                ->update(['file_url' => 'CONTENT_EXPIRED']);
            return;
        }

        if (!$response->successful()) {
            throw new \RuntimeException("LINE API {$response->status()}");
        }

        // ✅ เขียนลง temp เร็วมาก (local disk)
        Storage::disk('local')->put($tempPath, $response->body());

        LineMessage::where('message_id', $this->messageId)
            ->update(['file_url' => $tempPath]);

        // 🔥 ส่งต่อ job ไป upload S3
        UploadLineMediaToS3::dispatch($this->messageId, $tempPath, $this->groupId)
            ->onQueue('line_media');
    }
}