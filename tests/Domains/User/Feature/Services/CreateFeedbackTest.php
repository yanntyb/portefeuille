<?php

use App\Domains\User\Actions\CreateFeedback;
use App\Domains\User\Models\Feedback;
use App\Domains\User\Models\User;

it('creates a feedback for a user', function (): void {
    $user = User::factory()->create();

    $data = [
        'subject' => 'Bug report',
        'body' => 'Something is broken.',
    ];

    $feedback = (new CreateFeedback)->execute($user, $data);

    expect($feedback)
        ->toBeInstanceOf(Feedback::class)
        ->subject->toBe('Bug report')
        ->body->toBe('Something is broken.')
        ->user_id->toBe($user->id);

    $this->assertDatabaseHas('feedback', [
        'user_id' => $user->id,
        'subject' => 'Bug report',
        'body' => 'Something is broken.',
    ]);
});
