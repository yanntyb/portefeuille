<?php

use App\Domains\User\Models\Feedback;
use App\Domains\User\Models\User;

it('creates feedback', function () {
    $user = User::factory()->create();

    $feedback = Feedback::factory()->create([
        'user_id' => $user->id,
        'subject' => 'Bug report',
        'body' => 'Found an issue with the portfolio page',
    ]);

    expect($feedback->user_id)->toBe($user->id)
        ->and($feedback->subject)->toBe('Bug report')
        ->and($feedback->body)->toBe('Found an issue with the portfolio page');
});

it('belongs to user', function () {
    $user = User::factory()->create();
    $feedback = Feedback::factory()->create(['user_id' => $user->id]);

    expect($feedback->user()->first()->id)->toBe($user->id);
});

it('has fillable attributes', function () {
    $feedback = new Feedback();

    expect($feedback->getFillable())->toContain('user_id', 'subject', 'body');
});

it('can mass assign user_id', function () {
    $user = User::factory()->create();

    $feedback = Feedback::create([
        'user_id' => $user->id,
        'subject' => 'Feature request',
        'body' => 'Add export to CSV',
    ]);

    expect($feedback->user_id)->toBe($user->id);
});

it('timestamps are set automatically', function () {
    $feedback = Feedback::factory()->create();

    expect($feedback->created_at)->not->toBeNull()
        ->and($feedback->updated_at)->not->toBeNull();
});
