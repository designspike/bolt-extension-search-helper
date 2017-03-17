<?php

namespace Bolt\Extension\DesignSpike\SearchHelper;

use Bolt\Events\StorageEvent;
use Bolt\Events\StorageEvents;
use Bolt\Extension\SimpleExtension;
use Bolt\Storage\Mapping\ContentType;
use Bolt\Storage\Entity\Content;

class SearchHelperExtension extends SimpleExtension
{
    public static function getSubscribedEvents()
    {
        $localEvents = [
            StorageEvents::PRE_SAVE => [
                ['onPreSave', 0]
            ]
        ];
        return  $localEvents + parent::getSubscribedEvents();
    }

    /**
     * Save the string values of a repeater field to a specified keywords field.
     */
    public function onPreSave(StorageEvent $event)
    {
        $config = $this->getConfig();

        $globArr = []; // we wil temporarily store the globbed together keywords here

        /** @var Content $subject */
        $subject = $event->getSubject();

        if (!$subject instanceof Content) {
            return;
        }

        /** @var ContentType $contentType */
        $contentType = $subject->getContenttype();

        $fields = $contentType->getFields();

        // do we even have any contenttypes specified in this extension's config?
        if (!isset($config['contenttypes']) or !is_array($config['contenttypes'])) {
            return;
        }

        // is this one of the contenttypes that is specified in the extension's config?
        $contentTypeSlug = $contentType->offsetGet('slug');
        if (!array_key_exists($contentTypeSlug, $config['contenttypes'])) {
            return;
        }

        // does the config specify a field to store the keywordfield?
        if (!array_key_exists('keywordfield', $config['contenttypes'][$contentTypeSlug])) {
            return;
        }

        // ensure that the field where the keywords will be stored actually exists
        $keywordField = $config['contenttypes'][$contentTypeSlug]['keywordfield'];
        if (!array_key_exists($keywordField, $fields)) {
            return;
        }

        // ensure the field is a string type
        if (!in_array($fields[$keywordField]['type'], ['textarea', 'text', 'html'])) {
            return;
        }

        foreach ($fields as $fieldName => $field) {
            // we only care about repeaters
            if ($field['type'] !== 'repeater') {
                continue;
            }
            // loop through each index of the repeater
            foreach ($subject->get($fieldName) as $item) {
                foreach($item as $value) {
                    if (!is_string($value)) {
                        continue;
                    }
                    // ignore json
                    json_decode($value);
                    if (JSON_ERROR_NONE===json_last_error()) {
                        continue;
                    }
                    $globArr[] = strip_tags($value);
                }
            }
        }

        // glob the strings together, then clean up and save
        $globStr = join(" ", $globArr);
        $globStr = trim(preg_replace("@\s+@", " ", $globStr));

        $subject->set($keywordField, $globStr);
    }

}
