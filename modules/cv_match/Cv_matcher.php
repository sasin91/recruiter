<?php
require_once __DIR__ . '/../../taxonomy/php/Taxonomy.php';

/**
 * Matching a job post against a CV, and the legacy ranking of the result.
 * Plain PHP with no Trongate dependency, so its tests (tests/*.phpt) run from the
 * CLI. The controller (Cv_match) serves it to the page as two endpoints:
 * decide() before the language model judges what is left, score() after.
 *
 * A job post is the extract_job answer: { job_title, job_title_en, company,
 * requirements: [{ value, english, kind, required, alt_group, min_years,
 * min_level }], responsibilities }. A profile is the extract_cv answer.
 * Verdicts are keyed by item id: { verdict: met|partial|missing, by, method,
 * evidence, reason }.
 */
class Cv_matcher {

    // The legacy ranking engine's rules for a job post. Formal
    // requirements weigh 150, relevant skills 50, and every other criterion
    // (job title, responsibility area) 30.
    public const WEIGHTS = ['requirements' => 150, 'skills' => 50, 'title' => 30, 'responsibilities' => 30];

    // How much a verdict counts towards a criterion. The legacy ranker only
    // knew in or out; a partial match counts half.
    private const CREDIT = ['met' => 1, 'partial' => 0.5, 'missing' => 0];

    // Taxonomy match methods precise enough to decide a requirement on their
    // own. An embedding-only hit is a guess, so those go to the model.
    private const DECISIVE = ['exact', 'synonym', 'fuzzy'];

    /** CV phrases with the taxonomy terms they name, by term id. */
    private array $cv_phrases = [];
    private array $cv_terms = [];

    /**
     * @param array $known phrases whose term is already known, phrase => term
     *   (with its match method), so map_term() needn't rank the taxonomy for
     *   them: Free_reader found the CV's and job post's phrases by label.
     */
    public function __construct(private Taxonomy $taxonomy, private array $profile, private array $known = []) {
        $this->cv_phrases = array_merge(
            $profile['skill'] ?? [],
            $profile['language'] ?? [],
            $profile['certificate'] ?? [],
            $profile['education'] ?? [],
            $profile['title'] ?? []
        );
        foreach ($this->cv_phrases as $phrase) {
            $term = $this->map_term($phrase);
            if ($term && !isset($this->cv_terms[$term['id']])) {
                $this->cv_terms[$term['id']] = ['term' => $term, 'phrase' => $phrase];
            }
        }
    }

    // The legacy ranker's tags, on rank / total.
    public static function match_tag(float $index): string {
        if ($index >= 0.85) {
            return 'top';
        }
        if ($index >= 0.8) {
            return 'good';
        }
        return $index >= 0.6 ? 'medium' : 'poor';
    }

    /**
     * The job post as the legacy ranker's criteria: one list of items per weight. The
     * post's one requirements list splits into required (the formal
     * requirements) and nice-to-have (relevant skills). Requirements
     * sharing an alt_group are alternatives ("pædagog eller pædagogisk
     * assistent") and make one item, met by its best alternative.
     *
     * Soft skills are left out: they are not scored (see soft_skills()).
     *
     * Item: { id, text, english, kind, min_years, min_level, alternatives? },
     * where each alternative is itself an item without alternatives.
     */
    public static function criteria(array $job): array {
        $lists = [true => [], false => []];
        $groups = []; // "required:alt_group" => [list, position]
        foreach ($job['requirements'] as $i => $r) {
            if ($r['kind'] === 'soft_skill') {
                continue;
            }
            $level = ($r['min_level'] ?? '') !== '' ? " ({$r['min_level']})" : '';
            $unit = [
                'id' => "r$i",
                'text' => $r['value'] . $level,
                'english' => (($r['english'] ?? '') !== '' ? $r['english'] : $r['value']) . $level,
                'kind' => $r['kind'],
                'min_years' => (int) ($r['min_years'] ?? 0),
                'min_level' => $r['min_level'] ?? '',
            ];
            $required = (bool) $r['required'];
            if (($r['alt_group'] ?? '') === '') {
                $lists[$required][] = $unit;
                continue;
            }
            $key = ($required ? 'true' : 'false') . ':' . $r['alt_group'];
            if (!isset($groups[$key])) {
                $groups[$key] = [$required, count($lists[$required])];
                $lists[$required][] = ['id' => 'g' . (count($groups) - 1), 'kind' => 'alternatives', 'alternatives' => []];
            }
            [$list, $position] = $groups[$key];
            $lists[$list][$position]['alternatives'][] = $unit;
        }
        foreach ($groups as [$list, $position]) {
            $item = &$lists[$list][$position];
            $item['text'] = implode(' / ', array_column($item['alternatives'], 'text'));
            $item['english'] = implode(' or ', array_column($item['alternatives'], 'english'));
            unset($item);
        }

        $responsibilities = [];
        foreach ($job['responsibilities'] ?? [] as $i => $text) {
            $responsibilities[] = ['id' => "a$i", 'kind' => 'responsibility', 'text' => $text];
        }
        return [
            'requirements' => $lists[true],
            'skills' => $lists[false],
            'title' => ($job['job_title'] ?? '') !== ''
                ? [['id' => 't0', 'kind' => 'title', 'text' => $job['job_title'], 'english' => $job['job_title_en'] ?? '']]
                : [],
            'responsibilities' => $responsibilities,
        ];
    }

    /** The post's soft skills, shown as tags but not scored. */
    public static function soft_skills(array $job): array {
        $out = [];
        foreach ($job['requirements'] as $r) {
            if ($r['kind'] === 'soft_skill') {
                $out[] = $r['value'];
            }
        }
        return $out;
    }

    /** What gets a verdict: every item, or each of its alternatives. */
    public static function units(array $groups): array {
        $units = [];
        foreach ($groups as $items) {
            foreach ($items as $item) {
                array_push($units, ...($item['alternatives'] ?? [$item]));
            }
        }
        return $units;
    }

    /** Verdicts on units, plus one per alternatives item: its best alternative. */
    public static function combine(array $groups, array $verdicts): array {
        $best = ['met' => 2, 'partial' => 1, 'missing' => 0];
        foreach ($groups as $items) {
            foreach ($items as $item) {
                if (!isset($item['alternatives'])) {
                    continue;
                }
                $top = null;
                foreach ($item['alternatives'] as $a) {
                    $v = $verdicts[$a['id']] ?? null;
                    if ($v && ($top === null || $best[$v['verdict']] > $best[$top['verdict']])) {
                        $top = $v;
                    }
                }
                if ($top) {
                    $verdicts[$item['id']] = $top;
                }
            }
        }
        return $verdicts;
    }

    /**
     * The taxonomy term a phrase names, when the lookup is sure: the phrase
     * is a label of the term, a synonym, or one with typos. Null otherwise.
     */
    public function map_term(string $phrase, ?string $kind = null): ?array {
        if (isset($this->known[$phrase]) && ($kind === null || $this->known[$phrase]['kind'] === $kind)) {
            return $this->known[$phrase];
        }
        foreach ($this->taxonomy->rank($phrase, null, $kind, 3) as $result) {
            $method = Taxonomy::match_method($result, $phrase);
            if (in_array($method, self::DECISIVE, true)) {
                return $result['term'] + ['method' => $method];
            }
        }
        return null;
    }

    /**
     * A verdict the taxonomy (or plain arithmetic) can give without the model,
     * or null when it can't decide.
     */
    public function decide(array $item): ?array {
        if ($item['kind'] === 'experience' && ($item['min_years'] ?? 0) > 0) {
            $need = $item['min_years'];
            $have = (int) ($this->profile['experience_years'] ?? 0);
            $verdict = $have >= $need ? 'met' : ($have >= $need / 2 ? 'partial' : 'missing');
            return ['verdict' => $verdict, 'by' => 'rule', 'method' => 'years', 'evidence' => "$have years", 'reason' => "Needs $need, CV shows $have."];
        }
        // A level ("fluent", "master's degree") is more than the taxonomy knows.
        if ($item['kind'] === 'responsibility' || $item['kind'] === 'experience' || ($item['min_level'] ?? '') !== '') {
            return null;
        }

        // Spelled the same, give or take a typo ("Kubernets").
        $wanted = implode(' ', Taxonomy::content_words($item['text']));
        foreach ($this->cv_phrases as $phrase) {
            $have = implode(' ', Taxonomy::content_words($phrase));
            if ($wanted === '' || $have === '') {
                continue;
            }
            $distance = Taxonomy::edit_distance($wanted, $have, 2);
            if ($distance <= Taxonomy::typo_budget($wanted)) {
                $method = $distance === 0 ? 'exact' : 'fuzzy';
                return ['verdict' => 'met', 'by' => 'taxonomy', 'method' => $method, 'evidence' => $phrase, 'reason' => 'Named in the CV.'];
            }
        }

        $term = $this->map_term($item['text']);
        if (!$term) {
            return null;
        }
        $label = self::preferred($term);

        if (isset($this->cv_terms[$term['id']])) {
            $same = $this->cv_terms[$term['id']];
            return ['verdict' => 'met', 'by' => 'taxonomy', 'method' => $term['method'], 'evidence' => $same['phrase'], 'reason' => "Both are \"$label\"."];
        }
        // A broader or narrower skill in the same family, or a sibling.
        foreach ($this->cv_terms as ['term' => $have, 'phrase' => $phrase]) {
            $related = ($term['parent_id'] && ($term['parent_id'] === $have['id'] || $term['parent_id'] === $have['parent_id']))
                || ($have['parent_id'] && $have['parent_id'] === $term['id']);
            if ($related) {
                return ['verdict' => 'partial', 'by' => 'taxonomy', 'method' => 'related', 'evidence' => $phrase, 'reason' => '"' . self::preferred($have) . "\" is related to \"$label\"."];
            }
        }
        return null;
    }

    /**
     * The verdicts on the requirements as one English sentence pair, the
     * state Laya's English checkpoint ranked well in the bake-off
     * (eval/laya/run_laya_summary.py). A partial match counts as missing
     * there, marked "(partly)".
     */
    public static function laya_summary(array $job, array $groups, array $verdicts): string {
        $items = $groups['requirements'];
        $met = 0;
        $missing = [];
        foreach ($items as $item) {
            $verdict = $verdicts[$item['id']]['verdict'] ?? null;
            $name = ($item['english'] ?? '') !== '' ? $item['english'] : $item['text'];
            if ($verdict === 'met') {
                $met++;
            } else {
                $missing[] = $verdict === 'partial' ? "$name (partly)" : $name;
            }
        }
        $title = ($job['job_title_en'] ?? '') !== '' ? $job['job_title_en'] : ($job['job_title'] ?? '');
        return "Job: $title. The candidate meets $met of " . count($items) . ' required qualifications. '
            . 'Missing requirements: ' . ($missing ? implode(', ', $missing) : 'none') . '.';
    }

    /**
     * The legacy ranking over the criteria: each non-empty criterion scores the
     * share of its items met (as a whole percent), times its weight; the
     * match index is the sum over the sum of weights.
     */
    public static function score(array $groups, array $verdicts): array {
        $rank = 0;
        $total = 0;
        $parts = [];
        foreach ($groups as $key => $items) {
            if (!$items) {
                continue;
            }
            $credit = 0;
            foreach ($items as $item) {
                $credit += self::CREDIT[$verdicts[$item['id']]['verdict'] ?? 'missing'];
            }
            $percentage = (int) ($credit / count($items) * 100);
            $value = (int) round($percentage / 100 * self::WEIGHTS[$key]);
            $rank += $value;
            $total += self::WEIGHTS[$key];
            $parts[$key] = ['percentage' => $percentage, 'value' => $value, 'weight' => self::WEIGHTS[$key]];
        }
        $index = $total ? $rank / $total : 0;
        return ['rank' => $rank, 'total' => $total, 'index' => $index, 'tag' => self::match_tag($index), 'parts' => $parts];
    }

    private static function preferred(array $term): string {
        foreach ($term['labels'] as $label) {
            if ($label['preferred'] && $label['lang'] === 'da') {
                return $label['text'];
            }
        }
        return $term['labels'][0]['text'];
    }

}
