<?php
/**
 * Reads a job post from a URL someone pasted: fetches the page without
 * letting the URL reach anything private, and turns the HTML into plain text
 * before it goes anywhere near the language model.
 *
 * Fetching rules:
 * - http and https only, on the default ports, with no user:password part.
 * - Every address the host resolves to must be public (no private, loopback,
 *   link-local, carrier-grade NAT, multicast or reserved ranges), and curl
 *   connects to the address that was checked, so DNS can't change its answer
 *   between the check and the request.
 * - Redirects are followed by hand, at most MAX_REDIRECTS, and every hop is
 *   checked the same way.
 * - No proxy from the environment, a short timeout, at most MAX_BYTES of
 *   body, and only HTML or plain text.
 *
 * A JobPosting in the page's JSON-LD (most job boards have one) is used when
 * present; otherwise the visible text of <main>, <article> or <body>.
 */
class Page_reader {

    const MAX_REDIRECTS = 5;
    const MAX_BYTES = 2_000_000;
    const MAX_TEXT = 30_000;
    const TIMEOUT = 15;
    const USER_AGENT = 'Mozilla/5.0 (compatible; Recruiter/1.0; +https://recruiter.trongate.dev)';

    /** Elements whose content is never part of the post. */
    const DROP = ['script', 'style', 'noscript', 'template', 'svg', 'iframe', 'object', 'embed', 'canvas',
        'head', 'nav', 'footer', 'form', 'button', 'select', 'dialog'];

    /** Elements that start a new line. */
    const BLOCKS = ['p', 'div', 'section', 'article', 'main', 'header', 'aside', 'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'table', 'tr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'hr', 'figure', 'address'];

    /**
     * The job post at $url as {url, title, text}; `url` is where the last
     * redirect landed.
     *
     * @throws RuntimeException with a message for the person who pasted the URL.
     */
    public static function read(string $url): array {
        $url = trim($url);
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $target = self::check_url($url);
            $response = self::fetch($target);
            if ($response['redirect'] === null) {
                return ['url' => $target['url']] + self::to_text($response['body'], $response['content_type']);
            }
            $url = $response['redirect'];
        }
        throw new RuntimeException('The page redirects too many times.');
    }

    /**
     * Checks a URL and resolves its host to one public address. `url` is the
     * URL rebuilt from its checked parts, so curl sees exactly the host that
     * was resolved.
     *
     * @return array{url: string, host: string, port: int, ip: string}
     * @throws RuntimeException when the URL may not be fetched.
     */
    public static function check_url(string $url, ?callable $resolve = null): array {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        if ($parts === false || !in_array($scheme, ['http', 'https'], true) || empty($parts['host'])) {
            throw new RuntimeException('Paste a web address starting with http:// or https://.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Web addresses with a username or password are not fetched.');
        }
        $port = $scheme === 'https' ? 443 : 80;
        if (isset($parts['port']) && $parts['port'] !== $port) {
            throw new RuntimeException('Only the standard web ports are fetched.');
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if (preg_match('/[^\x20-\x7e]/', $host)) {
            $host = function_exists('idn_to_ascii') ? (idn_to_ascii($host) ?: '') : '';
        }
        $literal = str_starts_with($host, '[');
        if ($literal) {
            $addresses = [trim($host, '[]')];
        } elseif (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $addresses = [$host];
        } elseif (preg_match('/(^|\.)(0x[0-9a-f]*|[0-9]+)$/', $host)) {
            // 2130706433, 0x7f.1, 127.1: other spellings of an address.
            throw new RuntimeException('That address is not a public web page.');
        } elseif (preg_match('/^[a-z0-9_]([a-z0-9_-]*[a-z0-9_])?(\.[a-z0-9_]([a-z0-9_-]*[a-z0-9_])?)+$/', $host)) {
            $addresses = ($resolve ?? self::resolve(...))($host);
        } else {
            throw new RuntimeException('That address is not a public web page.');
        }
        if (!$addresses) {
            throw new RuntimeException("Couldn't find $host.");
        }
        foreach ($addresses as $ip) {
            if (!self::is_public($ip)) {
                throw new RuntimeException('That address is not a public web page.');
            }
        }
        // Prefer IPv4: it's what most hosts in a cluster can reach.
        usort($addresses, fn($a, $b) => str_contains($a, ':') <=> str_contains($b, ':'));
        $url = "$scheme://$host" . ($parts['path'] ?? '/') . (isset($parts['query']) ? "?{$parts['query']}" : '');
        return ['url' => $url, 'host' => $host, 'port' => $port, 'ip' => $addresses[0], 'literal' => $literal || filter_var($host, FILTER_VALIDATE_IP) !== false];
    }

    /**
     * Whether an IP address is on the public internet.
     */
    public static function is_public(string $ip): bool {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }
        if (strlen($packed) === 4) {
            $blocked = ['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
                '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16',
                '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'];
            foreach ($blocked as $range) {
                if (self::in_range($packed, $range)) {
                    return false;
                }
            }
            return true;
        }
        // IPv6: global unicast only, which leaves out loopback, link-local,
        // unique-local, multicast and the IPv4-mapped and NAT64 forms; then
        // the special ranges inside it (Teredo and friends, documentation, 6to4).
        if (!self::in_range($packed, '2000::/3')) {
            return false;
        }
        foreach (['2001::/23', '2001:db8::/32', '2002::/16'] as $range) {
            if (self::in_range($packed, $range)) {
                return false;
            }
        }
        return true;
    }

    /**
     * The page's job post as plain text: {title, text}.
     */
    public static function to_text(string $html, string $content_type = 'text/html'): array {
        $html = self::to_utf8($html, $content_type);
        if (!preg_match('~html~i', $content_type) && !preg_match('~<html|<body|<!doctype~i', substr($html, 0, 2000))) {
            return ['title' => '', 'text' => self::tidy($html)];
        }

        $posting = self::job_posting($html);
        if ($posting) {
            return $posting;
        }

        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        $title = trim($doc->getElementsByTagName('title')->item(0)?->textContent ?? '');
        $xpath = new DOMXPath($doc);
        $drop = implode(' | ', array_map(fn($tag) => "//$tag", self::DROP));
        foreach (iterator_to_array($xpath->query("$drop | //*[@hidden] | //*[@aria-hidden='true']")) as $node) {
            $node->parentNode?->removeChild($node);
        }

        // The main content when the page marks it and it has the post in it.
        $root = $doc->getElementsByTagName('body')->item(0) ?? $doc;
        foreach (['main', 'article'] as $tag) {
            $node = $doc->getElementsByTagName($tag)->item(0);
            if ($node && mb_strlen(trim($node->textContent)) >= 500) {
                $root = $node;
                break;
            }
        }
        $text = self::tidy(self::text_of($root));
        if ($text === '') {
            throw new RuntimeException('The page has no text. It may need JavaScript to show the job post: copy the text from your browser and paste it instead.');
        }
        return ['title' => self::tidy($title), 'text' => $text];
    }

    /**
     * Fetches a checked URL from its checked address, without following
     * redirects.
     *
     * @return array{body: string, content_type: string, redirect: ?string}
     */
    private static function fetch(array $target): array {
        $body = '';
        $too_big = false;
        $address = str_contains($target['ip'], ':') ? "[{$target['ip']}]" : $target['ip'];
        $curl = curl_init($target['url']);
        curl_setopt_array($curl, [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            // Connect to the address that was checked, not a fresh lookup.
            CURLOPT_RESOLVE => $target['literal'] ? [] : ["{$target['host']}:{$target['port']}:$address"],
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_MAXFILESIZE => self::MAX_BYTES,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,text/plain;q=0.9', 'Accept-Language: da,en;q=0.8'],
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$body, &$too_big) {
                if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
                    $too_big = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $content_type = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $redirect = curl_getinfo($curl, CURLINFO_REDIRECT_URL) ?: null;
        $connected_to = (string) curl_getinfo($curl, CURLINFO_PRIMARY_IP);
        $error = curl_error($curl);
        $errno = curl_errno($curl);
        curl_close($curl);

        // Belt and braces: never use an answer from an address that wasn't checked.
        if ($connected_to !== '' && inet_pton($connected_to) !== inet_pton($target['ip'])) {
            throw new RuntimeException('That address is not a public web page.');
        }
        if ($too_big || $errno === CURLE_FILESIZE_EXCEEDED) {
            throw new RuntimeException('The page is too large to read.');
        }
        if ($ok === false) {
            throw new RuntimeException("Couldn't fetch the page: $error");
        }
        if ($status >= 300 && $status < 400 && $redirect) {
            return ['body' => '', 'content_type' => '', 'redirect' => $redirect];
        }
        if ($status >= 400) {
            throw new RuntimeException("The page answered HTTP $status. If it needs a login, copy the text and paste it instead.");
        }
        if (!preg_match('~^(text/html|application/xhtml\+xml|text/plain)\b~i', $content_type)) {
            throw new RuntimeException('That address is not a web page' . ($content_type ? " ($content_type)" : '') . '. For a PDF, upload the file instead.');
        }
        return ['body' => $body, 'content_type' => $content_type, 'redirect' => null];
    }

    /** IPv4 addresses from the system resolver (so /etc/hosts counts) and IPv6 from DNS. */
    private static function resolve(string $host): array {
        $v4 = gethostbynamel($host) ?: [];
        $v6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');
        return array_values(array_unique([...$v4, ...$v6]));
    }

    private static function in_range(string $packed, string $cidr): bool {
        [$network, $bits] = explode('/', $cidr);
        $network = inet_pton($network);
        if (strlen($network) !== strlen($packed)) {
            return false;
        }
        $bytes = intdiv((int) $bits, 8);
        $rest = (int) $bits % 8;
        if (substr($packed, 0, $bytes) !== substr($network, 0, $bytes)) {
            return false;
        }
        if ($rest === 0) {
            return true;
        }
        $mask = (0xff << (8 - $rest)) & 0xff;
        return (ord($packed[$bytes]) & $mask) === (ord($network[$bytes]) & $mask);
    }

    private static function to_utf8(string $html, string $content_type): string {
        $charset = '';
        if (preg_match('~charset=["\']?([\w-]+)~i', $content_type, $m)
            || preg_match('~<meta[^>]+charset=["\']?([\w-]+)~i', substr($html, 0, 4000), $m)) {
            $charset = strtoupper($m[1]);
        }
        if ($charset === '' || $charset === 'UTF-8' || $charset === 'UTF8') {
            return mb_check_encoding($html, 'UTF-8') ? $html : mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
        }
        $converted = @mb_convert_encoding($html, 'UTF-8', $charset === 'ISO-8859-1' ? 'Windows-1252' : $charset);
        return is_string($converted) ? $converted : mb_convert_encoding($html, 'UTF-8', 'Windows-1252');
    }

    /**
     * The JobPosting from the page's JSON-LD as {title, text}, or null when
     * there is none with a description.
     */
    private static function job_posting(string $html): ?array {
        preg_match_all('~<script[^>]+type=["\']?application/ld\+json["\']?[^>]*>(.*?)</script>~is', $html, $scripts);
        foreach ($scripts[1] as $json) {
            $data = json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true)
                ?? json_decode(trim($json), true);
            $stack = is_array($data) ? [$data] : [];
            while ($stack) {
                $node = array_pop($stack);
                $type = (array) ($node['@type'] ?? []);
                if (in_array('JobPosting', $type, true) && is_string($node['description'] ?? null)) {
                    $description = self::to_text('<html><body>' . html_entity_decode($node['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</body></html>')['text'];
                    if (mb_strlen($description) < 200) {
                        continue;
                    }
                    $title = self::tidy(is_string($node['title'] ?? null) ? $node['title'] : '');
                    $company = $node['hiringOrganization']['name'] ?? '';
                    $head = array_filter([$title, is_string($company) ? self::tidy($company) : '', self::location($node)]);
                    return ['title' => $title, 'text' => self::tidy(implode("\n", $head) . "\n\n" . $description)];
                }
                foreach ($node as $value) {
                    if (is_array($value)) {
                        $stack[] = $value;
                    }
                }
            }
        }
        return null;
    }

    private static function location(array $posting): string {
        $places = $posting['jobLocation'] ?? [];
        $address = (isset($places['address']) ? $places : ($places[0] ?? []))['address'] ?? [];
        if (!is_array($address)) {
            return is_string($address) ? $address : '';
        }
        $parts = array_filter([$address['streetAddress'] ?? '', trim(($address['postalCode'] ?? '') . ' ' . ($address['addressLocality'] ?? ''))], 'is_string');
        return self::tidy(implode(', ', array_filter($parts)));
    }

    private static function text_of(DOMNode $node): string {
        if ($node instanceof DOMText) {
            return preg_replace('/\s+/u', ' ', $node->textContent);
        }
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= self::text_of($child);
        }
        if (!$node instanceof DOMElement) {
            return $text;
        }
        $tag = strtolower($node->tagName);
        return match (true) {
            $tag === 'br' => "\n",
            $tag === 'li' => "\n- " . trim($text),
            in_array($tag, ['td', 'th'], true) => trim($text) . ' ',
            in_array($tag, self::BLOCKS, true) => "\n" . $text . "\n",
            default => $text,
        };
    }

    private static function tidy(string $text): string {
        $text = str_replace(["\r\n", "\r", "\u{a0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/ *\n */u', "\n", $text);
        $text = trim(preg_replace('/\n{3,}/', "\n\n", $text));
        return mb_strlen($text) > self::MAX_TEXT ? mb_substr($text, 0, self::MAX_TEXT) : $text;
    }

}
