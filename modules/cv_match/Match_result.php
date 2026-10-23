<?php
require_once __DIR__ . '/Match_score.php';
require_once __DIR__ . '/Verdict_row.php';
require_once __DIR__ . '/Apply_notice.php';
require_once __DIR__ . '/Location_notice.php';
require_once __DIR__ . '/Reject_button.php';

/**
 * A match as the CV match page prints its result (views/result.php): the
 * score in its ring, the job, how the score is made up, a location the
 * candidate likely misses (Location_notice), what the post asks of
 * applicants (Apply_notice), the Reject button of a saved match
 * (Reject_button), the requirements by group with their
 * verdicts (Verdict_row) and a filter by verdict, the soft skills, Laya's
 * reading and, for a saved match, the application and résumé panel
 * (Match_documents).
 *
 * from() takes a match just made (quick_match, match); from_saved() a saved
 * one (Cv_match_model::find), which keeps less: no part weights, no
 * alternatives, no soft skills.
 */
class Match_result {

    public const GROUP_NAMES = [
        'requirements' => 'Requirements',
        'skills' => 'Nice to have',
        'title' => 'Job title',
        'responsibilities' => 'Responsibilities',
        'location' => 'Location',
    ];
    public const LOCATION_NOTE = 'not weighed · a miss caps the score';
    public const FILTERS = ['missing' => 'Missing', 'partial' => 'Partial', 'met' => 'Met'];
    public const ALL = 'All';
    public const LAYA_FAILED = 'No Laya reading this time. %s The score above doesn\'t depend on it.';
    private const CHIPS_SHOWN = 30;

    /**
     * A match just made. $job: the post as read; $answer: quick_match's or
     * _ai_match's answer; $writer: the page can write the application;
     * $documents: Match_documents::from, or null for the free match;
     * $laya_input: what Laya was given, shown for the AI match.
     */
    public static function from(array $job, array $answer, bool $writer, ?array $documents, string $laya_input = ''): array {
        $sections = self::sections($answer['groups'], $answer['verdicts'], $answer['result']['parts']);
        return [
            'saved_id' => $answer['saved_id'] ?? null,
            'score' => Match_score::from($answer['result']['index'], $answer['result']['tag']),
            'heading' => self::heading((string) ($job['job_title'] ?? '') ?: (string) ($job['heading'] ?? ''), (string) ($job['company'] ?? '')),
            'parts' => self::parts($answer['result']['parts']),
            'saved_at' => '',
            'reject' => Reject_button::from(isset($answer['saved_id']) ? (int) $answer['saved_id'] : null, null),
            'location' => Location_notice::from($answer['groups'], $answer['verdicts']),
            'apply' => Apply_notice::from($answer['apply_instructions'] ?? null, $writer),
            'filters' => self::filters($sections),
            'sections' => $sections,
            'soft_skills' => $answer['soft_skills'],
            'laya' => self::laya($answer['laya'] ?? null, (string) ($answer['laya_error'] ?? '')),
            'laya_input' => $laya_input,
            'documents' => $documents,
            'job_url' => '',
            'job_text' => '',
        ];
    }

    /** A saved match with its items; the page puts its post back in the job box. */
    public static function from_saved(array $match, bool $writer, array $documents): array {
        $sections = self::saved_sections($match['items']);
        return [
            'saved_id' => (int) $match['id'],
            'score' => Match_score::from($match['score'], (string) $match['tag']),
            'heading' => self::heading((string) $match['job_title'], (string) $match['company']),
            'parts' => "{$match['points']}/{$match['max_points']} points",
            'saved_at' => gmdate('Y-m-d\\TH:i:s\\Z', (int) $match['created_at']),
            'reject' => Reject_button::from((int) $match['id'], $match['rejected_at'] ?? null),
            'location' => Location_notice::from_saved($match['items']),
            'apply' => Apply_notice::from($match['apply_instructions'] ?? null, $writer),
            'filters' => self::filters($sections),
            'sections' => $sections,
            'soft_skills' => [],
            'laya' => self::laya(null, ''),
            'laya_input' => '',
            'documents' => $documents,
            'job_url' => (string) ($match['job_url'] ?? ''),
            'job_text' => (string) $match['job_text'],
        ];
    }

    /**
     * What the free match found in the CV, for the CV panel: ['status' =>
     * "Found 6 years of experience, 12 skills and 2 job titles we know.",
     * 'terms' => the first titles and skills, 'more' => "and 4 more" or ''].
     */
    public static function cv_found(array $profile): array {
        $found = [...$profile['title'], ...$profile['skill']];
        $years = $profile['experience_years'] ? "{$profile['experience_years']} years of experience, " : '';
        return [
            'status' => 'Found ' . $years . count($profile['skill']) . ' skills and ' . count($profile['title']) . ' job titles we know.',
            'terms' => array_slice($found, 0, self::CHIPS_SHOWN),
            'more' => count($found) > self::CHIPS_SHOWN ? 'and ' . (count($found) - self::CHIPS_SHOWN) . ' more' : '',
        ];
    }

    /** "Job title · Company". */
    private static function heading(string $title, string $company): string {
        return implode(' · ', array_filter([$title, $company], fn($part) => $part !== ''));
    }

    /** "Requirements 80% (120/150) · Nice to have 50% (25/50) · ...". */
    private static function parts(array $parts): string {
        $out = [];
        foreach ($parts as $key => $part) {
            $out[] = self::GROUP_NAMES[$key] . " {$part['percentage']}% ({$part['value']}/{$part['weight']})";
        }
        return implode(' · ', $out);
    }

    /** The groups with requirements, each as ['heading', 'note', 'rows'], missing first. */
    private static function sections(array $groups, array|object $verdicts, array $parts): array {
        $verdicts = (array) $verdicts;
        $sections = [];
        foreach ($groups as $key => $items) {
            if ($items) {
                $sections[] = [
                    'heading' => self::GROUP_NAMES[$key],
                    'note' => self::note($parts[$key] ?? null),
                    'rows' => Verdict_row::sorted(array_map(fn(array $item) => Verdict_row::from($item, $verdicts), $items)),
                ];
            }
        }
        return $sections;
    }

    /** "80% · weight 150", or the location group's note: it has no part. */
    private static function note(?array $part): string {
        return $part !== null ? "{$part['percentage']}% · weight {$part['weight']}" : self::LOCATION_NOTE;
    }

    /** A saved match's items by criterion, in the order they come, missing first. */
    private static function saved_sections(array $items): array {
        $by_criterion = [];
        foreach ($items as $item) {
            $by_criterion[$item['criterion']][] = Verdict_row::from_saved($item);
        }
        $sections = [];
        foreach ($by_criterion as $criterion => $rows) {
            $sections[] = ['heading' => self::GROUP_NAMES[$criterion] ?? (string) $criterion, 'note' => '', 'rows' => Verdict_row::sorted($rows)];
        }
        return $sections;
    }

    /** The filter buttons: All, then each verdict there is, with how many. */
    private static function filters(array $sections): array {
        $verdicts = array_column(array_merge(...array_column($sections, 'rows')), 'verdict');
        if (!$verdicts) {
            return [];
        }
        $counts = array_count_values($verdicts);
        $filters = [['key' => 'all', 'label' => self::ALL, 'count' => count($verdicts)]];
        foreach (self::FILTERS as $key => $label) {
            if (isset($counts[$key])) {
                $filters[] = ['key' => $key, 'label' => $label, 'count' => $counts[$key]];
            }
        }
        return $filters;
    }

    /** Laya's answer as a line, ['text', 'failed']; no text without Laya. */
    private static function laya(?array $laya, string $error): array {
        if ($laya) {
            $meets = (int) round($laya['meets'] * 100);
            $fit = number_format((float) $laya['fit'], 1);
            return ['text' => "Laya: $meets% likely to meet every requirement, fit $fit of 4 ({$laya['fit_label']}).", 'failed' => false];
        }
        return ['text' => $error !== '' ? sprintf(self::LAYA_FAILED, $error) : '', 'failed' => $error !== ''];
    }
}
