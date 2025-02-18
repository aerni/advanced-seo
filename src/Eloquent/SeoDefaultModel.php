<?php

namespace Aerni\AdvancedSeo\Eloquent;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Statamic\Eloquent\Database\BaseModel;

class SeoDefaultModel extends BaseModel
{
    protected $guarded = [];

    protected $table = 'advanced_seo_defaults';

    protected $casts = [
        'data' => AsCollection::class,
    ];
}
