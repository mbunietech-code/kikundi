<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Jobs\SendSmsNotification;
use App\Models\Announcement;
use App\Models\Member;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('officer.announcements.index', [
            'announcements' => Announcement::query()->where('group_id', $this->groupId())->latest()->paginate(10),
            'recipients' => Member::query()->where('group_id', $this->groupId())->where('status', 'active')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:300'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'publish' => ['nullable', 'boolean'],
            'send_sms' => ['nullable', 'boolean'],
        ], [], ['title' => __('kichwa'), 'body' => __('ujumbe'), 'expires_at' => __('mwisho')]);

        $publish = $request->boolean('publish') || $request->boolean('send_sms');

        $announcement = Announcement::query()->create([
            'group_id' => $this->groupId(),
            'title' => $data['title'],
            'body' => $data['body'],
            'expires_at' => $data['expires_at'] ?? null,
            'status' => $publish ? 'published' : 'draft',
            'published_at' => $publish ? now() : null,
        ]);

        $sent = $request->boolean('send_sms') ? $this->sendSms($announcement) : 0;

        return back()->with('status', $sent ? __('Tangazo limechapishwa na SMS :count zimepangwa kutumwa.', ['count' => $sent]) : ($publish ? __('Tangazo limechapishwa. Linaonekana kwenye USSD.') : __('Tangazo limehifadhiwa kama rasimu.')));
    }

    public function publish(Announcement $announcement): RedirectResponse
    {
        $this->ensureGroup($announcement);
        $announcement->update(['status' => 'published', 'published_at' => now()]);

        return back()->with('status', __('Tangazo limechapishwa.'));
    }

    public function sms(Announcement $announcement): RedirectResponse
    {
        $this->ensureGroup($announcement);
        $sent = $this->sendSms($announcement);

        return back()->with('status', __('SMS :count zimepangwa kutumwa.', ['count' => $sent]));
    }

    private function sendSms(Announcement $announcement): int
    {
        $message = $announcement->title.': '.$announcement->body.' - '.Settings::systemName();
        $phones = Member::query()->where('group_id', $announcement->group_id)->where('status', 'active')->pluck('phone')->unique();

        $phones->each(fn ($phone) => SendSmsNotification::dispatch($phone, $message));

        return $phones->count();
    }
}
