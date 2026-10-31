<?php

namespace Tests\Feature\Membership;

use App\Models\Member;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GuarantorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Queue::fake();
    }

    private function officer(string $role = 'secretary'): User
    {
        return User::factory()->create(['role' => $role, 'group_id' => 1]);
    }

    private function activeMember(string $number = 'M090', string $phone = '255754111222'): Member
    {
        return Member::query()->create(['group_id' => 1, 'member_number' => $number, 'full_name' => 'Mdhamini '.$number, 'phone' => $phone, 'status' => 'active']);
    }

    private function newMember(array $guarantors): array
    {
        return ['member_number' => '', 'full_name' => 'Juma Mpya', 'phone' => '0754999999', 'joined_at' => '2026-10-01', 'guarantors' => $guarantors];
    }

    public function test_new_member_is_registered_with_a_member_and_an_outsider_as_guarantors(): void
    {
        $fellow = $this->activeMember();

        $this->actingAs($this->officer())->post('/members', $this->newMember([
            ['member_id' => $fellow->id, 'relationship' => 'rafiki'],
            ['name' => 'Asha Nje', 'phone' => '0712000111', 'relationship' => 'ndugu'],
            ['member_id' => '', 'name' => '', 'phone' => ''],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $member = Member::query()->where('full_name', 'Juma Mpya')->firstOrFail();
        $this->assertSame(2, $member->guarantors()->count());
        $this->assertDatabaseHas('member_guarantors', ['member_id' => $member->id, 'guarantor_member_id' => $fellow->id, 'name' => $fellow->full_name, 'relationship' => 'rafiki']);
        $this->assertDatabaseHas('member_guarantors', ['member_id' => $member->id, 'guarantor_member_id' => null, 'name' => 'Asha Nje', 'phone' => '255712000111']);

        $this->get("/members/{$member->id}")->assertSee('Asha Nje')->assertSee($fellow->full_name);
        $this->get("/members/{$fellow->id}")->assertSee('Amewadhamini')->assertSee('Juma Mpya');
    }

    public function test_required_number_of_guarantors_is_enforced(): void
    {
        Settings::set(['min_guarantors' => '2']);

        $this->actingAs($this->officer())->get('/members/create')->assertSee('Katiba inahitaji wadhamini 2 kwa mwanachama mpya.');

        $this->post('/members', $this->newMember([['name' => 'Asha Nje', 'phone' => '0712000111']]))->assertSessionHasErrors('guarantors');
        $this->assertDatabaseMissing('members', ['full_name' => 'Juma Mpya']);
    }

    public function test_outsider_needs_name_and_phone_and_inactive_members_are_rejected(): void
    {
        $inactive = $this->activeMember();
        $inactive->forceFill(['status' => 'inactive'])->save();

        $this->actingAs($this->officer())->post('/members', $this->newMember([['name' => 'Bila Simu']]))->assertSessionHasErrors('guarantors.0.phone');
        $this->post('/members', $this->newMember([['member_id' => $inactive->id]]))->assertSessionHasErrors('guarantors.0.member_id');
        $this->assertDatabaseMissing('members', ['full_name' => 'Juma Mpya']);
    }

    public function test_guarantors_are_added_and_removed_on_the_member_page(): void
    {
        $member = $this->activeMember('M091', '255754333444');
        $fellow = $this->activeMember();
        $secretary = $this->officer();

        $this->actingAs($secretary)->post("/members/{$member->id}/guarantors", ['guarantors' => [['member_id' => $member->id]]])
            ->assertSessionHasErrors('guarantors.0.member_id');

        $this->post("/members/{$member->id}/guarantors", ['guarantors' => [['member_id' => $fellow->id]]])->assertSessionHasNoErrors();
        $guarantor = $member->guarantors()->firstOrFail();
        $this->assertSame($fellow->id, $guarantor->guarantor_member_id);

        $this->delete("/members/{$member->id}/guarantors/{$guarantor->id}")->assertRedirect();
        $this->assertModelMissing($guarantor);
    }

    public function test_chair_cannot_change_guarantors(): void
    {
        $member = $this->activeMember();

        $this->actingAs($this->officer('chair'))->post("/members/{$member->id}/guarantors", ['guarantors' => [['name' => 'Asha', 'phone' => '0712000111']]])->assertForbidden();
    }

    public function test_deleting_a_guarantor_member_keeps_the_record_by_name(): void
    {
        $member = $this->activeMember('M091', '255754333444');
        $fellow = $this->activeMember();
        $this->actingAs($this->officer())->post("/members/{$member->id}/guarantors", ['guarantors' => [['member_id' => $fellow->id]]]);

        $this->delete("/members/{$fellow->id}")->assertRedirect('/members');

        $this->assertDatabaseHas('member_guarantors', ['member_id' => $member->id, 'guarantor_member_id' => null, 'name' => $fellow->full_name]);
    }
}
