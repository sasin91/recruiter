<?php
require_once __DIR__ . '/Article_reader.php';
require_once __DIR__ . '/Slop_index.php';
require_once __DIR__ . '/../laya/Laya_client.php';
require_once __DIR__ . '/../laya/Laya_limit.php';
/**
 * /tldr-slop: paste a link to an article (or its text) and get a TL;DR and
 * whether it's slop.
 *
 * check() reads the article (Article_reader), embeds and indexes its
 * sentences with the taxonomy's model (Slop_index), and asks Laya about the
 * counts. Everything happens in the one request: a fetch (at most
 * Page_reader::TIMEOUT seconds), the embeddings (well under a second for
 * MAX_SENTENCES) and one Laya call (LAYA_TIMEOUT), inside the platform's
 * 30 second limit. Nothing is saved.
 *
 * The page is public, so checks are limited per visitor address (MAX_CHECKS
 * per WINDOW seconds, Laya_limit): each one fetches a page and asks Laya,
 * which answers one call at a time.
 */
class Tldr_slop extends Trongate {

    const MAX_CHECKS = 10;
    const WINDOW = 600;
    const LAYA_TIMEOUT = 10;

    // Longest pasted text, in characters.
    const MAX_TEXT = 60000;

    /** The page. */
    public function index(): void {
        $this->view('tldr_slop', ['base_url' => BASE_URL]);
    }

    /**
     * POST {url} or {text}: the TL;DR, the per-sentence reading, the counts
     * and Laya's verdict (`laya`), or `laya_error` saying why there is none.
     */
    public function check(): void {
        header('Content-Type: application/json');
        try {
            echo json_encode($this->assess($this->read_input()));
        } catch (Throwable $e) {
            // A status set before the throw (400s) is the visitor's input,
            // not a failure worth logging.
            if (http_response_code() < 400) {
                http_response_code(502);
                self::log_failure($e);
            }
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function assess(array $input): array {
        $url = trim((string) ($input['url'] ?? ''));
        $text = trim((string) ($input['text'] ?? ''));
        if ($url === '' && $text === '') {
            http_response_code(422);
            throw new RuntimeException('Paste a link to an article, or its text.');
        }
        if (mb_strlen($text, 'UTF-8') > self::MAX_TEXT) {
            http_response_code(413);
            throw new RuntimeException('That text is too long. Paste only the article.');
        }
        $wait = (new Laya_limit(sys_get_temp_dir() . '/tldr-slop-limit', self::MAX_CHECKS, self::WINDOW))
            ->take(Laya_limit::client($_SERVER));
        if ($wait > 0) {
            http_response_code(429);
            $minutes = (int) ceil($wait / 60);
            throw new RuntimeException("You've checked a lot of articles in a short time. Try again in $minutes minute" . ($minutes === 1 ? '' : 's') . '.');
        }

        if ($url !== '') {
            try {
                $article = Article_reader::read($url);
            } catch (RuntimeException $e) {
                // Page_reader's messages are for the person who pasted the link.
                http_response_code(422);
                throw $e;
            }
        } else {
            $article = Article_reader::from_text($text);
        }
        if (!$article['sentences']) {
            http_response_code(422);
            throw new RuntimeException('There are no sentences to read in that article. If the page needs JavaScript, copy the text and paste it instead.');
        }

        $index = Slop_index::load();
        $reading = $index->assess($article);
        $summary = Slop_index::laya_summary($article['title'], $reading['counts'], $reading['stock_words']);
        return [
            'url' => $article['url'] ?? null,
            'title' => $article['title'],
            'summary' => $summary,
        ] + $reading + $this->laya($summary);
    }

    /** Laya's answer on the summary, or why there isn't one. */
    private function laya(string $summary): array {
        $laya = Laya_client::from_env(self::LAYA_TIMEOUT);
        if ($laya === null) {
            return ['laya_error' => "Laya isn't set up on this server (no LAYA_URL), so there is no verdict. The reading below is the embeddings' alone."];
        }
        try {
            return ['laya' => Slop_index::read_answer($laya->ask($summary, Slop_index::questions()))];
        } catch (Throwable $e) {
            self::log_failure($e);
            return ['laya_error' => $e->getMessage()];
        }
    }

    private function read_input(): array {
        // A JSON content type can't be sent cross-site without a CORS
        // preflight, which this site never answers.
        if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            http_response_code(415);
            throw new RuntimeException('Expected a JSON body.');
        }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            throw new RuntimeException('Expected a JSON body.');
        }
        return $input;
    }

    /** Writes a failure the visitor was told about to the server log as well. */
    private static function log_failure(Throwable $e): void {
        error_log('tldr_slop: ' . get_class($e) . ': ' . $e->getMessage());
    }
}
