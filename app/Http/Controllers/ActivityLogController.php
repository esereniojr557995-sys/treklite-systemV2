<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/** Owner only: who did what (voids, product changes, staff accounts, backups). */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        // Only used to pre-select the Action filter when the page is opened from a link.
        $action = is_string($request->query('action')) && array_key_exists($request->query('action'), ActivityLog::ACTION_LABELS)
            ? $request->query('action')
            : '';
        $q = '';

        // The latest 2,000 lines are loaded ONCE. Search, the Action filter and the paging all work in the browser,
        // so changing a filter never asks the server again.
        $logs = ActivityLog::with('user')
            ->latest('id')
            ->limit(2000)
            ->get();

        return view('activity.index', compact('logs', 'q', 'action'));
    }
}
