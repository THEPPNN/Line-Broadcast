<?php

namespace App\Services;

use App\Models\Announcement;
use App\Jobs\SendAnnouncementJob;
use Illuminate\Support\Facades\Storage;

class AnnouncementService
{
    public function create($data)
    {
        $imagePath = null;

        if (isset($data['image'])) {
            $imagePath = $data['image']
                ->store('announcements', 'public');
        }

        $announcement = Announcement::create([
            'title' => $data['title'] ?? null,
            'message' => $data['message'] ?? null,
            'image' => $imagePath,
            'type' => $data['types'],
            'send_at' => $data['send_at'],
            'status' => 'pending'
        ]);

        if ($announcement) {
            return json_encode([
                'success' => true,
                'message' => 'Announcement created successfully',
                'data' => $announcement
            ]);
        } else {
            return json_encode([
                'success' => false,
                'message' => 'Announcement creation failed',
                'data' => $announcement
            ]);
        }
    }

    public function getAll()
    {
        return Announcement::orderBy('send_at', 'desc')->where('status', '!=', 'cancelled')->get();
    }
    public function cancel($id)
    {
        $announcement = Announcement::find($id);
        $announcement->update([
            'status' => 'cancelled'
        ]);
        return json_encode([
            'success' => true,
            'message' => 'Announcement cancelled successfully',
        ]);
    }
    public function send($id)
    {
        // sent text  to line than update status to sent
        $announcement = Announcement::find($id);
        // $response = Http::post('https://api.line.me/v2/bot/message/push', [
        //     'to' => 'U4af49806ea6ad87080349c6998712584',
        //     'messages' => [
        //         'type' => 'text',
        //         'text' => $announcement->message
        //     ]
        // ]);

        $announcement->update([
            'status' => 'sent'
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Announcement sent successfully',
        ]);
    }
}
