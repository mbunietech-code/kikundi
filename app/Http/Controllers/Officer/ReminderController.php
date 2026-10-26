<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Services\Reminders\ReminderService;
use Illuminate\Http\RedirectResponse;

class ReminderController extends Controller
{
    /** Run today's reminders now, e.g. on WAMP without a scheduler. Never sends twice. */
    public function run(ReminderService $reminders): RedirectResponse
    {
        $sent = $reminders->runDaily($this->groupId());
        $total = array_sum($sent);

        return back()->with('status', $total
            ? __('SMS :total zimetumwa: ukumbusho wa ada :dues, ada iliyochelewa :overdue, michango :events.', ['total' => $total, 'dues' => $sent['dues_reminders'], 'overdue' => $sent['dues_overdue'], 'events' => $sent['event_reminders']])
            : __('Hakuna ukumbusho unaohitajika leo, au tayari ulishatumwa.'));
    }

    public function debts(ReminderService $reminders): RedirectResponse
    {
        $sent = $reminders->sendDebtNotices($this->groupId());

        return back()->with('status', $sent
            ? __('Taarifa za deni zimetumwa kwa wadaiwa :count kwa SMS.', ['count' => $sent])
            : __('Hakuna SMS iliyotumwa: hakuna mdaiwa, au wote walishatumiwa leo.'));
    }
}
