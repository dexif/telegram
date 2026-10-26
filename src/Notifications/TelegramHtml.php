<?php

namespace FlarumTelegram\Notifications;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Converts rendered post HTML to the subset Telegram accepts with parse_mode HTML.
 * Unsupported elements are unwrapped, blocks become line breaks, and the visible
 * text is cut at the given length so that the message fits into one Telegram message.
 *
 * @see https://core.telegram.org/bots/api#html-style
 */
class TelegramHtml
{
    protected const INLINE_TAGS = [
        'b' => 'b', 'strong' => 'b',
        'i' => 'i', 'em' => 'i',
        'u' => 'u', 'ins' => 'u',
        's' => 's', 'strike' => 's', 'del' => 's',
    ];

    protected const SKIPPED_TAGS = ['script', 'style', 'template', 'head'];

    protected string $out = '';
    protected int $budget = 0;
    protected int $quoteDepth = 0;

    public function __construct(
        protected string $baseUrl
    ) {
    }

    public function convert(string $html, int $limit): string
    {
        $this->out = '';
        $this->budget = $limit;
        $this->quoteDepth = 0;

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document->documentElement) {
            $this->walk($document->documentElement);
        }

        return trim(preg_replace(["/ +\n/", "/\n{3,}/"], ["\n", "\n\n"], $this->out));
    }

    protected function walk(DOMNode $node, bool $plain = false): void
    {
        foreach ($node->childNodes as $child) {
            if ($this->budget <= 0) {
                return;
            }

            if ($child instanceof DOMText) {
                $this->text($child->nodeValue, $plain);
            } elseif ($child instanceof DOMElement) {
                $this->element($child, $plain);
            }
        }
    }

    protected function element(DOMElement $element, bool $plain): void
    {
        $name = strtolower($element->nodeName);

        if (in_array($name, self::SKIPPED_TAGS)) {
            return;
        }

        // Inside code and pre Telegram allows no other tags.
        if ($plain) {
            if ($name === 'br') {
                $this->out .= "\n";
            } else {
                $this->walk($element, true);
            }

            return;
        }

        switch ($name) {
            case 'br':
                $this->out .= "\n";
                break;

            case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
                $this->wrap('b', $element);
                $this->out .= "\n\n";
                break;

            case 'p':
                $this->walk($element);
                $this->out .= "\n\n";
                break;

            case 'li':
                $this->lineStart();
                $this->out .= '• ';
                $this->walk($element);
                $this->out .= "\n";
                break;

            case 'div': case 'ul': case 'ol': case 'table': case 'tr': case 'hr':
                $this->lineStart();
                $this->walk($element);
                $this->lineStart();
                break;

            case 'td': case 'th':
                $this->walk($element);
                $this->out .= ' ';
                break;

            case 'pre':
                $this->lineStart();
                $this->out .= '<pre>';
                $this->walk($element, true);
                $this->out .= "</pre>\n";
                break;

            case 'code':
                $this->out .= '<code>';
                $this->walk($element, true);
                $this->out .= '</code>';
                break;

            case 'blockquote':
                // Telegram doesn't allow nested quotes.
                $this->lineStart();
                $this->quoteDepth++;
                if ($this->quoteDepth === 1) {
                    // Render the quote on its own to drop the line breaks around its content.
                    $outer = $this->out;
                    $this->out = '';
                    $this->walk($element);
                    $this->out = $outer.'<blockquote>'.trim(preg_replace("/\n{3,}/", "\n\n", $this->out)).'</blockquote>';
                } else {
                    $this->walk($element);
                }
                $this->quoteDepth--;
                $this->out .= "\n";
                break;

            case 'a':
                $href = $this->absoluteUrl($element->getAttribute('href'));

                if ($href) {
                    $this->out .= '<a href="'.htmlspecialchars($href, ENT_QUOTES).'">';
                    $this->walk($element);
                    $this->out .= '</a>';
                } else {
                    $this->walk($element);
                }
                break;

            case 'img':
                // Emoji are rendered as images with the character in alt.
                $this->text($element->getAttribute('alt'), false);
                break;

            default:
                if (isset(self::INLINE_TAGS[$name])) {
                    $this->wrap(self::INLINE_TAGS[$name], $element);
                } else {
                    $this->walk($element);
                }
        }
    }

    protected function wrap(string $tag, DOMElement $element): void
    {
        $this->out .= "<$tag>";
        $this->walk($element);
        $this->out .= "</$tag>";
    }

    protected function text(string $text, bool $plain): void
    {
        if (! $plain) {
            $text = preg_replace('/\s+/u', ' ', $text);

            if ($this->out === '' || str_ends_with($this->out, "\n")) {
                $text = ltrim($text);
            }
        }

        if ($text === '' || $this->budget <= 0) {
            return;
        }

        if (mb_strlen($text) > $this->budget) {
            $text = mb_substr($text, 0, $this->budget).'…';
            $this->budget = 0;
        } else {
            $this->budget -= mb_strlen($text);
        }

        $this->out .= htmlspecialchars($text, ENT_NOQUOTES);
    }

    protected function lineStart(): void
    {
        if ($this->out !== '' && ! str_ends_with($this->out, "\n")) {
            $this->out .= "\n";
        }
    }

    protected function absoluteUrl(string $href): ?string
    {
        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            $href = $this->baseUrl.$href;
        }

        return preg_match('#^https?://#i', $href) ? $href : null;
    }
}
