<?php

namespace Tests\Feature\Reports;

use App\Models\Allocation;
use App\Models\Member;
use App\Models\User;
use App\Services\Reports\ReportPeriod;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['beem.ussd_group_id' => 1]);
        $this->seed([DatabaseSeeder::class, DemoActivitySeeder::class]);
    }

    private function officer(string $role = 'treasurer'): User
    {
        return User::factory()->create(['role' => $role, 'group_id' => 1]);
    }

    public function test_every_report_renders_for_each_kind_of_period(): void
    {
        $this->actingAs($this->officer());
        $amina = Member::query()->where('member_number', 'M001')->first();

        $this->get('/reports')->assertOk()->assertSee('Wadaiwa');

        foreach (['income', 'payers', 'debtors'] as $type) {
            foreach (['', '?type=quarter&quarter=2&year=2026', '?type=year&year=2025'] as $query) {
                $this->get("/reports/{$type}{$query}")->assertOk();
            }
        }

        $this->get('/reports/member')->assertOk()->assertSee('Chagua mwanachama');
        $this->get("/reports/member?member={$amina->id}&type=year")->assertOk()->assertSee('Amina Juma')->assertSee('Ada kwa mwezi');
        $this->get('/reports/unknown')->assertNotFound();
    }

    public function test_income_report_matches_posted_allocations(): void
    {
        $total = (int) Allocation::query()->sum('amount');

        $this->actingAs($this->officer())->get('/reports/income?type=year')
            ->assertOk()
            ->assertSee('TSh '.number_format($total))
            ->assertSee('Kwa njia ya malipo')
            ->assertSee('M-Pesa');
    }

    public function test_debtors_report_lists_amina_with_two_months(): void
    {
        $response = $this->actingAs($this->officer())->get('/reports/debtors')->assertOk();

        $response->assertSee('Amina Juma');
        $this->assertMatchesRegularExpression('/Amina Juma.*?>\s*2\s*</s', $response->getContent());
    }

    public function test_pdf_download_is_a_real_pdf(): void
    {
        $response = $this->actingAs($this->officer())->get('/reports/debtors/download/pdf');

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('wadaiwa-'.now()->format('Y-m').'.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_excel_download_keeps_amounts_as_numbers(): void
    {
        $response = $this->actingAs($this->officer())->get('/reports/payers/download/xlsx?type=year');
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'test').'.xlsx';
        file_put_contents($path, $response->getFile()->getContent());

        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        unlink($path);

        $this->assertSame('Ripoti ya waliolipa', $rows[0][0]);
        $totals = collect($rows)->last();
        $this->assertSame('Jumla', $totals[1]);
        $this->assertSame((int) Allocation::query()->sum('amount'), (int) end($totals));
    }

    public function test_invalid_downloads_are_not_found(): void
    {
        $this->actingAs($this->officer())->get('/reports/member/download/pdf')->assertNotFound();
        $this->actingAs($this->officer())->get('/reports/income/download/csv')->assertNotFound();
    }

    public function test_chair_can_see_reports_but_secretary_cannot(): void
    {
        $this->actingAs($this->officer('chair'))->get('/reports/income')->assertOk();
        $this->actingAs($this->officer('secretary'))->get('/reports')->assertForbidden();
    }

    public function test_quarter_period_covers_three_months(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', ['type' => 'quarter', 'quarter' => 4, 'year' => 2026]));

        $this->assertSame('2026-10-01', $period->start->toDateString());
        $this->assertSame('2026-12-31', $period->end->toDateString());
        $this->assertSame('Robo ya 4, 2026 (Oktoba – Desemba)', $period->label());
        $this->assertCount(3, $period->months());
    }
}
