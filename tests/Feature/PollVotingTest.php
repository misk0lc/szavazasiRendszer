<?php

namespace Tests\Feature;

use App\Models\Poll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PollVotingTest extends TestCase
{
    use RefreshDatabase;

    protected function createSamplePoll(array $options = ['Igen', 'Nem'], ?string $closesAt = null): Poll
    {
        return Poll::create([
            'question' => 'Tetszik a rendszer?',
            'description' => 'Egyszerű teszt szavazás',
            'options' => $options,
            'closes_at' => $closesAt,
        ]);
    }

    public function test_can_create_poll_and_vote(): void
    {
        $user = User::factory()->create();
        $poll = $this->createSamplePoll();

        $this->actingAs($user);
        $resp = $this->post(route('polls.vote', $poll), [
            'selected_option' => 'Igen',
        ]);

        $resp->assertRedirect(route('polls.results', $poll));
        $this->assertDatabaseHas('votes', [
            'user_id' => $user->id,
            'poll_id' => $poll->id,
            'selected_option' => 'Igen',
        ]);
    }

    public function test_cannot_vote_twice_in_same_poll(): void
    {
        $user = User::factory()->create();
        $poll = $this->createSamplePoll();

        $this->actingAs($user);
        $this->post(route('polls.vote', $poll), [
            'selected_option' => 'Igen',
        ])->assertRedirect(route('polls.results', $poll));

        // second attempt should fail with validation error
        $this->from(route('polls.show', $poll))
            ->post(route('polls.vote', $poll), [
                'selected_option' => 'Nem',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('votes', 1);
    }

    public function test_cannot_vote_on_closed_poll(): void
    {
        $user = User::factory()->create();
        $poll = $this->createSamplePoll(['A', 'B'], now()->subHour()->toDateTimeString());

        $this->actingAs($user);
        $this->from(route('polls.show', $poll))
            ->post(route('polls.vote', $poll), [
                'selected_option' => 'A',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('votes', 0);
    }
}
