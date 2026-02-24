<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Group;

class ProcessLineGroup implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 15;

    public function __construct(public array $event) {}

    public function handle(): void
    {
        try {
            $source = $this->event['source'] ?? null;

            if (!$source || ($source['type'] ?? null) !== 'group') {
                return;
            }

            $groupId = $source['groupId'] ?? null;

            if (!$groupId) {
                return;
            }

            $group = Group::where('group_id', $groupId)->first();
            if ($group && $group->name && $group->name !== 'LINE GROUP') {
                return;
            }

            // 🔥 ดึงชื่อกลุ่มจาก LINE
            $response = Http::withToken(config('services.line.token'))
                ->timeout(10)
                ->get("https://api.line.me/v2/bot/group/{$groupId}/summary");

            $groupName = 'LINE GROUP';

            if ($response->successful()) {
                $groupName = $response->json()['groupName'] ?? 'LINE GROUP';
            } else {
                Log::warning('Cannot fetch group summary', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }

            // ✅ บันทึกหรืออัปเดต
            Group::updateOrCreate(
                ['group_id' => $groupId],
                [
                    'name'   => $groupName,
                    'type'   => 0,
                    'status' => 1
                ]
            );
        } catch (\Throwable $e) {
            Log::error('ProcessLineGroup Error: ' . $e->getMessage(), [
                'event' => $this->event
            ]);
            throw $e; // ให้ retry ได้
        }
    }
}
