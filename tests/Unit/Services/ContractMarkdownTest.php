<?php

use App\Services\ContractMarkdown;

beforeEach(function () {
    $this->markdown = new ContractMarkdown;
});

test('renders standard markdown to HTML', function () {
    $html = $this->markdown->toHtml("# Heading\n\n- one\n- two\n\n**bold** text.");

    expect($html)->toContain('<h1>Heading</h1>');
    expect($html)->toContain('<li>one</li>');
    expect($html)->toContain('<strong>bold</strong>');
});

test('escapes inline HTML in the source', function () {
    $html = $this->markdown->toHtml('<script>alert(1)</script>');

    expect($html)->not->toContain('<script>');
    expect($html)->toContain('&lt;script&gt;');
});

test('blocks javascript: links', function () {
    $html = $this->markdown->toHtml('[click](javascript:alert(1))');

    expect($html)->not->toContain('javascript:');
});

test('blocks data: URL links', function () {
    $html = $this->markdown->toHtml('[click](data:text/html,<script>alert(1)</script>)');

    expect($html)->not->toContain('data:text/html');
});

test('allows http and https links', function () {
    $html = $this->markdown->toHtml('[site](https://example.com)');

    expect($html)->toContain('href="https://example.com"');
});
