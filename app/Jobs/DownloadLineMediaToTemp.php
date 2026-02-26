<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\LineMessage;
use App\Jobs\UploadLineMediaToS3;

class DownloadLineMediaToTemp implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // ห้าม retry เด็ดขาด เสียเวลา
    public int $timeout = 20;

    public function __construct(
        public string $messageId,
        public string $type,
        public ?string $groupId,
    ) {}

    public function handle(): void
    {
        $response = Http::withToken(config('services.line.token'))
            ->timeout(15)
            ->retry(0, 0)
            ->get("https://api-data.line.me/v2/bot/message/{$this->messageId}/content");

        if ($response->status() === 404) {
            LineMessage::where('message_id', $this->messageId)
                ->update(['file_url' => 'CONTENT_EXPIRED']);
            return;
        }

        if (!$response->successful()) {
            Log::warning("LINE download failed {$response->status()}");
            return;
        }

        $ext = match ($this->type) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'm4a',
            default => 'bin',
        };

        $tempPath = "line_temp/{$this->messageId}.{$ext}";

        $stream = $response->toPsrResponse()->getBody();
        Storage::disk('local')->put($tempPath, $stream);
        UploadLineMediaToS3::dispatch(
            $this->messageId,
            $this->groupId,
            $tempPath,
            $ext
        )->onQueue('line_media');
    }
}