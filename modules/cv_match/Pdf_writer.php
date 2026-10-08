<?php
/**
 * A job application or tailored résumé as a printable A4 PDF.
 *
 * A résumé tailored with fields (Tailored_resume) is laid out from them by
 * resume_pdf(). The application, and a résumé written before résumés had
 * fields, are plain text: the application is paragraphs, the old résumé
 * starts with the name and contact lines, then sections whose headings stand
 * on their own line, with "- " bullets. html() turns that text into a small
 * HTML page and pdf() renders it with dompdf
 * (packages/, composer install). Nothing remote is loaded: the page has no
 * images or links to fetch, and dompdf's remote loading stays off.
 */
require_once __DIR__ . '/Tailored_resume.php';

class Pdf_writer {

    public const KINDS = ['application', 'resume'];

    /**
     * The text as an A4 PDF document (the file's bytes).
     *
     * @param string $kind application or resume
     * @param string $text the text as shown on the page
     * @param string $title the PDF's title (its metadata), e.g. "Job application: Acme"
     */
    public static function pdf(string $kind, string $text, string $title): string {
        return self::render(self::html($kind, $text, $title), $title);
    }

    /**
     * A tailored résumé's fields (Tailored_resume) as an A4 PDF, laid out
     * like sasin91.xyz's cv.pdf (src/cv_pdf.rs there): the name, title,
     * contact and link lines, the intro, then Experience, Skills, Languages
     * and Education, each entry as "Title · Organisation" over its dates and
     * place, summary, bullets and note.
     */
    public static function resume_pdf(array $resume, string $title): string {
        return self::render(self::resume_fields_html($resume, $title), $title);
    }

    private static function render(string $html, string $title): string {
        $autoload = __DIR__ . '/../../packages/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('PDF export is not installed: run composer install.');
        }
        require_once $autoload;

        $options = new \Dompdf\Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot(__DIR__);
        // The app may run without write access to its own folder.
        $options->setTempDir(sys_get_temp_dir());
        $options->setFontCache(sys_get_temp_dir());

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        $dompdf->addInfo('Title', $title);
        return (string) $dompdf->output();
    }

    /** The résumé fields as the HTML page resume_pdf() renders. */
    public static function resume_fields_html(array $resume, string $title): string {
        $e = fn(string $text) => self::e($text);
        $body = '<h1>' . $e($resume['name']) . "</h1>\n";
        if ($resume['title'] !== '') {
            $body .= '<p class="title">' . $e($resume['title']) . "</p>\n";
        }
        $contact = array_filter([Tailored_resume::contact($resume), implode(' · ', $resume['links'])]);
        if ($contact) {
            $body .= '<p class="contact">' . implode('<br>', array_map($e, $contact)) . "</p>\n";
        }
        foreach ($resume['intro'] as $paragraph) {
            $body .= '<p class="intro">' . $e($paragraph) . "</p>\n";
        }

        $entry = function (array $entry) use ($e): string {
            $html = '<h3>' . $e(Tailored_resume::entry_heading($entry)) . "</h3>\n";
            $meta = Tailored_resume::entry_meta($entry);
            if ($meta !== '') {
                $html .= '<p class="meta">' . $e($meta) . "</p>\n";
            }
            if ($entry['summary'] !== '') {
                $html .= '<p>' . $e($entry['summary']) . "</p>\n";
            }
            if ($entry['bullets']) {
                $html .= self::list_html($entry['bullets']);
            }
            if ($entry['note'] !== '') {
                $html .= '<p class="note">' . $e($entry['note']) . "</p>\n";
            }
            return $html;
        };
        // Each block stays on one page; a heading goes with the first block.
        $section = function (string $heading, array $blocks) use ($e): string {
            if (!$blocks) {
                return '';
            }
            $first = array_shift($blocks);
            $html = "<div class=\"keep\">\n<h2>" . $e($heading) . "</h2>\n$first</div>\n";
            foreach ($blocks as $block) {
                $html .= "<div class=\"keep\">\n$block</div>\n";
            }
            return $html;
        };
        $entries = fn(array $list) => array_map(fn($item) => "<div class=\"entry\">\n" . $entry($item) . '</div>', $list);

        $body .= $section($resume['experience_heading'], $entries($resume['experience']));
        $body .= $section($resume['skills_heading'], $resume['skills'] ? [self::list_html($resume['skills'])] : []);
        $body .= $section($resume['languages_heading'], $resume['languages'] ? [self::list_html($resume['languages'])] : []);
        $education = $entries($resume['education']);
        if ($resume['education_note'] !== '') {
            array_unshift($education, '<p>' . $e($resume['education_note']) . '</p>');
        }
        $body .= $section($resume['education_heading'], $education);

        return self::page($body, $title, self::font(json_encode($resume, JSON_UNESCAPED_UNICODE) ?: ''));
    }

    private static function list_html(array $items): string {
        return "<ul>\n<li>" . implode("</li>\n<li>", array_map(fn($item) => self::e($item), $items)) . "</li>\n</ul>\n";
    }

    /**
     * The text as the HTML page pdf() renders. The layout is the one
     * sasin91.xyz's cv.pdf uses: Helvetica on A4 with 16mm side and 18mm top
     * and bottom margins, black text, a 20pt name, 11pt section headings,
     * "role" lines in bold with their bullets kept on one page, and a section
     * heading never left alone at the bottom of a page.
     */
    public static function html(string $kind, string $text, string $title): string {
        $text = self::normalise($text);
        $body = $kind === 'resume' ? self::resume_html($text) : self::letter_html($text);
        return self::page($body, $title, self::font($text));
    }

    private static function page(string $body, string $title, string $font): string {
        $title = self::e($title);
        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8">
            <title>$title</title>
            <style>
                @page { margin: 18mm 16mm; }
                body { font-family: $font; font-size: 10pt; line-height: 14pt; color: #000; }
                p { margin: 0 0 4pt 0; }
                h1 { font-size: 20pt; line-height: 24pt; font-weight: bold; margin: 0; }
                .title { font-size: 11pt; line-height: 15pt; margin: 0; }
                .contact { font-size: 9pt; line-height: 13pt; margin: 0 0 8pt 0; }
                .meta { font-size: 8.5pt; line-height: 12pt; margin: 0; }
                .note { font-size: 9pt; line-height: 13pt; margin: 3pt 0 0 0; }
                h2 { font-size: 11pt; line-height: 16pt; font-weight: bold; margin: 10pt 0 3pt 0; }
                h3 { font-size: 10.5pt; line-height: 14pt; font-weight: bold; margin: 0; }
                ul { margin: 0; padding: 0; list-style: none; }
                li { margin: 0 0 0 5mm; padding-left: 3.2mm; text-indent: -3.2mm; }
                li:before { content: "\\2022\\00a0\\00a0"; }
                .keep { page-break-inside: avoid; }
                .entry { margin: 0 0 6pt 0; }
                .letter p { margin: 0 0 8pt 0; }
            </style>
            </head>
            <body>
            $body
            </body>
            </html>
            HTML;
    }

    /**
     * Helvetica, as sasin91.xyz's cv.pdf, when every character is in its
     * WinAnsi set (Danish letters are); DejaVu Sans otherwise, so a letter
     * outside it isn't printed as "?".
     */
    private static function font(string $text): string {
        $winansi = @iconv('UTF-8', 'Windows-1252', $text);
        return $winansi !== false ? 'Helvetica, sans-serif' : '"DejaVu Sans", sans-serif';
    }

    /** The application: paragraphs split on blank lines, line breaks kept. */
    private static function letter_html(string $text): string {
        $html = '';
        foreach (preg_split('/\n\s*\n/', $text) ?: [] as $paragraph) {
            if (trim($paragraph) !== '') {
                $html .= '<p>' . nl2br(self::e(trim($paragraph)), false) . "</p>\n";
            }
        }
        return "<div class=\"letter\">\n$html</div>";
    }

    /**
     * The résumé. The first line is the name and the lines up to the first
     * blank line are contact details. After that the text is read by its
     * shape, the way the tailor_resume prompt lays it out, not by what the
     * words say: a heading is a short line on its own after a blank line,
     * with no digits and no comma; a line followed by bullets is a role (or
     * a school) and stays on one page with them; "- " lines are bullets and
     * anything else is a paragraph.
     */
    private static function resume_html(string $text): string {
        $lines = explode("\n", trim($text));
        if ($lines === ['']) {
            return '';
        }
        $html = '<h1>' . self::e(trim(array_shift($lines))) . "</h1>\n";
        $contact = [];
        while ($lines && trim($lines[0]) !== '') {
            $contact[] = self::e(trim(array_shift($lines)));
        }
        $html .= '<p class="contact">' . implode('<br>', $contact) . "</p>\n";

        // Blocks: [type, html]; type is heading, entry or text.
        $blocks = [];
        $entry = null;
        $paragraph = [];
        $bullets = [];
        $end_paragraph = function () use (&$paragraph, &$bullets, &$entry, &$blocks): void {
            $html = '';
            if ($paragraph) {
                $html .= '<p>' . implode('<br>', $paragraph) . "</p>\n";
            }
            if ($bullets) {
                $html .= "<ul>\n<li>" . implode("</li>\n<li>", $bullets) . "</li>\n</ul>\n";
            }
            $paragraph = $bullets = [];
            if ($html === '') {
                return;
            }
            if ($entry !== null) {
                $entry .= $html;
            } else {
                $blocks[] = ['text', $html];
            }
        };
        $end_entry = function () use (&$entry, &$blocks): void {
            if ($entry !== null) {
                $blocks[] = ['entry', $entry];
                $entry = null;
            }
        };

        $count = count($lines);
        $after_heading = false;
        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);
            // A block starts after a blank line or straight under a heading.
            $after_blank = $i === 0 || trim($lines[$i - 1]) === '' || $after_heading;
            $after_heading = false;
            if ($line === '') {
                $end_paragraph();
            } elseif (preg_match('/^[-•*–]\s+(.*)$/u', $line, $m)) {
                if ($paragraph) {
                    $end_paragraph();
                }
                $bullets[] = self::e($m[1]);
            } elseif ($after_blank && self::is_heading($line, $lines[$i + 1] ?? '')) {
                $end_paragraph();
                $end_entry();
                $blocks[] = ['heading', '<h2>' . self::e(rtrim($line, ': ')) . "</h2>\n"];
                $after_heading = true;
            } elseif (self::next_is_bullet($lines, $i) || ($after_blank && self::next_is_bullet($lines, $i + 1))) {
                // A role: its title line, maybe one line of dates or place,
                // then bullets.
                $end_paragraph();
                if ($after_blank) {
                    $end_entry();
                    $entry = '<h3>' . self::e($line) . "</h3>\n";
                } else {
                    $entry = ($entry ?? '') . '<p>' . self::e($line) . "</p>\n";
                }
            } else {
                if ($bullets) {
                    $end_paragraph();
                }
                if ($after_blank) {
                    $end_entry();
                }
                $paragraph[] = self::e($line);
            }
        }
        $end_paragraph();
        $end_entry();

        // A heading goes onto the page with the block after it.
        $count = count($blocks);
        for ($i = 0; $i < $count; $i++) {
            [$type, $block] = $blocks[$i];
            if ($type === 'heading' && isset($blocks[$i + 1])) {
                $next = $blocks[++$i];
                $block .= $next[0] === 'entry' ? "<div class=\"entry\">\n{$next[1]}</div>\n" : $next[1];
                $html .= "<div class=\"keep\">\n$block</div>\n";
            } elseif ($type === 'entry') {
                $html .= "<div class=\"keep entry\">\n$block</div>\n";
            } else {
                $html .= $block;
            }
        }
        return $html;
    }

    /**
     * Whether a line (after a blank line) is a section heading: short, no
     * digits (a role or a school has dates), no comma or separator (a role
     * names its employer), not a sentence, and something follows it.
     */
    private static function is_heading(string $line, string $next): bool {
        return trim($next) !== ''
            && mb_strlen($line, 'UTF-8') <= 40
            && preg_match('/[\d,|·()]/u', $line) === 0
            && preg_match('/[.!?;]$/u', $line) === 0
            && count(preg_split('/\s+/u', $line)) <= 4;
    }

    private static function next_is_bullet(array $lines, int $i): bool {
        return isset($lines[$i + 1]) && preg_match('/^\s*[-•*–]\s+/u', $lines[$i + 1]) === 1;
    }

    /** Unix line endings, no trailing spaces, no markdown bold. */
    private static function normalise(string $text): string {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\*\*(.+?)\*\*/u', '$1', $text) ?? $text;
        return preg_replace('/[ \t]+$/m', '', $text) ?? $text;
    }

    private static function e(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * A file name for the download: "Application - <job> - <company>.pdf",
     * without characters file systems refuse.
     */
    public static function file_name(string $kind, string $job_title, string $company): string {
        $parts = array_filter(
            [$kind === 'resume' ? 'Resume' : 'Application', trim($job_title), trim($company)],
            fn($part) => $part !== ''
        );
        $name = preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/u', ' ', implode(' - ', $parts)) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        return mb_substr($name, 0, 120, 'UTF-8') . '.pdf';
    }
}
