<?php

namespace App\Support;

class SubmissionNonce extends \Livewire\ComponentHook
{
    public function dehydrate($context)
    {
        $context->addMemo('submissionNonce', bin2hex(random_bytes(16)));
    }
}
