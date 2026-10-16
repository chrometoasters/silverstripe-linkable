<?php

namespace Sheadawson\Linkable\Tests\Stubs;

use Sheadawson\Linkable\Forms\EmbeddedObjectField;
use Sheadawson\Linkable\Forms\LinkField;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;

/**
 * Serves a form holding a LinkField and an EmbeddedObjectField so the fields' dialog actions can be
 * requested over HTTP the way the admin JavaScript requests them.
 */
class LinkFieldTestController extends Controller implements TestOnly
{
    /**
     * @var string
     */
    private static $url_segment = 'LinkFieldTestController';

    /**
     * @var array
     */
    private static $allowed_actions = [
        'Form',
    ];

    /**
     * @return Form
     */
    public function Form()
    {
        return Form::create(
            $this,
            'Form',
            FieldList::create(
                LinkField::create('MyLinkID', 'My link')->setAllowedTypes(['URL', 'Email']),
                EmbeddedObjectField::create('Embed', 'Embed')
            ),
            FieldList::create(FormAction::create('doSubmit', 'Submit'))
        );
    }

    /**
     * @return string
     */
    public function index()
    {
        return $this->Form()->forTemplate();
    }
}
