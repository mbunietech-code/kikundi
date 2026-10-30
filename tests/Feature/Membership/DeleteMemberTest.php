<?php

namespace Tests\Feature\Membership;

use App\Models\ContributionEvent;
use App\Models\Member;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoActivitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeleteMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['beem.ussd_group_id' => 1]);
        $this->seed([DatabaseSeeder::class, DemoActivitySeeder::class]);
        Queue::fake();
    }

    private function officer(string $role = 'secretary'): User
    {
        return User::factory()->create(['role' => $role, 'group_id' => 1]);
    }

    public function test_member_registered_by_mistake_is_deleted_with_their_records(): void
    {
        $secretary = $this->officer();
        $this->actingAs($secretary)->post('/members', ['member_number' => '', 'full_name' => 'Kosa Kosa', 'phone' => '0754999999', 'joined_at' => '2026-10-01'])->assertRedirect();
        $mistake = Member::query()->where('full_name', 'Kosa Kosa')->firstOrFail();
        $mistake->dependants()->create(['group_id' => 1, 'name' => 'Mtoto', 'relationship' => 'mtoto']);

        $this->actingAs($secretary)->get("/members/{$mistake->id}")->assertSee('Futa mwanachama')->assertSee(route('members.destroy', $mistake), false);
        $this->actingAs($secretary)->delete("/members/{$mistake->id}")->assertRedirect('/members');

        $this->assertModelMissing($mistake);
        $this->assertDatabaseMissing('membership_fees', ['member_id' => $mistake->id]);
        $this->assertDatabaseMissing('dependants', ['member_id' => $mistake->id]);
        $this->assertDatabaseMissing('member_status_changes', ['member_id' => $mistake->id]);
    }

    public function test_beneficiary_link_is_cleared_but_the_event_kept(): void
    {
        // A member with no payments, named as an event beneficiary.
        $fresh = Member::query()->create(['group_id' => 1, 'member_number' => 'M099', 'full_name' => 'Mpya', 'phone' => '255754888888', 'status' => 'active']);
        $event = ContributionEvent::query()->create(['group_id' => 1, 'event_type' => 'illness', 'title' => 'Msaada', 'beneficiary_member_id' => $fresh->id, 'status' => 'active']);

        $this->actingAs($this->officer())->delete("/members/{$fresh->id}")->assertRedirect('/members');

        $this->assertNull($event->fresh()->beneficiary_member_id);
    }

    public function test_member_with_recorded_payments_cannot_be_deleted(): void
    {
        $neema = Member::query()->where('member_number', 'M003')->firstOrFail();
        $secretary = $this->officer();

        $this->actingAs($secretary)->get("/members/{$neema->id}")->assertSee('Hariri hali')->assertDontSee('name="_method" value="DELETE"', false);
        $this->actingAs($secretary)->delete("/members/{$neema->id}")->assertSessionHas('error');

        $this->assertModelExists($neema);
    }

    public function test_chair_and_treasurer_cannot_delete(): void
    {
        $fresh = Member::query()->create(['group_id' => 1, 'member_number' => 'M098', 'full_name' => 'Mpya', 'phone' => '255754777777', 'status' => 'active']);

        $this->actingAs($this->officer('chair'))->delete("/members/{$fresh->id}")->assertForbidden();
        $this->actingAs($this->officer('treasurer'))->delete("/members/{$fresh->id}")->assertForbidden();
        $this->assertModelExists($fresh);
    }
}
