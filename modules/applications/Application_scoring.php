<?php
require_once __DIR__ . '/../cv_match/Cv_matcher.php';

/**
 * Scoring an application against its job post, for SmartMatch. Plain PHP
 * (tests/*.phpt run it from the CLI); Applications runs it and saves what it
 * gives.
 *
 * The match is the CV checker's: the taxonomy decides what it can
 * (Cv_matcher::decide), the language model judges the rest on the company's
 * key, and the legacy ranker scores the verdicts. Laya then reads the
 * verdicts and the post's candidates are ordered by what it says.
 */
class Application_scoring {

    // match_scores.ranking_version for this way of scoring.
    public const RANKING_VERSION = 'legacy-1';

    // Laya probabilities this close to a coin toss are flagged for a person.
    public const UNSURE = [0.4, 0.6];

    // What the CV lists, by job_application_terms / candidate_profile_terms kind.
    private const LISTS = ['titles' => 'title', 'skills' => 'skill', 'languages' => 'language', 'certifications' => 'certificate', 'education' => 'education'];

    private const CREDIT = ['met' => 1, 'partial' => 0.5, 'missing' => 0];

    /**
     * A read CV (extract_cv's shape) as term rows: { kind, raw_text, years },
     * one per phrase, duplicates dropped, plus an `experience` row for the
     * years of work.
     */
    public static function profile_terms(array $profile): array {
        $rows = [];
        $seen = [];
        foreach (self::LISTS as $list => $kind) {
            foreach ($profile[$list] ?? [] as $phrase) {
                $phrase = trim(mb_substr(preg_replace('/\s+/u', ' ', (string) $phrase), 0, 255, 'UTF-8'));
                $key = $kind . ':' . mb_strtolower($phrase, 'UTF-8');
                if ($phrase === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $rows[] = ['kind' => $kind, 'raw_text' => $phrase, 'years' => null];
            }
        }
        $years = max(0, min(60, (int) ($profile['experience_years'] ?? 0)));
        $rows[] = ['kind' => 'experience', 'raw_text' => "$years years of work", 'years' => $years];
        return $rows;
    }

    /** Term rows back in extract_cv's shape, for Cv_matcher. */
    public static function profile_from_terms(array $terms): array {
        $profile = array_fill_keys(array_keys(self::LISTS), []) + ['experience_years' => 0, 'responsibilities' => []];
        $lists = array_flip(self::LISTS);
        foreach ($terms as $term) {
            if ($term['kind'] === 'experience') {
                $profile['experience_years'] = (int) $term['years'];
            } elseif (isset($lists[$term['kind']])) {
                $profile[$lists[$term['kind']]][] = $term['raw_text'];
            }
        }
        return $profile;
    }

    /**
     * The verdict on a unit nobody could decide: the taxonomy didn't find it
     * and the model didn't judge it (no company key, or the call failed).
     */
    public static function not_found(string $why): array {
        return ['verdict' => 'missing', 'by' => 'none', 'method' => 'not_found', 'evidence' => '', 'reason' => $why];
    }

    /** The model's answer (Match_prompts::judge) as verdicts by unit id, for the ids asked about. */
    public static function judged(array $answer, array $ids): array {
        $wanted = array_fill_keys($ids, true);
        $verdicts = [];
        foreach ($answer['verdicts'] ?? [] as $v) {
            $id = (string) ($v['id'] ?? '');
            if (!isset($wanted[$id]) || !isset(self::CREDIT[$v['verdict'] ?? ''])) {
                continue;
            }
            $verdicts[$id] = [
                'verdict' => $v['verdict'],
                'by' => 'llm',
                'method' => 'judgement',
                'evidence' => (string) ($v['evidence'] ?? ''),
                'reason' => (string) ($v['reason'] ?? ''),
            ];
        }
        return $verdicts;
    }

    /**
     * The three scores match_scores keeps, as match indexes (0-1):
     * deterministic counts only what the taxonomy and rules decided (the
     * model's verdicts as missing), llm is the score with the model's
     * verdicts (null when it judged nothing), and combined is the one the
     * post's list uses: llm when there is one, else deterministic.
     *
     * @param array $verdicts unit verdicts (before Cv_matcher::combine)
     */
    public static function scores(array $groups, array $verdicts): array {
        $without_model = array_map(
            fn(array $v) => $v['by'] === 'llm' ? self::not_found('Judged by the model.') : $v,
            $verdicts
        );
        $deterministic = Cv_matcher::score($groups, Cv_matcher::combine($groups, $without_model));
        $judged = (bool) array_filter($verdicts, fn(array $v) => $v['by'] === 'llm');
        $full = Cv_matcher::score($groups, Cv_matcher::combine($groups, $verdicts));
        return [
            'deterministic' => $deterministic['index'],
            'llm' => $judged ? $full['index'] : null,
            'combined' => $full['index'],
            'tag' => $full['tag'],
            'result' => $full,
        ];
    }

    /**
     * match_score_details rows: one per criterion, then one per alternative
     * of an OR-group ("g0/r3"), each with its job_post_terms id where it has
     * one. rank is the verdict's credit (1, 0.5, 0), weight its criterion's.
     *
     * @param array $job Job_post_rules::to_job($rows), whose requirements carry `row`
     * @param array $rows the post's job_post_terms rows (with id)
     * @param array $verdicts combined verdicts (Cv_matcher::combine)
     */
    public static function details(array $groups, array $verdicts, array $job, array $rows): array {
        $term_ids = self::term_ids($job, $rows);
        $details = [];
        foreach ($groups as $group => $items) {
            foreach ($items as $item) {
                $details[] = self::detail($item['id'], $item['id'], $verdicts, $term_ids, Cv_matcher::WEIGHTS[$group]);
                foreach ($item['alternatives'] ?? [] as $alternative) {
                    $details[] = self::detail("{$item['id']}/{$alternative['id']}", $alternative['id'], $verdicts, $term_ids, Cv_matcher::WEIGHTS[$group]);
                }
            }
        }
        return $details;
    }

    /** Criterion id (r0, t0, a0) => job_post_terms id. */
    public static function term_ids(array $job, array $rows): array {
        $ids = [];
        foreach ($job['requirements'] as $i => $requirement) {
            $ids["r$i"] = isset($rows[$requirement['row']]['id']) ? (int) $rows[$requirement['row']]['id'] : null;
        }
        $responsibility = 0;
        foreach ($rows as $row) {
            if ($row['kind'] === 'title') {
                $ids['t0'] = isset($row['id']) ? (int) $row['id'] : null;
            } elseif ($row['kind'] === 'responsibility_area') {
                $ids['a' . $responsibility++] = isset($row['id']) ? (int) $row['id'] : null;
            }
        }
        return $ids;
    }

    /** Whether Laya's probability is too close to call. */
    public static function needs_human(?float $meets): bool {
        return $meets !== null && $meets >= self::UNSURE[0] && $meets <= self::UNSURE[1];
    }

    /**
     * The post's applications in their list order, as ids. Laya first: those
     * it thinks meet every requirement (probability >= 0.5), then by its fit,
     * then by the legacy score; earlier applications first on a tie. Without
     * Laya's answer an application sorts by its score after those with one.
     *
     * @param array $scores [{ id, meets: ?float, fit: ?float, combined: float, submitted_at: int }]
     */
    public static function order(array $scores): array {
        usort($scores, function (array $a, array $b) {
            $key = fn(array $s) => [
                $s['meets'] === null ? 0 : ($s['meets'] >= 0.5 ? 2 : 1),
                round((float) ($s['fit'] ?? 0), 3),
                round((float) $s['combined'], 4),
            ];
            return [$key($b), $a['submitted_at'], $a['id']] <=> [$key($a), $b['submitted_at'], $b['id']];
        });
        return array_map(fn(array $s) => (int) $s['id'], $scores);
    }

    private static function detail(string $criterion, string $unit, array $verdicts, array $term_ids, int $weight): array {
        $v = $verdicts[$unit] ?? self::not_found('No verdict.');
        $reason = trim((string) $v['reason']);
        if (($v['evidence'] ?? '') !== '' && $v['verdict'] !== 'missing' && $v['by'] !== 'rule') {
            $reason .= ' CV: "' . $v['evidence'] . '"';
        }
        return [
            'stage' => $v['by'],
            'criterion' => $criterion,
            'job_post_term_id' => $term_ids[$unit] ?? null,
            'rule' => mb_substr((string) $v['method'], 0, 16, 'UTF-8'),
            'weight' => $weight,
            'rank' => self::CREDIT[$v['verdict']],
            'passed' => $v['verdict'] === 'met' ? 1 : 0,
            'reason' => mb_substr(trim($reason), 0, 500, 'UTF-8'),
        ];
    }

}
