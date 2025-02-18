<?php

namespace Aerni\AdvancedSeo\Contracts;

use Statamic\Contracts\Data\Localizable;

interface SeoDefaultSet extends Localizable
{
    public function in($locale);

    public function inSelectedSite();

    public function inCurrentSite();

    public function inDefaultSite();
}
