<?php

namespace Aerni\AdvancedSeo\Events;

use Aerni\AdvancedSeo\Contracts\SeoDefaultSet;
use Statamic\Contracts\Git\ProvidesCommitMessage;
use Statamic\Events\Event;

class SeoDefaultSetSaved extends Event implements ProvidesCommitMessage
{
    public function __construct(public SeoDefaultSet $defaults)
    {
        //
    }

    public function commitMessage()
    {
        return __('advanced-seo::messages.commit_message', [], config('statamic.git.locale'));
    }
}
