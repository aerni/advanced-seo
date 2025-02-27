<?php

namespace Aerni\AdvancedSeo\Subscribers;

use Aerni\AdvancedSeo\Blueprints\OnPageSeoBlueprint;
use Aerni\AdvancedSeo\Concerns\GetsEventData;
use Aerni\AdvancedSeo\Data\DefaultsData;
use Aerni\AdvancedSeo\Support\Helpers;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Statamic\Events;
use Statamic\Events\Event;
use Statamic\Facades\Fieldset;

class OnPageSeoBlueprintSubscriber
{
    use GetsEventData;

    protected DefaultsData $data;

    public function subscribe(Dispatcher $events): array
    {
        return [
            Events\EntryBlueprintFound::class => 'extendBlueprint',
            Events\TermBlueprintFound::class => 'extendBlueprint',
        ];
    }

    public function extendBlueprint(Event $event): void
    {
        $this->data = $this->getDataFromEvent($event);

        if (! $this->shouldExtendBlueprint($event)) {
            return;
        }

        /**
         * The following code determines which seo fields should be added to the blueprint.
         * This is an important concept, as we will only return data of ensured blueprint fields
         * in the ViewCascade and GraphQlCascade. Data of fields with no blueprint field won't be augmented.
         */

        /**
         * These are the fields that we will end up adding to the blueprint.
         * We are passing $this->data to evaluate the feature of each field to determine if a field should be in the blueprint or not.
         */
        $seoFields = OnPageSeoBlueprint::make()->data($this->data)->get()->fields()->all();

        $usesDefaultSeoBlueprint = $seoFields
            ->intersectByKeys($event->blueprint->fields()->all())
            ->isEmpty();

        $usesDefaultSeoBlueprint
            ? $this->handleDefaultBlueprint($event, $seoFields)
            : $this->handleCustomizedBlueprint($event, $seoFields);
    }

    protected function shouldExtendBlueprint(Event $event): bool
    {
        // Don't add the fields if the collection/taxonomy is disabled.
        if (Helpers::isDisabled($this->data->type, $this->data->handle)) {
            return false;
        }

        // Ensure the collection/taxonomy defaults view doesn't break.
        if (Helpers::isAddonCpRoute()) {
            return false;
        }

        // Ensure the fields won't be saved to file when editing a blueprint.
        if (Helpers::isBlueprintCpRoute()) {
            return false;
        }

        /**
         * TODO: This can be removed when the Statamic PR is merged that ensures a unique blueprint instance.
         * The BlueprintFound event is called for every localization.
         * But we only want to extend the blueprint for the current localization.
         * Otherwise we will have issue evaluating conditional fields, e.g. the sitemap fields.
         */
        if (Helpers::isEntryEditRoute()) {
            return $event->entry?->id() === request()->route()->originalParameter('entry');
        }

        return true;
    }

    /**
     * If the user didn't explicitly add any Advanced SEO fieldsets to the blueprint,
     * we want to add all fields that are part of the 'advanced-seo::main' fieldset to the SEO section.
     * This allows the user to configure the SEO fields that should be shown by default by editing the main fieldset.
     * Any SEO field, that doesn't exist in the fieldset will be added as a hidden field to ensure the addon's functionality.
     */
    protected function handleDefaultBlueprint(Event $event, Collection $seoFields): void
    {
        // The fields that should be shown by default.
        $seoFieldsetFields = Fieldset::find('advanced-seo::main')->fields()->all();

        // Swap the fieldset fields with the actual field from the OnPageSeoBlueprint.
        $seoFieldsWithConfig = $seoFieldsetFields
            ->filter(fn ($field) => $field->type() === 'advanced_seo') // Remove any field that isn't of type `advanced_seo`
            ->filter(fn ($field) => $seoFields->has($field->config()['field'])) // Remove any field that isn't an actual SEO field
            ->map(fn ($field) => $seoFields->get($field->config()['field'])) // Get the field from the OnPageSeoBlueprint fields
            ->mapWithKeys(fn ($field) => [$field->handle() => $field]) // Make sure to set the correct handle
            ->map(fn ($field) => $field->config()); // We need the config to ensure the field below

        // All non-SEO fields the user might have added to the fieldset.
        $customUserFields = $seoFieldsetFields
            ->filter(fn ($field) => $field->type() !== 'advanced_seo')
            ->map(fn ($field) => $field->config());

        // Merge the SEO fields with the user-defined fields.
        $allFields = $seoFieldsWithConfig->merge($customUserFields);

        // The fields that should be visible in the CP in the correct order.
        $visibleFields = $seoFieldsetFields
            ->intersectByKeys($allFields) // Respect each field's position from the fieldset.
            ->merge($allFields); // Merge the preparated fields.

        // Add all visible fields to the blueprint.
        $event->blueprint->ensureFieldsInTab($visibleFields, 'SEO');

        // All other SEO fields that are not part of the fieldset and should not be visible.
        $fieldsToHide = collect($seoFields)->diffKeys($visibleFields);

        $this->ensureHiddenFields($event, $fieldsToHide);
    }

    /**
     * Override each linked 'advanced_seo' field with the corresponding field from the OnPageSeoBlueprint.
     * Note, that this only works with linked fieldsets and regular fields. It doesn't work with single linked fieldset fields.
     */
    protected function handleCustomizedBlueprint(Event $event, Collection $seoFields): void
    {
        // The Advanced SEO fields the user explicitly added to the blueprint.
        $manuallyAddedSeoFields = $event->blueprint->fields()->all()
            ->filter(fn ($field) => $field->type() === 'advanced_seo') // Remove any field that isn't of type `advanced_seo`
            ->filter(fn ($field) => $seoFields->has($field->config()['field'])); // Remove any field that isn't an actual SEO field

        // Custom SEO fields like a computed text field for `seo_title`.
        $customSeoFields = $event->blueprint->fields()->all()
            ->intersectByKeys($seoFields) // Only keep fields that exist as SEO fields
            ->diffKeys($manuallyAddedSeoFields); // Only keep fields that were not already imported

        // Handle individual Advanced SEO fields
        $individualAdvancedSeoFields = $event->blueprint->fields()->items()
            ->filter(fn ($item) => Arr::has($item, 'handle') && is_array($item['field'])) // Remove imported fieldsets and sinlge imported fieldset fields.
            ->mapWithKeys(fn ($item) => [$item['handle'] => $item['field']])
            ->intersectByKeys($manuallyAddedSeoFields) // Only keep the field that are actual Advanced SEO fields
            ->map(fn ($field) => $seoFields->get($field['field']))
            ->each(fn ($field) => $event->blueprint->ensureFieldHasConfig($field->handle(), $field->config()));

        // Handle imported fieldset fields
        $importedFieldsetFields = $manuallyAddedSeoFields
            ->diffKeys($individualAdvancedSeoFields)
            ->each(function ($field) use ($seoFields) {
                $seoField = $seoFields[$field->config()['field']];
                $handle = $seoField->handle();
                $config = $seoField->config();

                $field->setHandle($handle)->setConfig($config);
            });

        // All the fields that are visible
        $visibleFields = $individualAdvancedSeoFields
            ->merge($importedFieldsetFields)
            ->merge($customSeoFields);

        // All other SEO fields that are not part of the fieldset and should not be visible.
        $fieldsToHide = collect($seoFields)->diffKeys($visibleFields);

        $this->ensureHiddenFields($event, $fieldsToHide);
    }

    /**
     * We need to ensure that all SEO fields are added to the blueprint to ensure the addon's functionality.
     * But we'll hide those fields so that it's up to the user to decide which fields should be editable.
     */
    protected function ensureHiddenFields(Event $event, Collection $fieldsToHide): void
    {
        $fieldsToHide->map(fn ($field) => $field->config()) // We need the config to ensure the field below.
            ->map(fn ($config) => array_merge($config, ['if' => ['hide_me_and_do_not_save_data' => true]])) // Add condition to hide the field and save no data.
            ->each(fn ($config, $handle) => $event->blueprint->ensureField($handle, $config));
    }
}
