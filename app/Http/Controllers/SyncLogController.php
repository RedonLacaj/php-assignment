<?php

namespace App\Http\Controllers;

use App\Http\Resources\SyncLogResource;
use App\Models\SyncLog;
use Inertia\Inertia;

class SyncLogController extends Controller
{
    public function index()
    {
        $logs = SyncLog::with('spreadsheet:id,name')->latest('started_at')->paginate(30);

        return Inertia::render('logs/index', [
            'logs' => SyncLogResource::collection($logs),
        ]);
    }
}
