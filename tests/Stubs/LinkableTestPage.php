<?php

namespace Sheadawson\Linkable\Tests\Stubs;

use Sheadawson\Linkable\Extensions\LinkableDataExtension;
use Sheadawson\Linkable\Models\Link;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * A page type with a has_one Link, used to test linking to the SiteTree and the duplicate hook.
 *
 * @property int CallToActionID
 * @method Link CallToAction()
 */
class LinkableTestPage extends SiteTree implements TestOnly
{
    /**
     * @var string
     */
    private static $table_name = 'LinkableTestPage';

    /**
     * @var array
     */
    private static $has_one = [
        'CallToAction' => Link::class,
    ];

    /**
     * @var array
     */
    private static $extensions = [
        LinkableDataExtension::class,
    ];
}
