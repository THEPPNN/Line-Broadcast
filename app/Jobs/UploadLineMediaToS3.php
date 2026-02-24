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

    public function __construct(
        public string $localPath,
        public string $messageId,
        public string $type
    ) {}

    public function handle()
    {
        if (!Storage::disk('local')->exists($this->localPath)) {
            Log::error('Local file missing', ['path'=>$this->localPath]);
            return;
        }

        $content = Storage::disk('local')->get($this->localPath);

        $s3Path = "line_media/" . basename($this->localPath);

        Storage::disk('s3')->put(
            $s3Path,
            $content,
            ['visibility'=>'public']
        );

        // update DB
        LineMessage::where('message_id', $this->messageId)
            ->update([
                'file_url' => $s3Path
            ]);

        // delete local file
        Storage::disk('local')->delete($this->localPath);

        Log::info('Upload to S3 completed', [
            'messageId' => $this->messageId
        ]);
    }
}