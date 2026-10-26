<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Backup\BackupService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(BackupService $backups): View
    {
        return view('admin.backups', ['backups' => $backups->list(), 'keepDays' => (int) Settings::get('backup_keep_days')]);
    }

    public function store(BackupService $backups): RedirectResponse
    {
        $name = $backups->create();
        $backups->prune();

        return back()->with('status', __('Nakala :name imetengenezwa. Ipakue na uihifadhi nje ya kompyuta hii.', ['name' => basename($name)]));
    }

    public function download(string $file, BackupService $backups): BinaryFileResponse
    {
        $path = $backups->path($file);
        abort_unless($path, 404);

        return response()->download($path, $file);
    }
}
