<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\Reports\Report;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportService;
use App\Support\GroupContext;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('officer.reports.index', ['types' => ReportService::TYPES]);
    }

    public function show(Request $request, string $type, ReportService $reports): View
    {
        abort_unless(array_key_exists($type, ReportService::TYPES), 404);
        $period = ReportPeriod::fromRequest($request);
        $member = $this->member($request, $type);

        return view('officer.reports.show', [
            'type' => $type,
            'types' => ReportService::TYPES,
            'period' => $period,
            'member' => $member,
            'members' => $type === 'member' ? Member::query()->where('group_id', $this->groupId())->orderBy('member_number')->get()
                ->mapWithKeys(fn ($m) => [$m->id => "{$m->member_number} · {$m->full_name}"])->all() : [],
            'report' => $type !== 'member' || $member ? $this->build($reports, $type, $period, $member) : null,
        ]);
    }

    public function download(Request $request, string $type, string $format, ReportService $reports, ReportExporter $exporter): Response
    {
        abort_unless(array_key_exists($type, ReportService::TYPES) && in_array($format, ['pdf', 'xlsx'], true), 404);
        $member = $this->member($request, $type);
        abort_if($type === 'member' && ! $member, 404);

        $report = $this->build($reports, $type, ReportPeriod::fromRequest($request), $member);

        return $format === 'pdf' ? $exporter->pdf($report) : $exporter->xlsx($report);
    }

    private function build(ReportService $reports, string $type, ReportPeriod $period, ?Member $member): Report
    {
        return $reports->build($type, GroupContext::for(auth()->user()), $period, $member);
    }

    private function member(Request $request, string $type): ?Member
    {
        if ($type !== 'member' || ! $request->filled('member')) {
            return null;
        }

        $member = Member::query()->findOrFail((int) $request->query('member'));
        $this->ensureGroup($member);

        return $member;
    }
}
