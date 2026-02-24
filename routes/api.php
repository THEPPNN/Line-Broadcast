<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\LineEvent;
use App\Jobs\ProcessLineUnsend;
use App\Jobs\ProcessLineGroup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Jobs\UploadLineMediaToS3;
use Illuminate\Support\Facades\Log;
use App\Models\LineMessage;

Route::post('/webhook/line', function (Request $request) {

    $body = $request->all();

    foreach ($body['events'] ?? [] as $event) {

        LineEvent::create([
            'event_id' => $event['webhookEventId'] ?? null,
            'type' => $event['type'] ?? null,
            'source_type' => $event['source']['type'] ?? null,
            'user_id' => $event['source']['userId'] ?? null,
            'group_id' => $event['source']['groupId'] ?? null,
            'room_id' => $event['source']['roomId'] ?? null,
            'timestamp' => $event['timestamp'] ?? null,
            'raw' => json_encode($event)
        ]);

        if (($event['type'] ?? null) === 'message') {

            $msg = $event['message'];
            $messageId = $msg['id'];
            $type = $msg['type'];

            $localPath = null;

            /*
            |--------------------------------------------------------------------------
            | DOWNLOAD MEDIA IMMEDIATELY
            |--------------------------------------------------------------------------
            */

            if (in_array($type, ['image', 'video', 'audio', 'file'])) {

                try {

                    $response = Http::withToken(config('services.line.token'))
                        ->timeout(20)
                        ->get("https://api-data.line.me/v2/bot/message/{$messageId}/content");

                    if ($response->successful()) {

                        $ext = match ($type) {
                            'image' => 'jpg',
                            'video' => 'mp4',
                            'audio' => 'm4a',
                            default => 'bin'
                        };

                        $localPath = "line_temp/{$messageId}.{$ext}";

                        Storage::disk('local')->put(
                            $localPath,
                            $response->body()
                        );

                        // dispatch upload job
                        UploadLineMediaToS3::dispatch($localPath, $messageId, $type)
                            ->onQueue('line');
                    }
                } catch (\Throwable $e) {
                    Log::error('MEDIA DOWNLOAD FAILED', [
                        'messageId' => $messageId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE DATABASE IMMEDIATELY
            |--------------------------------------------------------------------------
            */

            LineMessage::create([
                'message_id' => $messageId,
                'type'       => $type,
                'user_id'    => $event['source']['userId'] ?? null,
                'group_id'   => $event['source']['groupId'] ?? null,
                'room_id'    => $event['source']['roomId'] ?? null,
                'text'       => $msg['text'] ?? null,
                'file_url'   => null, // จะ update ทีหลัง
                'file_type'  => $type,
                'unsent_at'  => null,
                'user_name'  => null,
            ]);

            if (($event['source']['type'] ?? null) === 'group') {
                ProcessLineGroup::dispatch($event)->onQueue('line');
            }
        }

        if (($event['type'] ?? null) === 'unsend') {
            ProcessLineUnsend::dispatch($event)
                ->delay(now()->addSeconds(5))
                ->onQueue('line');
        }
    }

    return response('OK', 200);
});

// Route::post('/webhook/line', function (Request $request) {
//     $body = $request->all();

//     foreach ($body['events'] ?? [] as $event) {
//         LineEvent::create([
//             'event_id' => $event['webhookEventId'] ?? null,
//             'type' => $event['type'] ?? null,
//             'source_type' => $event['source']['type'] ?? null,
//             'user_id' => $event['source']['userId'] ?? null,
//             'group_id' => $event['source']['groupId'] ?? null,
//             'room_id' => $event['source']['roomId'] ?? null,
//             'timestamp' => $event['timestamp'] ?? null,
//             'raw' => json_encode($event)
//         ]);

//         if (($event['type'] ?? null) === 'message') {
//             ProcessLineMessage::dispatch($event)->onQueue('line');
        
//             if (($event['source']['type'] ?? null) === 'group') {
//                 ProcessLineGroup::dispatch($event)->onQueue('line');
//             }
//         }
        
//         if (($event['type'] ?? null) === 'unsend') {
//             ProcessLineUnsend::dispatch($event)
//                 ->delay(now()->addSeconds(5))
//                 ->onQueue('line');
//         }
        
//     }
//     return response('OK', 200); // response ทันที
// });

// Route::post('/webhook/line', function (Request $request) {

//     $body = $request->all();
//     file_put_contents(
//         storage_path('logs/line.json'),
//         json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
//     );

//     foreach ($body['events'] ?? [] as $event) {

//         // ----------------------
//         // save raw event
//         // ----------------------

//         try {
//             LineEvent::create([
//                 'event_id' => $event['webhookEventId'] ?? null,
//                 'type' => $event['type'] ?? null,
//                 'source_type' => $event['source']['type'] ?? null,
//                 'user_id' => $event['source']['userId'] ?? null,
//                 'group_id' => $event['source']['groupId'] ?? null,
//                 'room_id' => $event['source']['roomId'] ?? null,
//                 'timestamp' => $event['timestamp'] ?? null,
//                 'raw' => json_encode($event)
//             ]);
//         } catch (\Throwable $e) {
//             Log::error($e->getMessage());
//         }

//         // ----------------------
//         // message event
//         // ----------------------
//         if (($event['type'] ?? null) === 'message') {
//             $msg = $event['message'];
//             $messageId = $msg['id'];
//             $type = $msg['type'];

//             $filePath = null;

//             // ---------- download media ----------
//             if (in_array($type, ['image', 'video', 'audio', 'file'])) {

//                 $res = Http::withToken(config('services.line.token'))
//                     ->get("https://api-data.line.me/v2/bot/message/$messageId/content");

//                 if (!$res->successful()) {
//                     Log::error('LINE download failed', ['status' => $res->status()]);
//                     return;
//                 }

//                 $ext = match ($type) {
//                     'image' => 'jpg',
//                     'video' => 'mp4',
//                     'audio' => 'm4a',
//                     default => 'bin'
//                 };

//                 $filePath = "line_media/{$messageId}.{$ext}";

//                 $uploaded = Storage::disk('s3')->put(
//                     $filePath,
//                     $res->body(),
//                     ['visibility' => 'public']
//                 );

//                 if (!$uploaded) {
//                     Log::error('R2 upload failed');
//                     return;
//                 }

//                 Log::info('UPLOAD SUCCESS', ['path' => $filePath]);
//             }

//             // ---------- save message ----------

//             $userId = $event['source']['userId'] ?? null;
//             $groupId = $event['source']['groupId'] ?? null;

//             $displayName = null;

//             if ($userId && $groupId) {
//                 $res = Http::withToken(config('services.line.token'))
//                     ->get("https://api.line.me/v2/bot/group/$groupId/member/$userId");

//                 if ($res->successful()) {
//                     $displayName = $res->json()['displayName'] ?? null;
//                 }
//             }
//             try {
//                 LineMessage::create([
//                     'message_id' => $messageId,
//                     'type' => $type,
//                     'user_id' => $event['source']['userId'] ?? null,
//                     'group_id' => $event['source']['groupId'] ?? null,
//                     'room_id' => $event['source']['roomId'] ?? null,
//                     'text' => $msg['text'] ?? null,
//                     'file_url' => $filePath,
//                     'file_type' => $type,
//                     'unsent_at' => null,
//                     'user_name' => $displayName,
//                 ]);
//             } catch (\Throwable $e) {
//                 Log::error($e->getMessage());
//             }
//         }

//         // ----------------------
//         // unsend event
//         // ----------------------
//         if (($event['type'] ?? null) === 'unsend') {
//             $mid = $event['unsend']['messageId'];
//             $message = LineMessage::where('message_id', $mid)->first();
//             $userName = $message->user_name ?? 'Unknown';
//             try {
//                 LineUnsend::create([
//                     'message_id' => $mid,
//                     'user_id' => $event['source']['userId'] ?? null,
//                     'group_id' => $event['source']['groupId'] ?? null,
//                     'unsent_at' => now(),
//                 ]);
//             } catch (\Throwable $e) {
//                 Log::error($e->getMessage());
//             }

//             LineMessage::where('message_id', $mid)
//                 ->update([
//                     'is_unsent' => true,
//                     'unsent_at' => now()
//                 ]);

//             // --------------------begin sent message to group ------------

//             $messageText =
//                 "📢 ข้อความถูกยกเลิก\n\n" .
//                 "ผู้ส่ง: @{$userName}\n" .
//                 "เวลา: {$message->created_at}\n" .
//                 "ประเภท: " . ($message->type == 'image' ? 'รูปภาพ' : 'ข้อความ') . "\n\n" .
//                 ($message->text ? 'ข้อความ: ' . "\n" . $message->text : '');
//             $messages = [[
//                 'type' => 'text',
//                 'text' => $messageText
//             ]];
//             Log::info('messageText : ' . json_encode($message));
//             $response = Http::withToken(config('services.line.token'))
//                 ->post('https://api.line.me/v2/bot/message/push', [
//                     'to' => $message->group_id,
//                     'messages' => $messages
//                 ]);

//             if ($message->type == "image") { // ถ้าเป็นรูปภาพ ส่งรูปภาพไปยังกลุ่ม
//                 $url = config('filesystems.disks.s3.url') . '/' . $message->file_url;
//                 $messages = [[
//                     'type' => 'image',
//                     'originalContentUrl' => $url,
//                     'previewImageUrl' => $url
//                 ]];

//                 $response = Http::withToken(config('services.line.token'))
//                     ->post('https://api.line.me/v2/bot/message/push', [
//                         'to' => $message->group_id,
//                         'messages' => $messages
//                     ]);
//             }
//             // --------------------end sent message to group ------------
//         }

//         if (($event['source']['type'] ?? null) === 'group') {
//             try {
//                 Group::firstOrCreate(
//                     ['group_id' => $event['source']['groupId']],
//                     [
//                         'name' => 'LINE GROUP',
//                         'type' => 0,
//                         'status' => 1
//                     ]
//                 );
//             } catch (\Throwable $e) {
//                 Log::error($e->getMessage());
//             }
//         }
//     }

//     return response('OK', 200);
// });
