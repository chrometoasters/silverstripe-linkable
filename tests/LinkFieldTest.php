<?php

namespace Sheadawson\Linkable\Tests;

use Sheadawson\Linkable\Forms\LinkField;
use Sheadawson\Linkable\Models\Link;
use Sheadawson\Linkable\Tests\Stubs\LinkFieldTestController;
use Sheadawson\Linkable\Tests\Stubs\LinkFormTestExtension;
use SilverStripe\Control\Director;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\View\Requirements;

/**
 * Renders LinkField and EmbeddedObjectField on CMS 6 and drives the LinkField dialog actions over HTTP
 * (LinkFormHTML, the LinkForm doSaveLink submission and doRemoveLink) the way the admin JavaScript does.
 */
class LinkFieldTest extends FunctionalTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    /**
     * @var array
     */
    protected static $required_extensions = [
        LinkField::class => [
            LinkFormTestExtension::class,
        ],
    ];

    /**
     * @var string
     */
    private const FIELD_URL = 'LinkFieldTestController/Form/field/MyLinkID';

    protected function setUp(): void
    {
        parent::setUp();

        // Ahead of the CMS's catch-all $URLSegment rule, which would otherwise answer with a 404 page
        Director::config()->set('rules', array_merge(
            ['LinkFieldTestController//$Action/$ID/$OtherID' => LinkFieldTestController::class],
            Director::config()->get('rules')
        ));
    }

    public function testFormRendersFieldMarkupAndRequirements(): void
    {
        Requirements::clear();
        $html = (string) LinkFieldTestController::create()->Form()->forTemplate();

        // The hidden input the dialog script binds to (input.link), and the dialog's buttons and container
        $this->assertMatchesRegularExpression('/<input[^>]+name="MyLinkID"[^>]+class="[^"]*\blink\b/', $html);
        $this->assertStringContainsString("class='linkfield-button btn btn-primary font-icon-plus-circled '", $html);
        $this->assertStringContainsString("<div class='linkfield-dialog'></div>", $html);

        // EmbeddedObjectField: the inspect button and its source URL input
        $this->assertStringContainsString('class="embeddedObjectLoad', $html);
        $this->assertStringContainsString('data-href="LinkFieldTestController/Form/field/Embed/update"', $html);
        $this->assertStringContainsString('name="Embed[sourceurl]"', $html);

        $javascript = implode(' ', array_keys(Requirements::backend()->getJavascript()));
        $css = implode(' ', array_keys(Requirements::backend()->getCSS()));
        $this->assertStringContainsString('silverstripe-linkable/client/dist/js/bundle.js', $javascript);
        $this->assertStringContainsString('silverstripe-linkable/client/dist/styles/bundle.css', $css);
    }

    public function testFieldWithLinkRendersLinkAndEditButtons(): void
    {
        $link = Link::create(['Title' => 'Example', 'Type' => 'URL', 'URL' => 'https://example.com']);
        $link->write();

        $form = LinkFieldTestController::create()->Form();
        $form->Fields()->dataFieldByName('MyLinkID')->setValue($link->ID);
        $html = (string) $form->forTemplate();

        $this->assertStringContainsString('<a href="https://example.com">Example</a>', $html);
        $this->assertStringContainsString('linkfield-remove-button', $html);
        $this->assertStringNotContainsString('font-icon-plus-circled', $html);
    }

    public function testLinkFormHTMLForNewLink(): void
    {
        $response = $this->get(self::FIELD_URL . '/LinkFormHTML?LinkID=0');

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('Add Link', $body);
        $this->assertStringContainsString('name="action_doSaveLink"', $body);
        $this->assertStringContainsString('<option value="URL"', $body);
        $this->assertStringContainsString('<option value="Email"', $body);
        $this->assertStringNotContainsString('<option value="Phone"', $body, 'setAllowedTypes() limits the types');
        $this->assertStringContainsString('link-form-extended', $body, 'updateLinkForm is called');
    }

    public function testLinkFormHTMLForExistingLink(): void
    {
        $link = Link::create(['Title' => 'Existing', 'Type' => 'URL', 'URL' => 'https://example.com/existing']);
        $link->write();

        $response = $this->get(self::FIELD_URL . '/LinkFormHTML?LinkID=' . $link->ID);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('Edit Link', $body);
        $this->assertStringContainsString('value="https://example.com/existing"', $body);
        $this->assertMatchesRegularExpression('/name="LinkID"[^>]*value="' . $link->ID . '"/', $body);
    }

    public function testSaveLinkCreatesLinkAndReturnsField(): void
    {
        $this->get(self::FIELD_URL . '/LinkFormHTML?LinkID=0');

        $response = $this->post($this->linkFormAction(), [
            'Title' => 'New link',
            'Type' => 'URL',
            'URL' => 'https://example.com/new',
            'LinkID' => 0,
            'action_doSaveLink' => 'Save',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $link = Link::get()->filter('URL', 'https://example.com/new')->first();
        $this->assertNotNull($link, 'The dialog saved a Link');
        $this->assertSame('New link', $link->Title);

        // The response is the field holder the script swaps into the page, now holding the link's ID
        $body = $response->getBody();
        $this->assertMatchesRegularExpression('/<input[^>]+value="' . $link->ID . '"/', $body);
        $this->assertStringContainsString('<a href="https://example.com/new">New link</a>', $body);
    }

    public function testSaveLinkShowsValidationMessage(): void
    {
        $this->get(self::FIELD_URL . '/LinkFormHTML?LinkID=0');

        $response = $this->post($this->linkFormAction(), [
            'Type' => 'URL',
            'URL' => 'not a url',
            'LinkID' => 0,
            'action_doSaveLink' => 'Save',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Please enter a valid URL', $response->getBody());
        $this->assertSame(0, Link::get()->count());
    }

    public function testRemoveLinkReturnsEmptyField(): void
    {
        $response = $this->get(self::FIELD_URL . '/doRemoveLink');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('font-icon-plus-circled', $response->getBody());
        $this->assertStringNotContainsString('linkfield-remove-button', $response->getBody());
    }

    /**
     * The action of the LinkForm in the last response. The test posts to it directly, as the dialog's
     * ajaxSubmit() does: TestSession::submitForm() resolves the relative action against the current URL.
     *
     * @return string
     */
    private function linkFormAction(): string
    {
        // Matched with a pattern: the dialog fragment is not a document the CSS parser accepts without tidy
        $matched = preg_match_all('/<form[^>]+action="([^"]+)"/', $this->content(), $matches);
        $this->assertSame(1, $matched, 'LinkFormHTML returns one form');
        $this->assertSame(self::FIELD_URL . '/LinkForm', $matches[1][0]);

        return $matches[1][0];
    }
}
