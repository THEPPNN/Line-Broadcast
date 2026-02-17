<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Group;

// Route::post('/webhook/line', function () {
//     return response('OK', 200);
// });

Route::post('/webhook/line', function (Request $request) {

    $data = $request->all();

    file_put_contents(
        storage_path('logs/line.json'),
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    if (!isset($data['events'][0])) {
        return response('NO EVENTS', 200);
    }

    $event = $data['events'][0];

    if (($event['source']['type'] ?? null) !== 'group') {
        return response('NOT GROUP', 200);
    }

    $groupId = $event['source']['groupId'] ?? null;
    $name = $event['message']['text'] ?? 'Unknown';

    if (!$groupId) {
        return response('NO GROUP ID', 200);
    }

    $group = Group::firstOrCreate(
        ['group_id' => $groupId],
        [
            'name' => $name,
            'type' => 0,
            'status' => 1,
        ]
    );

    return response('OK', 200);
});
