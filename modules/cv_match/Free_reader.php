<?php
require_once __DIR__ . '/../../taxonomy/php/Taxonomy.php';

/**
 * The free reading of a job post and a CV: no language model, so it costs
 * nothing per request and is what visitors who aren't signed in get.
 *
 * It spots the taxonomy's skills, roles and titles where the text names them
 * by one of their labels ("PHP", "Kørekort B", "projektledelse"), and reads
 * years of experience with plain rules. The answers have the shape of the
 * model's extract_job and extract_cv answers, so Cv_matcher scores them the
 * same way. What it can't do: anything the text says in other words
 * ("you can lead a small team"), levels ("flydende dansk"), alternatives
 * ("pædagog eller pædagogisk assistent") and responsibilities.
 */
class Free_reader {

    // Longest label, in words, worth looking for.
    private const MAX_WORDS = 5;

    // Labels that are everyday words in a job post or a CV, so finding them
    // says nothing about a skill ("Team", "Kunder", "Ansvar").
    private const TOO_COMMON =
        'ansvar arbejde kunder kunde team teams opgaver ledelse udvikling service salg drift ' .
        'kommunikation samarbejde planlægning struktur overblik support it web app apps ' .
        'work design data administration management development projects project ' .
        'uddannelse erfaring ansvarlig fleksibel selvstændig engageret';

    // A line that says what follows is a nice-to-have.
    private const NICE_TO_HAVE = '/\b(en fordel|fordel|gerne|ønskeligt|ønskværdigt|plus|nice to have|bonus|preferably|preferred|advantage|ideally)\b/iu';

    // Headings around the part of a job post that lists what is asked for,
    // and of a CV that lists education (whose dates are not work experience).
    private const ASKS = '/^(din profil|om dig|vi forventer|vi søger en|vi forestiller os|kvalifikationer|krav|du har|du er|du kan|du bringer|hvem er du|requirements|qualifications|what you bring|who you are|you have|your profile|about you|what we expect|must have|nice to have)\b/iu';
    private const OFFERS = '/^(vi tilbyder|vi kan tilbyde|hvad vi tilbyder|om os|om virksomheden|om jobbet|ansøgning|løn og|we offer|what we offer|about us|benefits|how to apply|apply)\b/iu';
    private const EDUCATION = '/^(uddannelse|uddannelser|education|kurser|courses|certificeringer|certifications)\b/iu';
    private const WORK = '/^(erfaring|erhvervserfaring|arbejdserfaring|experience|work experience|employment|ansættelser|karriere)\b/iu';

    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'maj' => 5, 'may' => 5, 'jun' => 6, 'jul' => 7,
        'aug' => 8, 'sep' => 9, 'okt' => 10, 'oct' => 10, 'nov' => 11, 'dec' => 12,
    ];

    /** @var array<string, array> label words joined by spaces => term */
    private array $labels = [];

    /** @var array<int, true> term ids that are soft skills */
    private array $soft = [];

    /**
     * Every phrase job() and cv() have put in their answers, with its term,
     * for Cv_matcher's $known.
     *
     * @var array<string, array>
     */
    public array $known = [];

    public function __construct(private Taxonomy $taxonomy) {
        $common = array_fill_keys(explode(' ', self::TOO_COMMON), true);
        $ignore = array_fill_keys(require __DIR__ . '/free_reader_ignore.php', true);
        $this->soft = array_fill_keys(require __DIR__ . '/free_reader_soft.php', true);
        foreach ($taxonomy->terms as $term) {
            if ($term['kind'] === 'skill_category' || isset($ignore[$term['id']])) {
                continue;
            }
            foreach ($term['phrases'] as $phrase) {
                $key = implode(' ', $phrase['words']);
                if (count($phrase['words']) > self::MAX_WORDS || isset($common[$key]) || mb_strlen($key, 'UTF-8') < 2) {
                    continue;
                }
                // One letter or a bare number is never a skill on its own ("C" is, but reads as noise).
                if (count($phrase['words']) === 1 && (mb_strlen($key, 'UTF-8') < 2 || ctype_digit($key))) {
                    continue;
                }
                // A skill and a title can share a label; the first (skills come first) wins.
                $this->labels[$key] ??= $term;
            }
        }
    }

    /**
     * The job post in extract_job's shape: the title and the requirements the
     * taxonomy recognises, with years of experience by rule.
     */
    public function job(string $text): array {
        $lines = self::lines($text);
        $title = $lines[0] ?? '';
        $asks = self::section($lines, self::ASKS, self::OFFERS);

        $requirements = [];
        $seen = [];
        foreach ($asks ?: $lines as $line) {
            $required = !preg_match(self::NICE_TO_HAVE, $line);
            if ($years = self::years_asked($line)) {
                $requirements[] = self::requirement("$years års erfaring", "$years years of experience", 'experience', $required, $years);
            }
            foreach ($this->spot($line) as $term) {
                if ($term['kind'] !== 'skill' || isset($seen[$term['id']])) {
                    continue;
                }
                $seen[$term['id']] = true;
                $requirements[] = self::requirement($this->name($term), self::label($term, 'en'), $this->kind($term), $required);
            }
        }

        $title_term = null;
        foreach ($this->spot($title) as $term) {
            if ($term['kind'] === 'title' || $term['kind'] === 'role') {
                $title_term = $term;
                break;
            }
        }
        return [
            'text_en' => $text,
            'job_title' => $title_term ? $this->name($title_term) : '',
            'job_title_en' => $title_term ? self::label($title_term, 'en') : '',
            'heading' => mb_substr($title, 0, 160, 'UTF-8'),
            'company' => '',
            'requirements' => $requirements,
            'responsibilities' => [],
        ];
    }

    /**
     * The CV in extract_cv's shape: the skills, roles and titles it names, and
     * the years between the dates of its work history.
     */
    public function cv(string $text): array {
        $lines = self::lines($text);
        $skills = [];
        $titles = [];
        foreach ($lines as $line) {
            foreach ($this->spot($line) as $term) {
                $label = $this->name($term);
                if ($term['kind'] === 'skill') {
                    $skills[$label] = true;
                } else {
                    $titles[$label] = true;
                }
            }
        }
        $education = self::section($lines, self::EDUCATION, self::WORK);
        $work = array_values(array_diff($lines, $education));
        return [
            'name' => '',
            'text_en' => $text,
            'title' => array_keys($titles),
            'skill' => array_keys($skills),
            'language' => [],
            'certificate' => [],
            'education' => [],
            'experience_years' => self::years_worked(implode("\n", $work)),
            'responsibilities' => [],
        ];
    }

    /**
     * The terms a line names by a label, longest label first, each word used
     * once ("Social- og sundhedsassistent" is one title, not also "assistent").
     *
     * @return array[]
     */
    public function spot(string $line): array {
        $words = Taxonomy::words($line);
        $found = [];
        $n = count($words);
        for ($i = 0; $i < $n;) {
            $hit = null;
            for ($len = min(self::MAX_WORDS, $n - $i); $len >= 1; $len--) {
                $key = implode(' ', array_slice($words, $i, $len));
                if (isset($this->labels[$key])) {
                    $hit = [$this->labels[$key], $len];
                    break;
                }
            }
            if ($hit) {
                $found[$hit[0]['id']] = $hit[0];
                $i += $hit[1];
            } else {
                $i++;
            }
        }
        return array_values($found);
    }

    /** Years of experience a line asks for: "3+ års erfaring", "flere års erfaring", "5 years of experience". */
    public static function years_asked(string $line): int {
        $line = mb_strtolower($line, 'UTF-8');
        if (!preg_match('/erfaring|experience/u', $line)) {
            return 0;
        }
        if (preg_match('/(\d{1,2})\s*\+?\s*(?:-\s*\d{1,2}\s*)?(?:års|år|years?|yrs)/u', $line, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/et par års|a couple of years|a few years/u', $line)) {
            return 2;
        }
        return preg_match('/flere års|mange års|several years|many years/u', $line) ? 3 : 0;
    }

    /**
     * Whole years covered by the date ranges in a work history ("September
     * 2024 – February 2026", "2017 - nu", "03/2019 - 08/2021"), overlaps
     * counted once.
     */
    public static function years_worked(string $text, ?int $now = null): int {
        $now ??= time();
        $current = (int) date('Y', $now) * 12 + (int) date('n', $now) - 1;
        $month = '(?:(\p{L}{3,})\.?\s+|(\d{1,2})[\/.-])?';
        $pattern = "/$month((?:19|20)\\d{2})\\s*(?:-|–|—|til|to)\\s*(?:$month((?:19|20)\\d{2})|(nu|now|present|i dag|dags dato|current|today))/iu";
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
        $ranges = [];
        foreach ($matches as $m) {
            $from = (int) $m[3] * 12 + self::month($m[1], $m[2], 1);
            $to = ($m[6] ?? '') !== ''
                ? (int) $m[6] * 12 + self::month($m[4] ?? '', $m[5] ?? '', 12)
                : $current;
            if ($to >= $from && $to <= $current + 1) {
                $ranges[] = [$from, $to];
            }
        }
        usort($ranges, fn($a, $b) => $a[0] <=> $b[0]);
        $months = 0;
        $end = -1;
        foreach ($ranges as [$from, $to]) {
            if ($to <= $end) {
                continue;
            }
            $months += $to - max($from, $end + 1) + 1;
            $end = $to;
        }
        return intdiv($months, 12);
    }

    /** The month index (0-11) of a month name or number, or the default (1-12). */
    private static function month(string $name, string $number, int $default): int {
        if ($number !== '' && (int) $number >= 1 && (int) $number <= 12) {
            return (int) $number - 1;
        }
        $key = mb_substr(mb_strtolower($name, 'UTF-8'), 0, 3, 'UTF-8');
        return (self::MONTHS[$key] ?? $default) - 1;
    }

    /** Non-empty lines, bullets and list markers dropped. */
    private static function lines(string $text): array {
        $lines = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim(preg_replace('/^[\s•·\-*–—▪●○◦>]+/u', '', $line));
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return $lines;
    }

    /** The lines under headings matching $start, up to a heading matching $stop. */
    private static function section(array $lines, string $start, string $stop): array {
        $out = [];
        $inside = false;
        foreach ($lines as $line) {
            $short = mb_strlen($line, 'UTF-8') <= 60;
            if ($short && preg_match($start, $line)) {
                $inside = true;
                // "Du er struktureret" is both a heading and a requirement.
                if (trim(preg_replace($start, '', $line), " \t:.!-") !== '') {
                    $out[] = $line;
                }
                continue;
            }
            if ($short && preg_match($stop, $line)) {
                $inside = false;
                continue;
            }
            if ($inside) {
                $out[] = $line;
            }
        }
        return $out;
    }

    private static function requirement(string $value, string $english, string $kind, bool $required, int $years = 0): array {
        return [
            'value' => $value,
            'english' => $english,
            'kind' => $kind,
            'required' => $required,
            'alt_group' => '',
            'min_years' => $years,
            'min_level' => '',
        ];
    }

    // Skills under the taxonomy's "Soft Skills" category are shown, not scored.
    private const SOFT_SKILLS_CATEGORY = 13;

    // One-word traits ("Ambitiøs", "Struktureret", "Mødestabil", "Proactive"):
    // Danish and English adjective endings.
    private const TRAIT = '/^\p{L}+(ig|lig|isk|øs|iv|som|bar|ende|eret|et|ent|ant|stabil|minded|ive|ous|ful|ed|ic)$/u';

    private function kind(array $term): string {
        if ($term['parent_id'] === self::SOFT_SKILLS_CATEGORY || isset($this->soft[$term['id']])) {
            return 'soft_skill';
        }
        $label = self::label($term, 'da');
        return !str_contains($label, ' ') && preg_match(self::TRAIT, mb_strtolower($label, 'UTF-8')) ? 'soft_skill' : 'skill';
    }

    /** The term's preferred label, remembered in $known for Cv_matcher. */
    private function name(array $term): string {
        $label = self::label($term, 'da');
        $this->known[$label] ??= $term + ['method' => 'exact'];
        return $label;
    }

    private static function label(array $term, string $lang): string {
        $any = null;
        foreach ($term['labels'] as $label) {
            if ($label['lang'] === $lang && $label['preferred']) {
                return $label['text'];
            }
            $any ??= $label['text'];
        }
        return $any ?? '';
    }

}
