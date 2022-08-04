<?php

namespace Aerni\AdvancedSeo\Fields;

use Aerni\AdvancedSeo\Actions\ShouldDisplaySitemapSettings;
use Aerni\AdvancedSeo\Actions\ShouldDisplaySocialImagesGenerator;
use Aerni\AdvancedSeo\Concerns\HasAssetField;
use Aerni\AdvancedSeo\Facades\SocialImage;
use Aerni\AdvancedSeo\Models\SocialImageTheme;
use Statamic\Facades\Fieldset;

class OnPageSeoFields extends BaseFields
{
    use HasAssetField;

    public function sections(): array
    {
        return [
            $this->titleAndDescription(),
            $this->socialImages(),
            $this->canonicalUrl(),
            $this->indexing(),
            $this->sitemap(),
            $this->jsonLd(),
        ];
    }

    public function titleAndDescription(): array
    {
        return [
            [
                'handle' => 'seo_section_title_description',
                'field' => [
                    'type' => 'section',
                    'display' => 'Title & Description',
                    'instructions' => $this->trans('seo_section_title_description', 'instructions'),
                ],
            ],
            [
                'handle' => 'seo_site_name_position',
                'field' => [
                    'display' => 'Site Name Position',
                    'instructions' => $this->trans('seo_site_name_position', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'button_group-fieldtype',
                    'field' => [
                        'type' => 'button_group',
                        'options' => [
                            'end' => 'End',
                            'start' => 'Start',
                            'disabled' => 'Disabled',
                        ],
                    ],
                ],
            ],
            [
                'handle' => 'seo_title',
                'field' => [
                    'display' => 'Meta Title',
                    'instructions' => $this->trans('seo_title', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@auto',
                    'auto' => 'title',
                    'localizable' => true,
                    'classes' => 'text-fieldtype',
                    'field' => [
                        'type' => 'text',
                        'character_limit' => 60,
                        'antlers' => false,
                    ],
                ],
            ],
            [
                'handle' => 'seo_description',
                'field' => [
                    'display' => 'Meta Description',
                    'instructions' => $this->trans('seo_description', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'textarea-fieldtype',
                    'field' => [
                        'type' => 'textarea',
                        'character_limit' => 160,
                    ],
                ],
            ],
        ];
    }

    public function socialImages(): array
    {
        $fields = collect([
            $this->openGraphImage(),
            $this->twitterImage(),
        ]);

        if (isset($this->data) && ShouldDisplaySocialImagesGenerator::handle($this->data)) {
            $fields->prepend($this->socialImagesGeneratorFields());
            $fields->prepend($this->socialImagesGenerator());
        }

        return $fields->flatten(1)->toArray();
    }

    public function socialImagesGenerator(): array
    {
        $fields = collect([
            [
                'handle' => 'seo_section_social_images_generator',
                'field' => [
                    'type' => 'section',
                    'display' => 'Social Images Generator',
                    'instructions' => $this->trans('seo_section_social_images_generator', 'instructions'),
                    'listable' => 'hidden',
                ],
            ],
            [
                'handle' => 'seo_generate_social_images',
                'field' => [
                    'display' => 'Generate Social Images',
                    'instructions' => $this->trans('seo_generate_social_images', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'toggle-fieldtype',
                    'field' => [
                        'type' => 'toggle',
                    ],
                ],
            ],
            [
                'handle' => 'seo_social_images_theme',
                'field' => [
                    'type' => 'hidden',
                    'default' => SocialImageTheme::fieldtypeDefault(),
                ],
            ],
        ]);

        if (SocialImageTheme::all()->count() > 1) {
            $fields->put(2, [
                'handle' => 'seo_social_images_theme',
                'field' => [
                    'display' => 'Theme',
                    'instructions' => $this->trans('seo_social_images_theme', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'select-fieldtype',
                    'if' => [
                        'seo_generate_social_images.value' => 'true',
                    ],
                    'field' => [
                        'type' => 'select',
                        'options' => SocialImageTheme::fieldtypeOptions(),
                        'default' => SocialImageTheme::fieldtypeDefault(),
                        'clearable' => false,
                        'multiple' => false,
                        'searchable' => false,
                        'taggable' => false,
                        'push_tags' => false,
                        'cast_booleans' => false,
                    ],
                ],
            ]);
        }

        $fields->push(
            [
                'handle' => 'seo_generated_og_image',
                'field' => [
                    'type' => 'social_image',
                    'image_type' => 'open_graph',
                    'display' => 'Open Graph',
                    'read_only' => true,
                    'listable' => 'hidden',
                    'width' => 50,
                    'if' => [
                        'seo_generate_social_images.value' => 'true',
                    ],
                ],
            ],
            [
                'handle' => 'seo_generated_twitter_image',
                'field' => [
                    'type' => 'social_image',
                    'image_type' => 'twitter',
                    'display' => 'Twitter',
                    'read_only' => true,
                    'listable' => 'hidden',
                    'width' => 50,
                    'if' => [
                        'seo_generate_social_images.value' => 'true',
                    ],
                ],
            ],
        );

        return $fields->toArray();
    }

    public function socialImagesGeneratorFields(): array
    {
        $fieldset = Fieldset::setDirectory(resource_path('fieldsets'))->find('social_images_generator');

        if (! $fieldset) {
            return [];
        }

        return collect($fieldset->contents()['fields'])->map(function ($field) {
            // Prefix the field handles to avoid naming conflicts.
            $field['handle'] = "seo_social_images_{$field['handle']}";

            // Hide the fields if the toggle is of.
            $field['field']['if'] = [
                'seo_generate_social_images.value' => 'true',
            ];

            return $field;
        })->toArray();
    }

    public function openGraphImage(): array
    {
        $fields = [
            [
                'handle' => 'seo_section_og',
                'field' => [
                    'type' => 'section',
                    'display' => 'Open Graph',
                    'instructions' => $this->trans('seo_section_og', 'instructions'),
                ],
            ],
            [
                'handle' => 'seo_og_image',
                'field' => [
                    'display' => 'Open Graph Image',
                    'instructions' => $this->trans('seo_og_image', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'assets-fieldtype',
                    'field' => [
                        'type' => 'assets',
                        'container' => config('advanced-seo.social_images.container', 'assets'),
                        'folder' => 'social_images',
                        'max_files' => 1,
                        'mode' => 'list',
                        'allow_uploads' => true,
                        'restrict' => false,
                        'validate' => [
                            'image',
                            'mimes:jpg,png',
                        ],
                    ],
                ],
            ],
            [
                'handle' => 'seo_og_title',
                'field' => [
                    'display' => 'Open Graph Title',
                    'instructions' => $this->trans('seo_og_title', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@auto',
                    'auto' => 'seo_title',
                    'localizable' => true,
                    'classes' => 'text-fieldtype',
                    'field' => [
                        'type' => 'text',
                        'character_limit' => 70,
                        'antlers' => false,
                    ],
                ],
            ],
            [
                'handle' => 'seo_og_description',
                'field' => [
                    'display' => 'Open Graph Description',
                    'instructions' => $this->trans('seo_og_description', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@auto',
                    'auto' => 'seo_description',
                    'localizable' => true,
                    'classes' => 'textarea-fieldtype',
                    'field' => [
                        'type' => 'textarea',
                        'character_limit' => 200,
                    ],
                ],
            ],
        ];

        if (isset($this->data) && ShouldDisplaySocialImagesGenerator::handle($this->data)) {
            $fields[1]['field']['if']['seo_generate_social_images.value'] = 'isnt true';
        }

        return $fields;
    }

    public function twitterImage(): array
    {
        $fields = [
            [
                'handle' => 'seo_section_twitter',
                'field' => [
                    'type' => 'section',
                    'display' => 'Twitter',
                    'instructions' => $this->trans('seo_section_twitter', 'instructions'),
                ],
            ],
            [
                'handle' => 'seo_twitter_card',
                'field' => [
                    'display' => 'Twitter Card',
                    'instructions' => $this->trans('seo_twitter_card', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'button_group-fieldtype',
                    'field' => [
                        'type' => 'button_group',
                        'options' => [
                            'summary' => 'Regular',
                            'summary_large_image' => 'Large Image',
                        ],
                    ],
                ],
            ],
            [
                'handle' => 'seo_twitter_summary_image',
                'field' => [
                    'display' => 'Twitter Summary Image',
                    'instructions' => $this->trans('seo_twitter_summary_image', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'assets-fieldtype',
                    'twitter_card' => SocialImage::findModel('twitter_summary')['card'],
                    'if' => [
                        'seo_twitter_card.value' => 'equals summary',
                    ],
                    'field' => [
                        'type' => 'assets',
                        'container' => config('advanced-seo.social_images.container', 'assets'),
                        'folder' => 'social_images',
                        'max_files' => 1,
                        'mode' => 'list',
                        'allow_uploads' => true,
                        'restrict' => false,
                        'validate' => [
                            'image',
                            'mimes:jpg,png',
                        ],
                    ],
                ],
            ],
            [
                'handle' => 'seo_twitter_summary_large_image',
                'field' => [
                    'display' => 'Twitter Summary Large Image',
                    'instructions' => $this->trans('seo_twitter_summary_large_image', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'assets-fieldtype',
                    'twitter_card' => SocialImage::findModel('twitter_summary_large_image')['card'],
                    'if' => [
                        'seo_twitter_card.value' => 'equals summary_large_image',
                    ],
                    'field' => [
                        'type' => 'assets',
                        'container' => config('advanced-seo.social_images.container', 'assets'),
                        'folder' => 'social_images',
                        'max_files' => 1,
                        'mode' => 'list',
                        'allow_uploads' => true,
                        'restrict' => false,
                        'validate' => [
                            'image',
                            'mimes:jpg,png',
                        ],
                    ],
                ],
            ],
            [
                'handle' => 'seo_twitter_title',
                'field' => [
                    'display' => 'Twitter Title',
                    'instructions' => $this->trans('seo_twitter_title', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@auto',
                    'auto' => 'seo_title',
                    'localizable' => true,
                    'classes' => 'text-fieldtype',
                    'field' => [
                        'type' => 'text',
                        'character_limit' => 70,
                        'antlers' => false,
                    ],
                ],
            ],
            [
                'handle' => 'seo_twitter_description',
                'field' => [
                    'display' => 'Twitter Description',
                    'instructions' => $this->trans('seo_twitter_description', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@auto',
                    'auto' => 'seo_description',
                    'localizable' => true,
                    'classes' => 'textarea-fieldtype',
                    'field' => [
                        'type' => 'textarea',
                        'character_limit' => 200,
                    ],
                ],
            ],
        ];

        if (isset($this->data) && ShouldDisplaySocialImagesGenerator::handle($this->data)) {
            $fields[2]['field']['if']['seo_generate_social_images.value'] = 'isnt true';
            $fields[3]['field']['if']['seo_generate_social_images.value'] = 'isnt true';
        }

        return $fields;
    }

    public function canonicalUrl(): array
    {
        return [
            [
                'handle' => 'seo_section_canonical_url',
                'field' => [
                    'type' => 'section',
                    'display' => 'Canonical URL',
                    'instructions' => $this->trans('seo_section_canonical_url', 'instructions'),
                ],
            ],
            [
                'handle' => 'seo_canonical_type',
                'field' => [
                    'display' => 'Canonical URL',
                    'instructions' => $this->trans('seo_canonical_type', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'button_group-fieldtype',
                    'field' => [
                        'type' => 'button_group',
                        'options' => [
                            'current' => 'Current '.ucfirst(str_singular($this->typePlaceholder())),
                            'other' => 'Other Entry',
                            'custom' => 'Custom URL',
                        ],
                    ],
                ],
            ],
            [
                'handle' => 'seo_canonical_entry',
                'field' => [
                    'display' => 'Entry',
                    'instructions' => $this->trans('seo_canonical_entry', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'relationship-fieldtype',
                    'if' => [
                        'seo_canonical_type.value' => 'equals other',
                    ],
                    'field' => [
                        'type' => 'entries',
                        'component' => 'relationship',
                        'mode' => 'stack',
                        'max_items' => 1,
                        'validate' => [
                            'required_if:seo_canonical_type,other',
                        ],
                    ],
                ],
            ],
            [
                'handle' => 'seo_canonical_custom',
                'field' => [
                    'display' => 'URL',
                    'instructions' => $this->trans('seo_canonical_custom', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'text-fieldtype',
                    'if' => [
                        'seo_canonical_type.value' => 'equals custom',
                    ],
                    'field' => [
                        'type' => 'text',
                        'input_type' => 'url',
                        'validate' => [
                            'required_if:seo_canonical_type,custom',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function indexing(): array
    {
        return [
            [
                'handle' => 'seo_section_indexing',
                'field' => [
                    'type' => 'section',
                    'display' => 'Indexing',
                    'instructions' => $this->trans('seo_section_indexing', 'instructions'),
                ],
            ],
            [
                'handle' => 'seo_noindex',
                'field' => [
                    'display' => 'Noindex',
                    'instructions' => $this->trans('seo_noindex', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'toggle-fieldtype',
                    'width' => 50,
                    'field' => [
                        'type' => 'toggle',
                    ],
                ],
            ],
            [
                'handle' => 'seo_nofollow',
                'field' => [
                    'display' => 'Nofollow',
                    'instructions' => $this->trans('seo_nofollow', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'toggle-fieldtype',
                    'width' => 50,
                    'field' => [
                        'type' => 'toggle',
                    ],
                ],
            ],
        ];
    }

    public function sitemap(): array
    {
        if (! config('advanced-seo.sitemap.enabled', true)) {
            return [];
        }

        if (isset($this->data) && ! ShouldDisplaySitemapSettings::handle($this->data)) {
            return [];
        }

        return [
            [
                'handle' => 'seo_section_sitemap',
                'field' => [
                    'type' => 'section',
                    'display' => 'Sitemap',
                    'instructions' => $this->trans('seo_section_sitemap', 'instructions'),
                    'if' => [
                        'seo_noindex.value' => 'false',
                        'seo_canonical_type.value' => 'equals current',
                    ],
                ],
            ],
            [
                'handle' => 'seo_sitemap_enabled',
                'field' => [
                    'display' => 'Enabled',
                    'instructions' => $this->trans('seo_sitemap_enabled', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'toggle-fieldtype',
                    'if' => [
                        'seo_noindex.value' => 'false',
                        'seo_canonical_type.value' => 'equals current',
                    ],
                    'field' => [
                        'type' => 'toggle',
                    ],
                ],
            ],
            [
                'handle' => 'seo_sitemap_priority',
                'field' => [
                    'display' => 'Priority',
                    'instructions' => $this->trans('seo_sitemap_priority', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'select-fieldtype',
                    'width' => 50,
                    'if' => [
                        'seo_noindex.value' => 'false',
                        'seo_canonical_type.value' => 'equals current',
                        'seo_sitemap_enabled.value' => 'true',
                    ],
                    'field' => [
                        'type' => 'select',
                        'options' => [
                            '0.0' => '0.0',
                            '0.1' => '0.1',
                            '0.2' => '0.2',
                            '0.3' => '0.3',
                            '0.4' => '0.4',
                            '0.5' => '0.5',
                            '0.6' => '0.6',
                            '0.7' => '0.7',
                            '0.8' => '0.8',
                            '0.9' => '0.9',
                            '1.0' => '1.0',
                        ],
                        'clearable' => false,
                        'multiple' => false,
                        'searchable' => false,
                        'taggable' => false,
                        'push_tags' => false,
                        'cast_booleans' => false,
                    ],
                ],
            ],
            [
                'handle' => 'seo_sitemap_change_frequency',
                'field' => [
                    'display' => 'Change Frequency',
                    'instructions' => $this->trans('seo_sitemap_change_frequency', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'select-fieldtype',
                    'width' => 50,
                    'if' => [
                        'seo_noindex.value' => 'false',
                        'seo_canonical_type.value' => 'equals current',
                        'seo_sitemap_enabled.value' => 'true',
                    ],
                    'field' => [
                        'type' => 'select',
                        'options' => [
                            'always' => 'Always',
                            'hourly' => 'Hourly',
                            'daily' => 'Daily',
                            'weekly' => 'Weekly',
                            'monthly' => 'Monthly',
                            'yearly' => 'Yearly',
                            'never' => 'Never',
                        ],
                        'clearable' => false,
                        'multiple' => false,
                        'searchable' => false,
                        'taggable' => false,
                        'push_tags' => false,
                        'cast_booleans' => false,
                    ],
                ],
            ],
        ];
    }

    public function jsonLd(): array
    {
        return [
            [
                'handle' => 'seo_section_json_ld',
                'field' => [
                    'type' => 'section',
                    'display' => 'JSON-ld Schema',
                    'instructions' => $this->trans('seo_section_json_ld', 'instructions'),
                ],
            ],
            [
                'handle' => 'seo_json_ld',
                'field' => [
                    'display' => 'JSON-LD Schema',
                    'instructions' => $this->trans('seo_json_ld', 'instructions'),
                    'type' => 'seo_source',
                    'default' => '@default',
                    'localizable' => true,
                    'classes' => 'code-fieldtype',
                    'field' => [
                        'type' => 'code',
                        'theme' => 'material',
                        'mode' => 'javascript',
                        'indent_type' => 'tabs',
                        'indent_size' => 4,
                        'key_map' => 'default',
                        'line_numbers' => true,
                        'line_wrapping' => true,
                    ],
                ],
            ],
        ];
    }
}
