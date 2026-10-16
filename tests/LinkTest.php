<?php

namespace Sheadawson\Linkable\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Sheadawson\Linkable\Models\Link;
use Sheadawson\Linkable\Tests\Stubs\LinkableTestPage;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Assets\File;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\DropdownField;
use SilverStripe\TemplateEngine\SSTemplateEngine;
use SilverStripe\View\ViewLayerData;

/**
 * Covers the Link model on CMS 6: URL resolution per type, rendering, validation and the CMS fields.
 */
class LinkTest extends SapphireTest
{
    /**
     * @var string
     */
    protected static $fixture_file = 'LinkTest.yml';

    /**
     * @var array
     */
    protected static $extra_dataobjects = [
        LinkableTestPage::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        TestAssetStore::activate('LinkTest');
        $file = $this->objFromFixture(File::class, 'brochure');
        $file->setFromString('PDF placeholder', 'brochure.pdf');
        $file->write();
        $file->publishSingle();
    }

    protected function tearDown(): void
    {
        TestAssetStore::reset();

        parent::tearDown();
    }

    public function testLinkURLForEachType(): void
    {
        $this->assertSame(
            'https://example.com/path?a=1&b=2',
            $this->objFromFixture(Link::class, 'url')->getLinkURL()
        );
        $this->assertSame('mailto:hello@example.com', $this->objFromFixture(Link::class, 'email')->getLinkURL());
        $this->assertSame('tel:+64 4 123 4567', $this->objFromFixture(Link::class, 'phone')->getLinkURL());
        $this->assertStringEndsWith('brochure.pdf', $this->objFromFixture(Link::class, 'file')->getLinkURL());

        $pageURL = $this->objFromFixture(Link::class, 'page')->getLinkURL();
        $this->assertStringContainsString('about-us', $pageURL);
        $this->assertStringEndsWith('#team', $pageURL);
    }

    public function testUnsavedLinkHasNoURL(): void
    {
        $link = Link::create(['Type' => 'URL', 'URL' => 'https://example.com']);

        $this->assertSame('', $link->getLinkURL());
        $this->assertSame('', $link->forTemplate());
    }

    public function testLinkToMissingObjectHasNoURL(): void
    {
        $link = $this->objFromFixture(Link::class, 'page');
        $link->SiteTreeID = 999999;

        $this->assertFalse($link->getLinkURL());
        $this->assertSame('', $link->forTemplate());
    }

    public function testForTemplateRendersAnchor(): void
    {
        $link = $this->objFromFixture(Link::class, 'url');
        $link->setCSSClass('button primary');

        $this->assertSame(
            '<a href="https://example.com/path?a=1&amp;b=2" target=\'_blank\' class=\'button primary\'>Example</a>',
            trim($link->forTemplate())
        );

        $email = $this->objFromFixture(Link::class, 'email');
        $this->assertSame('<a href="mailto:hello@example.com">Email us</a>', trim($email->forTemplate()));
    }

    public function testTemplateGettersUsedByConsumers(): void
    {
        $link = $this->objFromFixture(Link::class, 'url');
        $output = $this->renderString(
            '$Link|$Link.LinkURL|<% if $Link.OpenInNewWindow %>new<% end_if %>|$Link.Type|$Link.Title|$Link.LinkType',
            ['Link' => $link]
        );

        $this->assertSame(
            '<a href="https://example.com/path?a=1&amp;b=2" target=\'_blank\'>Example</a>'
            . '|https://example.com/path?a=1&amp;b=2|new|URL|Example|URL',
            trim(preg_replace('/\s*\|\s*/', '|', $output))
        );
    }

    public function testSetCSSClassFromTemplate(): void
    {
        $output = trim($this->renderString(
            "\$Link.setCSSClass('b-link b-data-block__more-link')",
            ['Link' => $this->objFromFixture(Link::class, 'email')]
        ));

        $this->assertSame(
            '<a href="mailto:hello@example.com" class=\'b-link b-data-block__more-link\'>Email us</a>',
            $output
        );
    }

    public function testFileGettersFromTemplate(): void
    {
        $output = trim($this->renderString(
            '<% if $Link.Type == "File" %>$Link.File.Extension.UpperCase<% end_if %>',
            ['Link' => $this->objFromFixture(Link::class, 'file')]
        ));

        $this->assertSame('PDF', $output);
    }

    public function testExistsIsFalseForEmptyHasOne(): void
    {
        $page = $this->objFromFixture(LinkableTestPage::class, 'about');
        $this->assertSame(
            'none',
            trim($this->renderString('<% if $CallToAction %>has<% else %>none<% end_if %>', $page))
        );
    }

    public function testTitleIsGeneratedOnWrite(): void
    {
        $link = Link::create(['Type' => 'URL', 'URL' => 'https://example.org']);
        $link->write();
        $this->assertSame('https://example.org', $link->Title);

        $pageLink = Link::create([
            'Type' => 'SiteTree',
            'SiteTreeID' => $this->idFromFixture(LinkableTestPage::class, 'about'),
        ]);
        $pageLink->write();
        $this->assertSame('About', $pageLink->Title);

        $fileLink = Link::create(['Type' => 'File', 'FileID' => $this->idFromFixture(File::class, 'brochure')]);
        $fileLink->write();
        $this->assertSame('Brochure', $fileLink->Title);
    }

    public static function provideValidation(): array
    {
        return [
            'valid absolute URL' => [['Type' => 'URL', 'URL' => 'https://example.com'], true],
            'valid root-relative URL' => [['Type' => 'URL', 'URL' => '/contact'], true],
            'valid anchor' => [['Type' => 'URL', 'URL' => '#top'], true],
            'invalid URL' => [['Type' => 'URL', 'URL' => 'example dot com'], false],
            'javascript: URL' => [['Type' => 'URL', 'URL' => 'javascript:alert(1)'], false],
            'empty URL' => [['Type' => 'URL', 'URL' => ''], false],
            'valid email' => [['Type' => 'Email', 'Email' => 'a@example.com'], true],
            'invalid email' => [['Type' => 'Email', 'Email' => 'not-an-email'], false],
            'valid phone' => [['Type' => 'Phone', 'Phone' => '04 123 4567'], true],
            'emergency number' => [['Type' => 'Phone', 'Phone' => '111'], true],
            'invalid phone' => [['Type' => 'Phone', 'Phone' => 'call me'], false],
            'page not chosen' => [['Type' => 'SiteTree', 'SiteTreeID' => 0], false],
            'file not chosen' => [['Type' => 'File', 'FileID' => 0], false],
            'no type' => [[], true],
            'URL longer than its Varchar(255) column' => [
                ['Type' => 'URL', 'URL' => 'https://example.com/' . str_repeat('a', 300)],
                false,
            ],
        ];
    }

    #[DataProvider('provideValidation')]
    public function testValidate(array $data, bool $expectedValid): void
    {
        $result = Link::create($data)->validate();

        $this->assertSame($expectedValid, $result->isValid(), print_r($result->getMessages(), true));
    }

    public function testGetTypesHonoursConfigAndOverride(): void
    {
        // LinkableSiteTreeExtension's SiteTree type merges in ahead of the class's own types
        $this->assertSame(
            ['SiteTree', 'URL', 'Email', 'Phone', 'File'],
            array_keys(Link::singleton()->getTypes())
        );

        Link::config()->set('allowed_types', ['URL', 'SiteTree']);
        $this->assertSame(['SiteTree', 'URL'], array_keys(Link::create()->getTypes()));

        $this->assertSame(['Email'], array_keys(Link::create()->setAllowedTypes(['Email'])->getTypes()));
    }

    public function testCMSFields(): void
    {
        $fields = Link::create()->setAllowedTypes(['URL', 'SiteTree'])->getCMSFields();

        foreach (['Title', 'Type', 'URL', 'FileID', 'Email', 'Phone', 'OpenInNewWindow', 'SiteTreeID', 'Anchor'] as $name) {
            $this->assertNotNull($fields->dataFieldByName($name), "$name field is missing");
        }

        $this->assertNotNull($fields->findTab('Root.Main'), 'Extensions add fields to Root.Main');

        /** @var DropdownField $type */
        $type = $fields->dataFieldByName('Type');
        $this->assertSame(['SiteTree', 'URL'], array_keys($type->getSource()));
        $this->assertNull($fields->dataFieldByName('Template'), 'Template only shows when templates are configured');

        Link::config()->set('templates', ['button' => 'Button']);
        $this->assertNotNull(Link::create()->getCMSFields()->dataFieldByName('Template'));
    }

    public function testDuplicateResetsLinkRelation(): void
    {
        $page = $this->objFromFixture(LinkableTestPage::class, 'about');
        $page->CallToActionID = $this->idFromFixture(Link::class, 'url');
        $page->write();

        $copy = $page->duplicate();

        $this->assertSame(0, (int) $copy->CallToActionID);
        $this->assertSame($this->idFromFixture(Link::class, 'url'), (int) $page->CallToActionID);
    }

    /**
     * Renders a template string the way a theme template would (CMS 6 removed SSViewer::fromString())
     */
    private function renderString(string $template, mixed $data): string
    {
        return SSTemplateEngine::create()->renderString($template, ViewLayerData::create($data));
    }
}
