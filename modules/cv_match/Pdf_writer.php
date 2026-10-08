<?php
/**
 * A job application or tailored résumé as a printable A4 PDF.
 *
 * Both are plain text (see the prompts in Cv_match): the application is
 * paragraphs, the résumé starts with the name and contact lines, then
 * sections whose headings stand on their own line, with "- " bullets. html()
 * turns that text into a small HTML page and pdf() renders it with dompdf
 * (packages/, composer install). Nothing remote is loaded: the page has no
 * images or links to fetch, and dompdf's remote loading stays off.
 */
class Pdf_writer {

    public const KINDS = ['application', 'resume'];

    // Headings the résumé prompt asks for, in English and Danish, and the
    // other common ones. A line that is one of these (with or without a
    // trailing colon), or a short line in capitals, starts a section.
    private const HEADINGS = [
        'profile', 'profil', 'summary', 'resume', 'résumé', 'about me', 'om mig',
        'experience', 'work experience', 'professional experience', 'employment', 'employment history',
        'erfaring', 'erhvervserfaring', 'arbejdserfaring', 'beskæftigelse', 'ansættelser',
        'education', 'uddannelse', 'uddannelser', 'courses', 'kurser', 'training', 'efteruddannelse',
        'skills', 'key skills', 'technical skills', 'kompetencer', 'færdigheder', 'kvalifikationer',
        'it-kompetencer', 'tekniske kompetencer', 'faglige kompetencer', 'personlige kompetencer',
        'languages', 'language', 'sprog', 'sprogkundskaber',
        'certifications', 'certificates', 'certifikater', 'certificeringer',
        'projects', 'projekter', 'achievements', 'resultater',
        'interests', 'hobbies', 'interesser', 'fritidsinteresser', 'fritid',
        'references', 'referencer', 'contact', 'kontakt', 'volunteering', 'frivilligt arbejde',
    ];

    /**
     * The text as an A4 PDF document (the file's bytes).
     *
     * @param string $kind application or resume
     * @param string $text the text as shown on the page
     * @param string $title the PDF's title (its metadata), e.g. "Job application: Acme"
     */
    public static function pdf(string $kind, string $text, string $title): string {
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
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml(self::html($kind, $text, $title), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        $dompdf->addInfo('Title', $title);
        return (string) $dompdf->output();
    }

    /** The text as the HTML page pdf() renders. */
    public static function html(string $kind, string $text, string $title): string {
        $body = $kind === 'resume' ? self::resume_html($text) : self::letter_html($text);
        $title = self::e($title);
        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8">
            <title>$title</title>
            <style>
                @page { margin: 22mm 20mm 20mm 20mm; }
                body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.45; color: #1f2328; }
                p { margin: 0 0 9pt 0; }
                h1 { font-size: 20pt; font-weight: bold; margin: 0 0 3pt 0; color: #111; }
                .contact { color: #555; font-size: 9pt; margin: 0 0 14pt 0; }
                h2 { font-size: 10.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em;
                     color: #1a5276; border-bottom: 0.75pt solid #1a5276; padding-bottom: 2pt; margin: 14pt 0 6pt 0; }
                h3 { font-size: 10pt; font-weight: bold; margin: 8pt 0 2pt 0; }
                ul { margin: 0 0 8pt 0; padding-left: 13pt; }
                li { margin: 0 0 2pt 0; }
                .letter { font-size: 10.5pt; line-height: 1.55; }
                .letter p { margin: 0 0 11pt 0; }
            </style>
            </head>
            <body>
            $body
            </body>
            </html>
            HTML;
    }

    /** The application: paragraphs split on blank lines, line breaks kept. */
    private static function letter_html(string $text): string {
        $paragraphs = preg_split('/\n\s*\n/', self::normalise($text)) ?: [];
        $html = '';
        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph) !== '') {
                $html .= '<p>' . nl2br(self::e(trim($paragraph)), false) . "</p>\n";
            }
        }
        return "<div class=\"letter\">\n$html</div>";
    }

    /**
     * The résumé: the first line is the name, the lines up to the first
     * blank line or heading are contact details, then headings, bullet lists
     * and paragraphs. A short line followed by bullets (a role or a school)
     * is a subheading.
     */
    private static function resume_html(string $text): string {
        $lines = explode("\n", self::normalise($text));
        while ($lines && trim($lines[0]) === '') {
            array_shift($lines);
        }
        if (!$lines) {
            return '';
        }

        $html = '<h1>' . self::e(trim(array_shift($lines))) . "</h1>\n";
        $contact = [];
        while ($lines && trim($lines[0]) !== '' && !self::is_heading($lines[0])) {
            $contact[] = self::e(trim(array_shift($lines)));
        }
        if ($contact) {
            $html .= '<p class="contact">' . implode('<br>', $contact) . "</p>\n";
        }

        $paragraph = [];
        $bullets = [];
        $flush = function () use (&$html, &$paragraph, &$bullets): void {
            if ($paragraph) {
                $html .= '<p>' . implode('<br>', $paragraph) . "</p>\n";
                $paragraph = [];
            }
            if ($bullets) {
                $html .= "<ul>\n<li>" . implode("</li>\n<li>", $bullets) . "</li>\n</ul>\n";
                $bullets = [];
            }
        };
        $count = count($lines);
        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                $flush();
            } elseif (preg_match('/^[-•*–]\s+(.*)$/u', $line, $m)) {
                if ($paragraph) {
                    $flush();
                }
                $bullets[] = self::e($m[1]);
            } elseif (self::is_heading($line)) {
                $flush();
                $html .= '<h2>' . self::e(rtrim($line, ': ')) . "</h2>\n";
            } elseif (mb_strlen($line, 'UTF-8') <= 90 && self::next_is_bullet($lines, $i)) {
                $flush();
                $html .= '<h3>' . self::e($line) . "</h3>\n";
            } else {
                if ($bullets) {
                    $flush();
                }
                $paragraph[] = self::e($line);
            }
        }
        $flush();
        return $html;
    }

    /** Whether a line is a section heading. */
    private static function is_heading(string $line): bool {
        $line = trim($line);
        $bare = mb_strtolower(rtrim($line, ': '), 'UTF-8');
        if (in_array($bare, self::HEADINGS, true)) {
            return true;
        }
        // "WORK EXPERIENCE", "IT-KOMPETENCER": short, letters, all capitals.
        return mb_strlen($line, 'UTF-8') <= 40
            && preg_match('/^[\p{Lu}][\p{Lu}\s&\/\-]+:?$/u', $line) === 1;
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
