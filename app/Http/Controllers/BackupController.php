<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Throwable;

/** Owner-only database backup and restore. */
class BackupController extends Controller
{
    public function __construct(private BackupService $backups)
    {
    }

    public function index()
    {
        return view('backup.index', ['backups' => $this->backups->list()]);
    }

    public function store()
    {
        try {
            $name = $this->backups->create();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['backup' => 'The backup could not be created. Please try again or contact the developer.']);
        }

        ActivityLog::record('backup_create', 'Created backup ' . $name . '.');

        return redirect()->route('backup.index')->with('status', 'Backup created successfully (' . $name . '). Download it and keep a copy on an external drive.');
    }

    public function download(string $name)
    {
        $path = $this->backups->path($name);
        abort_unless($path, 404);

        return response()->download($path);
    }

    public function restore(Request $request)
    {
        $request->validate([
            'backup_file'  => ['required', 'file', 'max:102400'],
            'password'     => ['required'],
            'confirmation' => ['required', 'in:RESTORE'],
        ], [
            'confirmation.in' => 'Type RESTORE (in capital letters) to confirm.',
        ]);

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return back()->withErrors(['password' => 'Your password is incorrect.']);
        }

        $json = file_get_contents($request->file('backup_file')->getRealPath());

        try {
            // Safety copy of the current data first, so a wrong restore can be undone.
            $this->backups->create('-before-restore');
            $counts = $this->backups->restore($json);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['backup_file' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['backup_file' => 'The restoration failed and no data was changed.']);
        }

        ActivityLog::record('backup_restore', 'Restored the database from ' . $request->file('backup_file')->getClientOriginalName() . ' (' . array_sum($counts) . ' records).');

        // The users table was replaced, so the current session must start again.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Database restored successfully (' . array_sum($counts) . ' records). Please log in again.');
    }
}
