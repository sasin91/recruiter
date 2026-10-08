<?php
/**
 * The plain rules of a job post, with no database, so tests/ can run them:
 * which status a post moves to, how a reading of the post (Match_prompts or
 * Free_reader) and the review form become job_post_terms rows, and how the
 * rows become the job post shape Cv_matcher scores.
 *
 * A row: { kind, raw_text, english, is_required, alt_group (int|null),
 * min_years (int|null), min_level (string|null) }. Kinds: title (one row),
 * responsibility_area, and the requirement kinds in KINDS.
 */
class Job_post_rules {

    // The requirement kinds, as the review form names them. soft_skill is
    // shown on the post but never scored.
    public const KINDS = [
        'skill' => 'Skill',
        'experience' => 'Experience',
        'education' => 'Education',
        'certificate' => 'Certificate',
        'language' => 'Language',
        'soft_skill' => 'Personal quality',
    ];

    public const WORK_HOURS = ['' => 'Not stated', 'full_time' => 'Full time', 'part_time' => 'Part time'];
    public const WORKPLACE = ['' => 'Not stated', 'onsite' => 'On site', 'hybrid' => 'Hybrid', 'remote' => 'Remote'];
    public const LANGUAGES = ['da' => 'Danish', 'en' => 'English'];

    // What each action does to a post: from status => to status.
    private const TRANSITIONS = [
        'publish' => ['draft' => 'active'],
        'pause' => ['active' => 'paused'],
        'resume' => ['paused' => 'active'],
        'close' => ['active' => 'closed', 'paused' => 'closed'],
        'reopen' => ['closed' => 'active'],
        'archive' => ['closed' => 'archived'],
    ];

    // Longest texts the columns take.
    private const MAX_TEXT = 255;
    private const MAX_LEVEL = 24;
    private const MAX_YEARS = 50;

    /** The status $action moves a post in $status to, or null when it can't. */
    public static function next_status(string $status, string $action): ?string {
        return self::TRANSITIONS[$action][$status] ?? null;
    }

    /** The actions a post in $status offers, in the order the page shows them. */
    public static function actions(string $status): array {
        return array_keys(array_filter(self::TRANSITIONS, fn(array $from) => isset($from[$status])));
    }

    /** Why a post can't be published yet, or null when it can. */
    public static function refuse_publish(string $title, array $rows): ?string {
        if (trim($title) === '') {
            return 'Give the post a title first.';
        }
        foreach ($rows as $row) {
            if (isset(self::KINDS[$row['kind']]) && $row['kind'] !== 'soft_skill') {
                return null;
            }
        }
        return 'Add at least one requirement first: candidates are matched on them.';
    }

    /** A fresh public link token: 12 characters, letters and digits. */
    public static function public_token(): string {
        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $token = '';
        for ($i = 0; $i < 12; $i++) {
            $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $token;
    }

    /** Whether $token has the shape public_token() makes. */
    public static function is_public_token(string $token): bool {
        return (bool) preg_match('/^[a-zA-Z0-9]{12}$/', $token);
    }

    /** The text job_post_terms.normalised keeps: lower case, single spaces. */
    public static function normalise(string $text): string {
        $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)), 'UTF-8');
        return mb_substr($text, 0, 191, 'UTF-8');
    }

    /**
     * Rows from a reading of the post: Match_prompts::read_job's answer, or
     * Free_reader::job's. alt_group labels ("a", "b") become numbers.
     */
    public static function from_reading(array $job): array {
        $rows = [];
        $title = trim((string) ($job['job_title'] ?? '')) ?: trim((string) ($job['heading'] ?? ''));
        if ($title !== '') {
            $rows[] = self::row('title', $title, (string) ($job['job_title_en'] ?? ''));
        }
        $groups = [];
        foreach ($job['requirements'] ?? [] as $r) {
            $kind = (string) ($r['kind'] ?? '');
            $value = trim((string) ($r['value'] ?? ''));
            if (!isset(self::KINDS[$kind]) || $value === '') {
                continue;
            }
            $label = trim((string) ($r['alt_group'] ?? ''));
            $group = null;
            if ($label !== '') {
                $group = $groups[$label] ??= count($groups) + 1;
            }
            $rows[] = self::row($kind, $value, (string) ($r['english'] ?? ''), (bool) ($r['required'] ?? true), $group,
                (int) ($r['min_years'] ?? 0), (string) ($r['min_level'] ?? ''));
        }
        foreach ($job['responsibilities'] ?? [] as $text) {
            if (trim((string) $text) !== '') {
                $rows[] = self::row('responsibility_area', (string) $text);
            }
        }
        return self::share_required($rows);
    }

    /**
     * Rows from the review form: title, title_en, responsibilities (one per
     * line) and requirements, each { value, english, kind, required, group
     * (a letter or empty), min_years, min_level, remove }. Empty and removed
     * rows are dropped.
     *
     * @return array{0: array, 1: string[]} the rows and what was wrong
     */
    public static function from_form(array $form): array {
        $errors = [];
        $rows = [];
        $title = trim((string) ($form['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'The post needs a title.';
        } elseif (mb_strlen($title, 'UTF-8') > self::MAX_TEXT) {
            $errors[] = 'The title is too long.';
        } else {
            $rows[] = self::row('title', $title, (string) ($form['title_en'] ?? ''));
        }

        $groups = [];
        foreach (array_values((array) ($form['requirements'] ?? [])) as $i => $r) {
            if (!is_array($r) || !empty($r['remove'])) {
                continue;
            }
            $value = trim((string) ($r['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $n = $i + 1;
            $kind = (string) ($r['kind'] ?? '');
            $years = trim((string) ($r['min_years'] ?? ''));
            $level = trim((string) ($r['min_level'] ?? ''));
            $label = strtoupper(trim((string) ($r['group'] ?? '')));
            if (!isset(self::KINDS[$kind])) {
                $errors[] = "Requirement $n: pick what kind it is.";
                continue;
            }
            if (mb_strlen($value, 'UTF-8') > self::MAX_TEXT || mb_strlen((string) ($r['english'] ?? ''), 'UTF-8') > self::MAX_TEXT) {
                $errors[] = "Requirement $n is too long.";
                continue;
            }
            if ($years !== '' && (!ctype_digit($years) || (int) $years > self::MAX_YEARS)) {
                $errors[] = "Requirement $n: years is a whole number up to " . self::MAX_YEARS . '.';
                continue;
            }
            if (mb_strlen($level, 'UTF-8') > self::MAX_LEVEL) {
                $errors[] = "Requirement $n: the level is at most " . self::MAX_LEVEL . ' characters.';
                continue;
            }
            if ($label !== '' && !preg_match('/^[A-Z]$/', $label)) {
                $errors[] = "Requirement $n: an OR-group is one letter, like A.";
                continue;
            }
            $group = $label === '' ? null : ($groups[$label] ??= count($groups) + 1);
            $rows[] = self::row($kind, $value, (string) ($r['english'] ?? ''), !empty($r['required']), $group, (int) $years, $level);
        }

        foreach (preg_split('/\R/u', (string) ($form['responsibilities'] ?? '')) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $rows[] = self::row('responsibility_area', mb_substr($line, 0, self::MAX_TEXT, 'UTF-8'));
            }
        }
        return [self::share_required($rows), $errors];
    }

    /**
     * The rows as the job post Cv_matcher scores: { job_title, job_title_en,
     * requirements: [{ value, english, kind, required, alt_group, min_years,
     * min_level, row }], responsibilities }. row is the requirement's index
     * in $rows, so a verdict can be stored against its job_post_terms row.
     */
    public static function to_job(array $rows): array {
        $job = ['job_title' => '', 'job_title_en' => '', 'company' => '', 'requirements' => [], 'responsibilities' => []];
        foreach ($rows as $i => $row) {
            if ($row['kind'] === 'title') {
                $job['job_title'] = $row['raw_text'];
                $job['job_title_en'] = (string) $row['english'];
            } elseif ($row['kind'] === 'responsibility_area') {
                $job['responsibilities'][] = $row['raw_text'];
            } elseif (isset(self::KINDS[$row['kind']])) {
                $job['requirements'][] = [
                    'value' => $row['raw_text'],
                    'english' => (string) $row['english'],
                    'kind' => $row['kind'],
                    'required' => (bool) $row['is_required'],
                    'alt_group' => $row['alt_group'] === null ? '' : (string) $row['alt_group'],
                    'min_years' => (int) $row['min_years'],
                    'min_level' => (string) $row['min_level'],
                    'row' => $i,
                ];
            }
        }
        return $job;
    }

    /**
     * Whether two row lists ask for the same things: a change to what
     * candidates are matched on (not to the pitch or the order of
     * responsibilities) makes a new version of a live post.
     */
    public static function same_requirements(array $a, array $b): bool {
        $key = fn(array $rows) => array_map(
            fn(array $r) => [$r['kind'], $r['raw_text'], (string) $r['english'], (int) $r['is_required'], $r['alt_group'], (int) $r['min_years'], (string) $r['min_level']],
            array_values(array_filter($rows, fn(array $r) => $r['kind'] !== 'responsibility_area'))
        );
        return $key($a) == $key($b);
    }

    /** The OR-group letter the review form shows for alt_group $n (1 = A). */
    public static function group_letter(?int $n): string {
        return $n === null || $n < 1 || $n > 26 ? '' : chr(64 + $n);
    }

    private static function row(string $kind, string $text, string $english = '', bool $required = false,
            ?int $group = null, int $years = 0, string $level = ''): array {
        $english = trim($english);
        $level = trim($level);
        return [
            'kind' => $kind,
            'raw_text' => mb_substr(trim($text), 0, self::MAX_TEXT, 'UTF-8'),
            'english' => $english === '' ? null : mb_substr($english, 0, self::MAX_TEXT, 'UTF-8'),
            'is_required' => $required ? 1 : 0,
            'alt_group' => $group,
            'min_years' => $years > 0 ? min($years, self::MAX_YEARS) : null,
            'min_level' => $level === '' ? null : mb_substr($level, 0, self::MAX_LEVEL, 'UTF-8'),
        ];
    }

    /** Alternatives share is_required: the group's first row decides. */
    private static function share_required(array $rows): array {
        $required = [];
        foreach ($rows as &$row) {
            if ($row['alt_group'] !== null) {
                $row['is_required'] = $required[$row['alt_group']] ??= $row['is_required'];
            }
        }
        unset($row);
        return $rows;
    }

}
