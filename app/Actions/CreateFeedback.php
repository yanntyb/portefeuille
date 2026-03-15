<?php

namespace App\Actions;

use App\Models\Feedback;
use App\Models\User;

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
