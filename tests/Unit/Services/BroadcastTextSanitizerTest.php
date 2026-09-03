<?php

declare(strict_types=1);

namespace GeekCo\FilamentMaxBroadcasts\Tests\Unit\Services;

use GeekCo\FilamentMaxBroadcasts\Services\BroadcastTextSanitizer;
use GeekCo\FilamentMaxBroadcasts\Tests\TestCase;

class BroadcastTextSanitizerTest extends TestCase
{
    private BroadcastTextSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new BroadcastTextSanitizer();
    }

    public function testEmptyText(): void
    {
        self::assertSame('', $this->sanitizer->sanitize(''));
        self::assertSame('', $this->sanitizer->sanitize('   '));
    }

    public function testDropsScriptStyleAndIframeWithContent(): void
    {
        $html = '<script>alert(1)</script><b>ok</b><style>.x{}</style><iframe src="x"></iframe>';

        self::assertSame('<b>ok</b>', $this->sanitizer->sanitize($html));
    }

    public function testUnwrapsUnknownTags(): void
    {
        self::assertSame('text', $this->sanitizer->sanitize('<unknown>text</unknown>'));
        self::assertSame('text', $this->sanitizer->sanitize('<section>text</section>'));
    }

    public function testUnwrapsDiv(): void
    {
        self::assertSame('Hello', $this->sanitizer->sanitize('<div>Hello</div>'));
    }

    public function testKeepsWhitelistedTags(): void
    {
        $html = '<p>para</p><b>bold</b><i>italic</i><u>under</u><a href="https://example.com">link</a><blockquote>quote</blockquote>';

        self::assertSame($html, $this->sanitizer->sanitize($html));
    }

    public function testStripsAllAttributesExceptSafeHref(): void
    {
        $html = '<a href="https://example.com" onclick="x()">link</a><b class="x">bold</b>';

        self::assertSame('<a href="https://example.com">link</a><b>bold</b>', $this->sanitizer->sanitize($html));
    }

    public function testDropsUnsafeHref(): void
    {
        self::assertSame('<a>link</a>', $this->sanitizer->sanitize('<a href="javascript:alert(1)">link</a>'));
        self::assertSame('<a>link</a>', $this->sanitizer->sanitize('<a href="data:text/html;base64,x">link</a>'));
    }

    public function testAllowsMaxSchemeHref(): void
    {
        self::assertSame('<a href="max://chat/123">chat</a>', $this->sanitizer->sanitize('<a href="max://chat/123">chat</a>'));
    }

    public function testRemovesHtmlComments(): void
    {
        self::assertSame('<p>text</p>', $this->sanitizer->sanitize('<!-- comment --><p>text</p>'));
    }

    public function testRemovesNestedCommentsInsideElements(): void
    {
        self::assertSame('<p>text</p>', $this->sanitizer->sanitize('<p><!-- inner -->text</p>'));
    }

    public function testRemovesCommentThenUnwrapsUnknownElement(): void
    {
        self::assertSame('text', $this->sanitizer->sanitize('<div><!-- c -->text</div>'));
    }

    public function testSanitizeDivUnwrapsKeepingInlineFormatting(): void
    {
        self::assertSame('Hello <b>world</b>', $this->sanitizer->sanitize('<div>Hello <b>world</b></div>'));
    }

    public function testToMaxHtmlHandlesBlockquoteAsBlock(): void
    {
        self::assertSame("<blockquote>quote</blockquote>\nBody", $this->sanitizer->toMaxHtml('<blockquote>quote</blockquote><p>Body</p>'));
    }

    public function testToMaxHtmlConvertsParagraphsToNewlines(): void
    {
        self::assertSame("First\nSecond", $this->sanitizer->toMaxHtml('<p>First</p><p>Second</p>'));
    }

    public function testToMaxHtmlReplacesBreaks(): void
    {
        self::assertSame("First\nSecond", $this->sanitizer->toMaxHtml('<p>First<br>Second</p>'));
    }

    public function testToMaxHtmlLeavesHeadingFollowedByNewline(): void
    {
        self::assertSame('<h1>Title</h1>', $this->sanitizer->toMaxHtml('<h1>Title</h1>'));
        self::assertSame("<h1>Title</h1>\nBody", $this->sanitizer->toMaxHtml('<h1>Title</h1><p>Body</p>'));
    }

    public function testToMaxHtmlTrimsExcessiveNewlines(): void
    {
        self::assertSame("A\n\nB", $this->sanitizer->toMaxHtml('<p>A</p><br><br><p>B</p>'));
    }

    public function testToMaxHtmlKeepsInlineFormatting(): void
    {
        self::assertSame("Hello <b>world</b>", $this->sanitizer->toMaxHtml('<p>Hello <b>world</b></p>'));
    }

    public function testToMaxHtmlEmpty(): void
    {
        self::assertSame('', $this->sanitizer->toMaxHtml('<script>alert(1)</script>'));
    }
}
