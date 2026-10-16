<?php

namespace Sheadawson\Linkable\Extensions;

use SilverStripe\Core\Extension;
use Sheadawson\Linkable\Models\Link;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;

/**
 * Class LinkableDataExtension
 * @author Ryan
 * @package Sheadawson\Linkable
 * @extends Extension<DataObject>
 */
class LinkableDataExtension extends Extension
{
    public function onBeforeDuplicate()
    {
        $owner = $this->getOwner();
        $hasOne = Config::inst()->get(get_class($owner), 'has_one');

        // loop through has_one relationships and reset any Link fields
        if ($hasOne) {
            foreach ($hasOne as $field => $fieldType) {
                if ($fieldType === Link::class) {
                    $owner->{$field . 'ID'} = 0;
                }
            }
        }
    }

}
