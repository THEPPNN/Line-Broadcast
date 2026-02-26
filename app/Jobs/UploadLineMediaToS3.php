<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\LineMessage;

class UploadLineMediaToS3 implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public string $messageId,
        public ?string $groupId,
        public string $tempPath,
        public string $extension,
    ) {}

    public function handle(): void
    {
        if (!Storage::disk('local')->exists($this->tempPath)) {
            Log::error("Temp file not found: {$this->tempPath}");
            return;
        }

        $finalPath = "line_media/{$this->groupId}/{$this->messageId}.{$this->extension}";

        $stream = Storage::disk('local')->readStream($this->tempPath);

        Storage::disk('s3')->put(
            $finalPath,
            $stream,
            ['visibility' => 'public']
        );

        if (is_resource($stream)) {
            fclose($stream);
        }

        Storage::disk('local')->delete($this->tempPath);

        LineMessage::where('message_id', $this->messageId)
            ->update(['file_url' => $finalPath]);
    }
}