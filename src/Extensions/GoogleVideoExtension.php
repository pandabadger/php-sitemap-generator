<?php


namespace Icamys\SitemapGenerator\Extensions;


use DateTime;
use InvalidArgumentException;
use XMLWriter;


class GoogleVideoExtension
{
    private static $requiredFields = [
        'video:thumbnail_loc',
        'video:title',
        'video:description',
    ];

    private static $requiredEitherFields = [
        'video:content_loc',
        'video:player_loc',
    ];

    private static $platforms = [
        'web',
        'mobile',
        'tv',
    ];

    public static function writeVideoTag(XMLWriter $xmlWriter, string $loc, array $extFields)
    {
        self::validate($loc, $extFields);

        $xmlWriter->startElement('video:video');
        $xmlWriter->writeElement('video:thumbnail_loc', $extFields['video:thumbnail_loc']);
        $xmlWriter->writeElement('video:title', htmlentities($extFields['video:title'], ENT_QUOTES));
        $xmlWriter->writeElement('video:description', htmlentities($extFields['video:description'], ENT_QUOTES));

        if (isset($extFields['video:content_loc'])) {
            $xmlWriter->writeElement('video:content_loc', $extFields['video:content_loc']);
        }
        if (isset($extFields['video:player_loc'])) {
            $xmlWriter->writeElement('video:content_loc', $extFields['video:player_loc']);
        }
        if (isset($extFields['video:duration'])) {
            $xmlWriter->writeElement('video:duration', $extFields['video:duration']);
        }
        if (isset($extFields['video:expiration_date'])) {
            $xmlWriter->writeElement('video:expiration_date', $extFields['video:expiration_date']);
        }
        if (isset($extFields['video:rating'])) {
            $xmlWriter->writeElement('video:rating', $extFields['video:rating']);
        }
        if (isset($extFields['video:view_count'])) {
            $xmlWriter->writeElement('video:view_count', $extFields['video:view_count']);
        }
        if (isset($extFields['video:publication_date'])) {
            $xmlWriter->writeElement('video:publication_date', $extFields['video:publication_date']);
        }
        if (isset($extFields['video:family_friendly'])) {
            $xmlWriter->writeElement('video:family_friendly', $extFields['video:family_friendly']);
        }
        if (isset($extFields['video:restriction'])) {
            $xmlWriter->startElement('video:restriction');
            if (isset($extFields['video:restriction']['relationship'])) {
                $xmlWriter->writeAttribute('relationship', $extFields['video:restriction']['relationship']);
            }
            $xmlWriter->writeRaw($extFields['video:restriction']['value']);
            $xmlWriter->endElement();
        }
        if (isset($extFields['video:platform'])) {
            $xmlWriter->startElement('video:platform');
            if (isset($extFields['video:platform']['relationship'])) {
                $xmlWriter->writeAttribute('relationship', $extFields['video:platform']['relationship']);
            }
            $xmlWriter->writeRaw($extFields['video:platform']['value']);
            $xmlWriter->endElement();
        }
        if (isset($extFields['video:price'])) {
            foreach ($extFields['video:price'] as $price) {
                $xmlWriter->startElement('video:price');
                $xmlWriter->writeAttribute('currency', $price['currency']);
                if (isset($price['type'])) {
                    $xmlWriter->writeAttribute('type', $price['type']);
                }
                if (isset($price['resolution'])) {
                    $xmlWriter->writeAttribute('resolution', $price['resolution']);
                }
                $xmlWriter->writeRaw($price['value']);
                $xmlWriter->endElement();
            }
        }
        if (isset($extFields['video:requires_subscription'])) {
            $xmlWriter->writeElement('video:requires_subscription', $extFields['video:requires_subscription']);
        }
        if (isset($extFields['video:uploader'])) {
            $xmlWriter->startElement('video:uploader');
            if (isset($extFields['video:uploader']['info'])) {
                $xmlWriter->writeAttribute('info', $extFields['video:uploader']['info']);
            }
            $xmlWriter->writeRaw($extFields['video:uploader']['value']);
            $xmlWriter->endElement();
        }
        if (isset($extFields['video:live'])) {
            $xmlWriter->writeElement('video:live', $extFields['video:live']);
        }
        if (isset($extFields['video:tag'])) {
            foreach ($extFields['video:tag'] as $tag) {
                $xmlWriter->writeElement('video:tag', $tag);
            }
        }
        if (isset($extFields['video:category'])) {
            $xmlWriter->writeElement('video:category', $extFields['video:category']);
        }

        $xmlWriter->endElement();
    }

    public static function validate($loc, $extFields)
    {
        $extFieldNames = array_keys($extFields);

        if (count(array_intersect(self::$requiredFields, $extFieldNames)) !== count(self::$requiredFields)) {
            throw new InvalidArgumentException(
                sprintf("Missing required fields: %s", implode(', ', array_diff(self::$requiredFields, $extFieldNames)))
            );
        }
        if (count(array_intersect(self::$requiredEitherFields, $extFieldNames)) < 1) {
            throw new InvalidArgumentException(
                sprintf("At least one of the following values are required but missing: %s",
                    implode(', ', self::$requiredEitherFields)
                )
            );
        }
        if (mb_strlen($extFields['video:description']) > 2048) {
            throw new InvalidArgumentException('The field video:description must be less than or equal to a 2048.');
        }
        if (isset($extFields['video:content_loc']) && $extFields['video:content_loc'] === $loc) {
            throw new InvalidArgumentException('The field video:content_loc must not be the same as the <loc> URL.');
        }
        if (isset($extFields['video:player_loc']) && $extFields['video:player_loc'] === $loc) {
            throw new InvalidArgumentException('The field video:player_loc must not be the same as the <loc> URL.');
        }
        if (isset($extFields['video:duration']) && !(1 <= $extFields['video:duration'] && $extFields['video:duration'] <= 28800)) {
            throw new InvalidArgumentException('The video:duration value should be between 1 and 28800');
        }
        if (isset($extFields['video:expiration_date'])
            && DateTime::createFromFormat(DateTime::ATOM, $extFields['video:expiration_date']) === false
            && DateTime::createFromFormat('Y-m-d', $extFields['video:expiration_date']) === false
        ) {
            throw new InvalidArgumentException('Invalid video:expiration_date value. ' .
                'Supported values are complete date (YYYY-MM-DD) or complete date plus hours, ' .
                'minutes and seconds, and timezone (YYYY-MM-DDThh:mm:ss+TZD)');
        }
        if (isset($extFields['video:rating']) && !in_array($extFields['video:rating'], range(0.0, 5.0, 0.1))) {
            throw new InvalidArgumentException(
                'Invalid video:rating value. ' .
                'Supported values are float numbers in the range 0.0 (low) to 5.0 (high), inclusive.'
            );
        }
        if (isset($extFields['video:publication_date'])
            && DateTime::createFromFormat(DateTime::ATOM, $extFields['video:publication_date']) === false
            && DateTime::createFromFormat('Y-m-d', $extFields['video:publication_date']) === false
        ) {
            throw new InvalidArgumentException('Invalid video:publication_date value. ' .
                'Supported values are complete date (YYYY-MM-DD) or complete date plus hours, ' .
                'minutes and seconds, and timezone (YYYY-MM-DDThh:mm:ss+TZD)');
        }
        if (isset($extFields['video:family_friendly']) && !in_array($extFields['video:family_friendly'], ['yes', 'no'])) {
            throw new InvalidArgumentException('Invalid video:family_friendly value. ' .
                'yes (or omitted) if the video can be available with SafeSearch on. ' .
                'no if the video should be available only with SafeSearch off.');
        }
        if (isset($extFields['video:restriction'])) {
            if (isset($extFields['video:restriction']['relationship'])
                && !in_array($extFields['video:restriction']['relationship'], ['allow', 'deny'])) {
                throw new InvalidArgumentException('Invalid video:restriction.relationship value. Allowed values are allow or deny.');
            }
            if (!isset($extFields['video:restriction']['value'])) { // todo: country codes in ISO 3166 format
                throw new InvalidArgumentException('Value video:restriction.value is required');
            }
        }
        if (isset($extFields['video:platform'])) {
            if (isset($extFields['video:platform']['relationship'])
                && !in_array($extFields['video:platform']['relationship'], ['allow', 'deny'])) {
                throw new InvalidArgumentException('Invalid video:platform.relationship value. Allowed values are allow or deny.');
            }
            if (!isset($extFields['video:platform']['value'])) {
                throw new InvalidArgumentException('Value video:platform.value is required');
            }

            $platformValues = explode(' ', $extFields['video:platform']['value']);

            if (count(array_diff($platformValues, static::$platforms)) > 0) {
                throw new InvalidArgumentException(
                    'Invalid video:platform.relationship value. ' .
                    'Expecting a list of space-delimited platform types: ' .
                    implode(', ', self::$platforms)
                );
            }
        }
        if (isset($extFields['video:price'])) {
            foreach ($extFields['video:price'] as $price) {
                if (!isset($price['currency'])) {
                    throw new InvalidArgumentException('Value video:price.currency is required');
                }
                if (!isset($price['value'])) {
                    throw new InvalidArgumentException('Value video:price.value is required');
                }
                if ($price['value'] <= 0 || is_float($price['value']) === false) {
                    throw new InvalidArgumentException('Value video:price.value should be a float value more than 0');
                }
                if (isset($price['type']) && !in_array($price['type'], ['rent', 'own'])) {
                    throw new InvalidArgumentException(
                        'Invalid video:price.type value. Allowed values are rent or own.'
                    );
                }
                if (isset($price['resolution']) && !in_array($price['resolution'], ['hd', 'sd'])) {
                    throw new InvalidArgumentException(
                        'Invalid video:price.resolution value. Allowed values are hd or sd.'
                    );
                }
            }
        }
        if (isset($extFields['video:requires_subscription']) && !in_array($extFields['video:requires_subscription'], ['yes', 'no'])) {
            throw new InvalidArgumentException(
                'Invalid video:requires_subscription value. Allowed values are yes or no.'
            );
        }
        if (isset($extFields['video:uploader'])) {
            if (mb_strlen($extFields['video:uploader']['value']) > 255) {
                throw new InvalidArgumentException(
                    'Value video:uploader.value is too large, max 255 characters.'
                );
            }
            if (isset($extFields['video:uploader']['info'])) {
                $locDomain = parse_url($loc, PHP_URL_HOST);
                $infoDomain = parse_url($extFields['video:uploader']['info'], PHP_URL_HOST);
                if ($locDomain !== $infoDomain) {
                    throw new InvalidArgumentException(
                        'The video:uploader.info must be in the same domain as the <loc> tag.'
                    );
                }
            }
        }
        if (isset($extFields['video:live']) && !in_array($extFields['video:live'], ['yes', 'no'])) {
            throw new InvalidArgumentException(
                'Invalid video:live value. Allowed values are yes or no.'
            );
        }
        if (isset($extFields['video:tag'])) {
            if (count($extFields['video:tag']) > 32) {
                throw new InvalidArgumentException(
                    'The array video:tag is too large, max 32 tags.'
                );
            }
        }
        if (isset($extFields['video:category']) && mb_strlen($extFields['video:category']) > 256) {
            throw new InvalidArgumentException(
                'Value video:category is too large, max 256 characters.'
            );
        }
    }
}