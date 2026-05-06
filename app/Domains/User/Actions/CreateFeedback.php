<?php

namespace App\Domains\User\Actions;

use App\Domains\User\Models\Feedback;
use App\Domains\User\Models\User;

class CreateFeedback
{
    /**
     * @param  array{subject: string, body: string}  $data
     */
    public function execute(User $user, array $data): Feedback
    {
        return Feedback::create([
            'user_id' => $user->id,
            'subject' => $data['subject'],
            'body' => $data['body'],
        ]);
    }
}
