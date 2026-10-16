<?php

namespace Sheadawson\Linkable\Extensions;

use SilverStripe\Core\Extension;
use Sheadawson\Linkable\Models\Link;
use SilverStripe\Core\Config\Config;

/**
 * Class LinkableDataExtension
 * @author Ryan
 * @package Sheadawson\Linkable
 * @extends Extension<static>
 */
class LinkableDataExtension extends Extension
{
    public function onBeforeDuplicate()
    {
        $hasOne = Config::inst()->get(get_class($this->owner), 'has_one');

        // loop through has_one relationships and reset any Link fields
        if ($hasOne) {
            foreach ($hasOne as $field => $fieldType) {
                if ($fieldType === Link::class) {
                    $this->owner->{$field . 'ID'} = 0;
                }
            }
        }
    }

}
