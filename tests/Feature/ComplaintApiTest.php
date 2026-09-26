<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\District;
use App\Models\User;
use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ComplaintApiTest extends TestCase
{
    use RefreshDatabase;

    private int $twelve;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoCitySeeder::class);
        $this->twelve = District::where('name', '12 мкр')->value('id');
    }

    private function complain(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/complaints', $overrides + [
            'district_id' => $this->twelve,
            'category' => 'transport',
            'text' => 'Пробка на выезде из микрорайона каждое утро',
        ]);
    }

    private function akimatToken(): string
    {
        User::where('email', 'akimat@citylab.kz')->update(['password' => Hash::make('secret-pass')]);

        return $this->postJson('/api/login', ['email' => 'akimat@citylab.kz', 'password' => 'secret-pass'])->json('token');
    }

    public function test_resident_complaint_appears_in_the_feed(): void
    {
        $this->complain()->assertCreated()
            ->assertJsonPath('district_id', $this->twelve)
            ->assertJsonPath('category', 'transport')
            ->assertJsonPath('status', 'new');
        $this->complain(['category' => 'other', 'text' => 'Нет урн у остановки'])->assertCreated()->assertJsonPath('category', 'other');

        $feed = $this->getJson('/api/complaints')->assertOk()->json();

        $this->assertCount(2, $feed);
        $this->assertSame('Нет урн у остановки', $feed[0]['text']); // новые сверху
        $this->assertArrayHasKey('created_at', $feed[0]);
    }

    public function test_complaint_is_validated(): void
    {
        $this->complain(['district_id' => 999_999, 'category' => 'weather', 'text' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['district_id', 'category', 'text']);
        $this->complain(['text' => str_repeat('а', 281)])->assertStatus(422)->assertJsonValidationErrors('text');
    }

    public function test_submissions_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->complain()->assertCreated();
        }

        $this->complain()->assertStatus(429);
    }

    public function test_feed_shows_only_active_complaints_of_the_last_day(): void
    {
        $this->complain(['text' => 'Свежая жалоба на пробки'])->assertCreated();
        Complaint::create(['district_id' => $this->twelve, 'text' => 'Старая', 'status' => 'new', 'created_at' => now()->subHours(25)]);
        Complaint::create(['district_id' => $this->twelve, 'text' => 'Решённая', 'status' => 'resolved']);
        Complaint::create(['district_id' => $this->twelve, 'text' => 'Скрытая', 'status' => 'hidden']);

        $this->assertSame(['Свежая жалоба на пробки'], array_column($this->getJson('/api/complaints')->json(), 'text'));
    }

    public function test_only_akimat_changes_status(): void
    {
        $id = $this->complain()->json('id');

        $this->putJson("/api/complaints/{$id}", ['status' => 'resolved'])->assertUnauthorized();

        $this->withToken($this->akimatToken())->putJson("/api/complaints/{$id}", ['status' => 'resolved'])
            ->assertOk()->assertJsonPath('status', 'resolved');
        $this->assertSame([], $this->getJson('/api/complaints')->json());
    }

    public function test_status_is_validated(): void
    {
        $id = $this->complain()->json('id');

        $this->withToken($this->akimatToken())->putJson("/api/complaints/{$id}", ['status' => 'deleted'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }
}
