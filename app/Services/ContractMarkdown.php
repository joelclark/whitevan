<?php

namespace App\Services;

use League\CommonMark\CommonMarkConverter;

/**
 * Renders contract markdown to sanitized HTML.
 *
 * Configured to escape any raw HTML in the source and to strip `javascript:` /
 * `data:` URLs. Used by the sysop editor, account admin editor, customer
 * approval page, and the signed-snapshot display.
 *
 * The markdown source is the canonical storage form everywhere — HTML is
 * always computed on read.
 */
class ContractMarkdown
{
    private CommonMarkConverter $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    public function toHtml(string $markdown): string
    {
        return (string) $this->converter->convert($markdown);
    }
}
