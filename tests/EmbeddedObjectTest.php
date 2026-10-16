<?php

namespace Sheadawson\Linkable\Tests;

use Embed\Embed;
use Embed\Http\Crawler;
use Sheadawson\Linkable\Models\EmbeddedObject;
use Sheadawson\Linkable\Tests\Stubs\LinkFieldTestController;
use Sheadawson\Linkable\Tests\Stubs\StubHttpClient;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;

/**
 * Covers EmbeddedObject and EmbeddedObjectField against embed/embed 4, with a stub HTTP client so
 * nothing leaves the machine.
 */
class EmbeddedObjectTest extends FunctionalTest
{
    /**
     * @var bool
     */
    protected $usesDatabase = true;

    private StubHttpClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Ahead of the CMS's catch-all $URLSegment rule, which would otherwise answer with a 404 page
        Director::config()->set('rules', array_merge(
            ['LinkFieldTestController//$Action/$ID/$OtherID' => LinkFieldTestController::class],
            Director::config()->get('rules')
        ));

        $this->client = new StubHttpClient([
            // A plain page: a title and a description, no image and no embed code
            '/plain' => ['text/html', '<html><head><title>Plain page</title></head><body><p>Hi</p></body></html>'],
            // A page that advertises an oEmbed endpoint, as video providers do
            '/video' => [
                'text/html',
                '<html><head><title>Example video</title>'
                . '<meta property="og:description" content="A short film">'
                . '<link rel="alternate" type="application/json+oembed"'
                . ' href="https://media.example.com/oembed?format=json&amp;url=https%3A%2F%2Fmedia.example.com%2Fvideo">'
                . '</head><body></body></html>',
            ],
            '/oembed' => [
                'application/json',
                json_encode([
                    'version' => '1.0',
                    'type' => 'video',
                    'title' => 'Example video',
                    'html' => '<iframe src="https://media.example.com/player/1"></iframe>',
                    'width' => 640,
                    'height' => 360,
                    'thumbnail_url' => 'https://media.example.com/thumb.jpg',
                ]),
            ],
        ]);

        Injector::inst()->registerService(new Embed(new Crawler($this->client)), Embed::class);
    }

    public function testSetFromURLWithoutImageOrCode(): void
    {
        $object = EmbeddedObject::create();
        $object->setFromURL('https://media.example.com/plain');

        $this->assertSame('Plain page', $object->Title);
        $this->assertSame('https://media.example.com/plain', $object->SourceURL);
        $this->assertNull($object->ThumbURL);
        $this->assertNull($object->Width);
        $this->assertNull($object->EmbedHTML);
        $this->assertSame('Plain page', $object->Description, 'Falls back to the title');
    }

    public function testSetFromURLWithOEmbedVideo(): void
    {
        $object = EmbeddedObject::create();
        $object->setFromURL('https://media.example.com/video');

        $this->assertSame('Example video', $object->Title);
        $this->assertSame('video', $object->Type);
        $this->assertSame('640', (string) $object->Width);
        $this->assertSame('360', (string) $object->Height);
        $this->assertSame('https://media.example.com/thumb.jpg', $object->ThumbURL);
        $this->assertSame('<iframe src="https://media.example.com/player/1"></iframe>', $object->EmbedHTML);

        $object->ExtraClass = 'video-wrapper';
        $this->assertSame(
            "<div class='video-wrapper'><iframe src=\"https://media.example.com/player/1\"></iframe></div>",
            $object->forTemplate()
        );
    }

    public function testWriteFetchesEmbedWhenSourceURLChanges(): void
    {
        $object = EmbeddedObject::create(['SourceURL' => 'https://media.example.com/video']);
        $object->write();

        $this->assertSame('video', $object->Type);
        $this->assertContains('https://media.example.com/video', $this->client->requested);
    }

    public function testForTemplateEscapesValues(): void
    {
        $photo = EmbeddedObject::create([
            'Type' => 'photo',
            'SourceURL' => 'https://example.com/a.jpg',
            'Width' => '10',
            'Height' => '20',
            'ExtraClass' => "x' onload='alert(1)",
        ]);
        $this->assertSame(
            "<img src='https://example.com/a.jpg' width='10' height='20' class='x&#039; onload=&#039;alert(1)' />",
            $photo->forTemplate()
        );

        $link = EmbeddedObject::create(['Type' => 'link', 'SourceURL' => 'https://example.com', 'Title' => '<b>Hi</b>']);
        $this->assertSame('<a class="" href="https://example.com">&lt;b&gt;Hi&lt;/b&gt;</a>', $link->forTemplate());
    }

    public function testFieldRendersStoredObject(): void
    {
        // The first write fetches the embed; the edit after it is what the field must show
        $object = EmbeddedObject::create(['SourceURL' => 'https://media.example.com/video']);
        $object->write();
        $object->Title = 'Stored video';
        $object->write();
        $object = EmbeddedObject::get()->byID($object->ID);

        $form = LinkFieldTestController::create()->Form();
        $form->Fields()->dataFieldByName('Embed')->setValue($object);
        $html = (string) $form->forTemplate();

        $this->assertMatchesRegularExpression('/name="Embed\[title\]"[^>]*value="Stored video"/', $html);
        $this->assertStringContainsString('name="Embed[width]"', $html);
        $this->assertStringContainsString('<img src="https://media.example.com/thumb.jpg" />', $html);
    }

    public function testUpdateActionInspectsURL(): void
    {
        $response = $this->post('LinkFieldTestController/Form/field/Embed/update', [
            'URL' => 'https://media.example.com/video',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertMatchesRegularExpression('/name="Embed\[title\]"[^>]*value="Example video"/', $response->getBody());
    }

    public function testUpdateActionReportsUnreachableURL(): void
    {
        $response = $this->post('LinkFieldTestController/Form/field/Embed/update', [
            'URL' => 'not a url at all',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Could not look up provided URL', $response->getBody());
    }
}
