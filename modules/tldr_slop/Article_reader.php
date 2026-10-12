<?php
require_once __DIR__ . '/../cv_match/Page_reader.php';
/**
 * Reads an article for /tldr-slop: fetches the page with Page_reader (public
 * web pages only, same limits as a job post) and keeps the article body as
 * prose sentences, counting what isn't prose (code blocks, links) on the way.
 *
 * The body is the first of the page's articleBody, #article-body (dev.to),
 * <article> or <main> with at least MIN_BODY characters, else <body>.
 * Comments, navigation and forms are dropped. Code blocks are counted and
 * left out of the sentences: they aren't prose and would read as substance
 * or noise depending on the language.
 *
 * Pasted text is read the same way, with ``` fences as code blocks.
 */
class Article_reader {

    const MIN_BODY = 500;

    // Most sentences kept: enough for a long post, and it bounds the
    // repeated-sentence check, which compares every pair.
    const MAX_SENTENCES = 400;

    // A sentence has at least this many words; shorter lines are headings,
    // captions and list stubs.
    const MIN_WORDS = 5;

    const BODIES = ["//*[@itemprop='articleBody']", "//*[@id='article-body']", '//article', '//main'];

    const DROP = ["//*[@id='comments']", "//*[contains(concat(' ', normalize-space(@class), ' '), ' comments ')]", '//aside'];

    /**
     * The article at $url: {url, title, sentences, words, code_blocks, links}.
     *
     * @throws RuntimeException with a message for the person who pasted the URL.
     */
    public static function read(string $url): array {
        $page = Page_reader::fetch_page($url);
        return ['url' => $page['url']] + self::parse($page['body'], $page['content_type'], $page['url']);
    }

    /** An article from its HTML (or plain text): see read(). */
    public static function parse(string $html, string $content_type = 'text/html', string $url = ''): array {
        $html = Page_reader::to_utf8($html, $content_type);
        if (!preg_match('~html~i', $content_type) && !preg_match('~<html|<body|<!doctype~i', substr($html, 0, 2000))) {
            return self::from_text($html);
        }

        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        $xpath = new DOMXPath($doc);
        $title = self::title($xpath);
        $drop = implode(' | ', [...array_map(fn($tag) => "//$tag", Page_reader::DROP), ...self::DROP, "//*[@hidden]", "//*[@aria-hidden='true']"]);
        foreach (iterator_to_array($xpath->query($drop)) as $node) {
            $node->parentNode?->removeChild($node);
        }

        $root = $doc->getElementsByTagName('body')->item(0) ?? $doc;
        foreach (self::BODIES as $query) {
            $node = $xpath->query($query)->item(0);
            if ($node && mb_strlen(trim($node->textContent)) >= self::MIN_BODY) {
                $root = $node;
                break;
            }
        }

        $host = parse_url($url, PHP_URL_HOST);
        $links = 0;
        foreach ($xpath->query('.//a[@href]', $root) as $a) {
            $target = parse_url($a->getAttribute('href'), PHP_URL_HOST);
            $links += $target !== null && $target !== false && $target !== $host ? 1 : 0;
        }
        $code_blocks = 0;
        foreach (iterator_to_array($xpath->query('.//pre', $root)) as $pre) {
            $code_blocks++;
            $pre->parentNode?->removeChild($pre);
        }

        $text = Page_reader::tidy(Page_reader::text_of($root));
        if ($text === '') {
            throw new RuntimeException('The page has no text. It may need JavaScript to show the article: copy the text from your browser and paste it instead.');
        }
        return ['title' => $title] + self::counted($text, $code_blocks, $links);
    }

    /** Pasted text: ``` fences are code, the first short line is the title. */
    public static function from_text(string $text): array {
        $code_blocks = 0;
        $text = preg_replace_callback('/^```.*?^```[^\n]*$/ms', function () use (&$code_blocks) {
            $code_blocks++;
            return '';
        }, str_replace(["\r\n", "\r"], "\n", $text));
        $links = preg_match_all('~https?://\S+~', $text);
        // Hard-wrapped paragraphs (Markdown, email): a line that goes on in
        // lower case continues the one before.
        $text = preg_replace('/(?<![.!?:])\n(?=[ \t]*[\p{Ll}(“"])/u', ' ', $text);
        $text = Page_reader::tidy($text);
        $first = trim(strtok($text, "\n") ?: '');
        $title = '';
        if ($first !== '' && str_word_count($first) < 16 && !preg_match('/[.!?]$/', $first)) {
            $title = ltrim($first, '# ');
            $text = (string) substr($text, strlen($first));
        }
        return ['title' => $title] + self::counted($text, $code_blocks, (int) $links);
    }

    private static function counted(string $text, int $code_blocks, int $links): array {
        $sentences = self::sentences($text);
        return [
            'sentences' => $sentences,
            'words' => array_sum(array_map(fn($s) => self::word_count($s), $sentences)),
            'code_blocks' => $code_blocks,
            'links' => $links,
        ];
    }

    /**
     * The prose sentences of $text in order: each line split after . ! ? or …
     * when the next word starts a sentence, keeping those of MIN_WORDS or
     * more, at most MAX_SENTENCES.
     *
     * @return string[]
     */
    public static function sentences(string $text): array {
        $sentences = [];
        foreach (preg_split('/\n+/u', $text) as $line) {
            $line = trim(preg_replace('/^[-*•]\s+/u', '', trim($line)));
            $parts = preg_split('/(?<!\.\.\.)(?<=[.!?…])["”’)\]]*\s+(?=["“‘(\[]?[\p{Lu}\d])/u', $line) ?: [];
            foreach ($parts as $part) {
                $part = trim($part);
                if (self::word_count($part) >= self::MIN_WORDS) {
                    $sentences[] = $part;
                    if (count($sentences) >= self::MAX_SENTENCES) {
                        return $sentences;
                    }
                }
            }
        }
        return $sentences;
    }

    public static function word_count(string $text): int {
        return preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’.,\/-]*/u', $text);
    }

    /** og:title, else the first <h1>, else <title>. */
    private static function title(DOMXPath $xpath): string {
        foreach (["//meta[@property='og:title']/@content", '//h1', '//title'] as $query) {
            $title = trim(preg_replace('/\s+/u', ' ', $xpath->query($query)->item(0)?->textContent ?? ''));
            if ($title !== '') {
                return $title;
            }
        }
        return '';
    }
}
