<?php

namespace Sheadawson\Linkable\Models;

use Embed\Embed;
use Embed\Extractor;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;

/**
 * Class EmbeddedObject
 * @license BSD License http://www.silverstripe.org/bsd-license
 * @author <marcus@silverstripe.com.au>
 * @property string Title
 * @property string Type
 * @property string SourceURL
 * @property string Width
 * @property string Height
 * @property string Description
 * @property string ThumbURL
 * @property string ExtraClass
 * @property string EmbedHTML
 * @package Sheadawson\Linkable\Models
 */
class EmbeddedObject extends DataObject
{
    /**
     * @var array
     */
    private static $db = [
        'Title' => 'Varchar(255)',
        'Type' => 'Varchar',
        'SourceURL' => 'Varchar(255)',
        'Width' => 'Varchar',
        'Height' => 'Varchar',
        'Description' => 'HTMLText',
        'ThumbURL' => 'Varchar(255)',
        'ExtraClass' => 'Varchar(64)',
        'EmbedHTML' => 'Text',
    ];

    /**
     * @var string
     */
    private static $table_name = 'LinkableEmbed';

    /**
     * @return $this
     */
    public function Embed()
    {
        $this->setFromURL($this->SourceURL);

        return $this;
    }

    public function onBeforeWrite()
    {
        $changes = $this->getChangedFields();

        if (isset($changes['SourceURL']) && $changes['SourceURL']['after']) {
            $this->updateEmbedHTML();
        }

        parent::onBeforeWrite();
    }

    public function updateEmbedHTML()
    {
        $this->setFromURL($this->SourceURL);
    }

    /**
     * @param $url
     */
    public function setFromURL($url)
    {
        if ($url) {
            // array('image' => array('minImageWidth' => $this->Width, 'minImageHeight' => $this->Height)));
            // Fetched through the Injector so tests and projects can supply a configured client
            $info = Injector::inst()->get(Embed::class)->get($url);

            $this->setFromEmbed($info);
        }
    }

    /**
     * Copies the details embed/embed 4 extracted from a URL onto this record.
     *
     * Image and code are null when the page has no thumbnail or embed code, and embed/embed 4
     * only has lowercase detectors (reading $info->Title throws), so every read is null-safe.
     *
     * @param Extractor $info
     */
    public function setFromEmbed($info)
    {
        $this->Title = $info->title;
        $this->SourceURL = (string) $info->url;
        // Width and Height are Varchar columns and CMS 6 validates field types on write, so the
        // integers embed/embed returns are stored as strings
        $this->Width = isset($info->code?->width) ? (string) $info->code->width : null;
        $this->Height = isset($info->code?->height) ? (string) $info->code->height : null;
        $this->ThumbURL = $info->image ? (string) $info->image : null;
        $this->Description = $info->description ? $info->description : $info->title;
        $this->Type = $info->getOEmbed()->str('type');
        $embed = $info->code?->html;
        $this->EmbedHTML = $embed ? $embed : $this->EmbedHTML;
    }

    /**
     * Renders the embed. EmbedHTML is output as stored (it is the provider's embed code); the
     * other values are escaped for the attribute or text position they are written into.
     *
     * @return string
     */
    public function forTemplate(): string
    {
        $extraClass = Convert::raw2att($this->ExtraClass ?? '');
        $sourceURL = Convert::raw2att($this->SourceURL ?? '');

        switch ($this->Type) {
            case 'video':
            case 'rich':
                if ($this->ExtraClass) {
                    return "<div class='$extraClass'>" . ($this->EmbedHTML ?? '') . "</div>";
                } else {
                    return $this->EmbedHTML ?? '';
                }
            case 'link':
                return '<a class="' . $extraClass . '" href="' . $sourceURL . '">'
                    . Convert::raw2xml($this->Title ?? '') . '</a>';
            case 'photo':
                $width = Convert::raw2att($this->Width ?? '');
                $height = Convert::raw2att($this->Height ?? '');

                return "<img src='$sourceURL' width='$width' height='$height' class='$extraClass' />";
        }

        return '';
    }
}
