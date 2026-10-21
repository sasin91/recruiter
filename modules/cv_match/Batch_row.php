<?php
/**
 * A match as a row of "Jobs ranked by match" (views/batch_row.php), for
 * several job posts matched with one CV: the score, the job, where the post
 * is from, when it was matched before (a post already matched with this CV
 * isn't matched again) and what the post asks of applicants, over the
 * result's own body (Match_result). Rows are ranked by `percent`.
 */
class Batch_row {

    public const UNTITLED = 'Job post';
    public const NOT_AGAIN = 'not matched again';
    public const APPLY_CHIPS = [false => 'Apply by email', true => 'Special instructions'];

    /**
     * ['percent' => 81, 'tag' => 'good', 'heading' => ..., 'host' =>
     * 'jobindex.dk' or '', 'matched_at' => ISO time or '', 'apply_chip' =>
     * ['label', 'trap'] or null, 'line' => the parts, or the points and
     * "not matched again"]. $result: Match_result; $job_url: where the post
     * was read from.
     */
    public static function from(array $result, string $job_url): array {
        return [
            'percent' => $result['score']['percent'],
            'tag' => $result['score']['tag'],
            'heading' => $result['heading'] !== '' ? $result['heading'] : self::UNTITLED,
            'host' => (string) (parse_url($job_url, PHP_URL_HOST) ?? ''),
            'matched_at' => $result['saved_at'],
            'apply_chip' => self::apply_chip($result['apply']),
            'line' => $result['saved_at'] !== '' ? $result['parts'] . ' · ' . self::NOT_AGAIN : $result['parts'],
        ];
    }

    private static function apply_chip(?array $apply): ?array {
        return $apply ? ['label' => self::APPLY_CHIPS[$apply['trap']], 'trap' => $apply['trap']] : null;
    }
}
