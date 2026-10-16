<?php

namespace Sheadawson\Linkable\Tests\Stubs;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\LiteralField;

/**
 * Proves LinkField::LinkForm() still calls the updateLinkForm hook.
 *
 * @extends Extension<\Sheadawson\Linkable\Forms\LinkField>
 */
class LinkFormTestExtension extends Extension implements TestOnly
{
    protected function updateLinkForm(Form $form): void
    {
        $form->Fields()->push(LiteralField::create('LinkFormExtended', '<p class="link-form-extended"></p>'));
    }
}
