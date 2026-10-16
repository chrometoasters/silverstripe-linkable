<?php

namespace Sheadawson\Linkable\Forms;

use Embed\Embed;
use Sheadawson\Linkable\Models\EmbeddedObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\FormField;
use Throwable;

/**
 * Class EmbeddedObjectField
 * @license BSD License http://www.silverstripe.org/bsd-license
 * @author <marcus@silverstripe.com.au>
 * @package Sheadawson\Linkable\Forms
 */
class EmbeddedObjectField extends FormField
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'update',
    ];

    /**
     * @var bool
     */
    protected $editableEmbedCode = false;

    /**
     * @var mixed
     */
    protected $object;

    /**
     * @var string
     */
    protected $message;

    /**
     * @param mixed $value
     * @param null $data
     * @return $this|void
     */
    public function setValue($value, $data = null)
    {
        if ($value instanceof EmbeddedObject) {
            $this->object = $value;

            return parent::setValue($value->toMap(), $data);
        }

        return parent::setValue($value, $data);
    }

    /**
     * @param $code
     * @return $this
     */
    public function setEditableEmbedCode($code)
    {
        $this->editableEmbedCode = $code;

        return $this;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param array $properties
     * @return mixed|DBHTMLText
     */
    public function FieldHolder($properties = [])
    {
        Requirements::css('sheadawson/silverstripe-linkable: client/dist/styles/bundle.css');
        Requirements::javascript('sheadawson/silverstripe-linkable: client/dist/js/bundle.js');

        if ($this->object && $this->object->ID) {
            $properties['SourceURL'] = TextField::create($this->getName() . '[sourceurl]', '')
                ->setAttribute('placeholder', _t('Linkable.SOURCEURL', 'Source URL'));

            if (strlen($this->object->SourceURL ?? '')) {
                $properties['ObjectTitle'] = TextField::create(
                    $this->getName() . '[title]', _t('Linkable.TITLE', 'Title')
                );
                $properties['Width'] = TextField::create(
                    $this->getName() . '[width]', _t('Linkable.WIDTH', 'Width')
                );
                $properties['Height'] = TextField::create(
                    $this->getName() . '[height]', _t('Linkable.HEIGHT', 'Height')
                );
                $properties['ThumbURL'] = HiddenField::create($this->getName() . '[thumburl]', '');
                $properties['Type'] = HiddenField::create($this->getName() . '[type]', '');

                if ($this->editableEmbedCode) {
                    $properties['EmbedHTML'] = TextareaField::create(
                        $this->getName() . '[embedhtml]', 'Embed code'
                    );
                } else {
                    $properties['EmbedHTML'] = HiddenField::create($this->getName() . '[embedhtml]', '');
                }

                $properties['ObjectDescription'] = TextareaField::create(
                    $this->getName() . '[description]', _t('Linkable.DESCRIPTION', 'Description')
                );
                $properties['ExtraClass'] = TextField::create(
                    $this->getName() . '[extraclass]', _t('Linkable.CSSCLASS', 'CSS class')
                );

                foreach ($properties as $key => $field) {
                    if ($key == 'ObjectTitle') {
                        $key = 'Title';
                    } elseif ($key == 'ObjectDescription') {
                        $key = 'Description';
                    }

                    $field->setValue($this->object->$key);
                }

                if ($this->object->ThumbURL) {
                    $properties['ThumbImage'] = LiteralField::create(
                        $this->getName(), '<img src="' . Convert::raw2att($this->object->ThumbURL) . '" />'
                    );
                }
            }
        } else {
            $properties['SourceURL'] = TextField::create($this->getName() . '[sourceurl]', '')
                ->setAttribute('placeholder', _t('Linkable.SOURCEURL', 'Source URL'));
        }

        $field = parent::FieldHolder($properties);

        return $field;
    }

    /**
     * @param DataObjectInterface $record
     */
    public function saveInto(DataObjectInterface $record)
    {
        $val = $this->getValue();
        $field = $this->getName() . 'ID';

        if (!strlen($val['sourceurl'] ?? '') && $this->object) {
            if ($this->object->exists()) {
                $this->object->delete();
            }

            $record->$field = 0;

            return;
        }

        if (!$this->object) {
            $this->object = EmbeddedObject::create();
        }

        $props = array_keys(Config::inst()->get(EmbeddedObject::class, 'db'));

        foreach ($props as $prop) {
            $this->object->$prop = isset($val[strtolower($prop)]) ? $val[strtolower($prop)] : null;
        }

        $this->object->write();
        $record->$field = $this->object->ID;
    }

    /**
     * @param HTTPRequest $request
     * @return mixed|DBHTMLText|string
     */
    public function update(HTTPRequest $request)
    {
        if (!SecurityToken::inst()->checkRequest($request)) {
            return '';
        }

        $url = $request->postVar('URL') ?? '';

        if (strlen($url)) {
            // embed/embed 4 never returns a falsy result: it throws on network errors and extracts
            // from error pages, so both count as a failed lookup
            try {
                $info = Injector::inst()->get(Embed::class)->get($url);

                if ($info->getResponse()->getStatusCode() >= 400) {
                    $info = null;
                }
            } catch (Throwable $e) {
                $info = null;
            }

            if ($info) {
                $object = EmbeddedObject::create();
                $object->setFromEmbed($info);

                $this->object = $object;
                // needed to make sure the check in FieldHolder works out
                $object->ID = -1;

                return $this->FieldHolder();
            } else {
                $this->message = _t(
                    'EmbeddedObjectField.ERROR', 'Could not look up provided URL: ' . Convert::raw2xml($url)
                );

                return $this->FieldHolder();
            }
        } else {
            $this->object = null;

            return $this->FieldHolder();
        }
    }
}
