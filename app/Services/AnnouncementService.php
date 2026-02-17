<?php

namespace App\Services;

use App\Models\Announcement;
use App\Jobs\SendLineMessageJob;
use App\Models\Group;
use App\Models\AnnouncementLog;
use Illuminate\Support\Facades\DB;

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
        return $announcement;
    }

    public function send($id)
    {
        $announcement = Announcement::findOrFail($id);

        $groups = Group::where('status', 1)
            ->whereNotIn('group_id', function ($q) use ($id) {
                $q->select('group_id')
                    ->from('announcement_logs')
                    ->where('announcement_id', $id);
            })
            ->pluck('group_id');

        if ($groups->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No groups to send'
            ], 404);
        }

        DB::transaction(function () use ($groups, $announcement, $id) {
            foreach ($groups as $gid) {

                SendLineMessageJob::dispatch(
                    $gid,
                    $announcement->id,
                    $announcement->message,
                    $announcement->type,
                    $announcement->image
                );

                AnnouncementLog::firstOrCreate([
                    'announcement_id' => $id,
                    'group_id' => $gid
                ]);
            }

            $announcement->update(['status' => 'sending']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Queue sending started'
        ]);
    }

}
