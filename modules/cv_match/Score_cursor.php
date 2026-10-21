<?php
/**
 * The cursor of a list of matches shown best first: highest score, then
 * newest, then highest id. "{score}_{at}_{id}" names the last row shown, and
 * the next batch is the rows after it in that order. Rows without a score
 * yet (a found post still waiting to be matched) count as score -1, so they
 * come after every scored row. Used by the saved matches (Cv_match_model)
 * and a job search's found posts (Job_scan_model).
 */
class Score_cursor {

    /** What an unscored row sorts as: below every 0..1 score. */
    public const NO_SCORE = -1;

    /** "0.8140_1792620000_7": the cursor of the rows after that row. */
    public static function after(float|string|null $score, int|string $at, int|string $id): string {
        $score = $score === null ? self::NO_SCORE : (float) $score;
        return number_format($score, 4, '.', '') . '_' . (int) $at . '_' . (int) $id;
    }

    /** ['score', 'at', 'id'] as query params, or null when $cursor isn't one. */
    public static function place(string $cursor): ?array {
        if (!preg_match('/^(-1\.0000|[01]\.\d{4})_(\d{1,10})_(\d{1,10})$/D', $cursor, $m)) {
            return null;
        }
        return ['score' => $m[1], 'at' => (int) $m[2], 'id' => (int) $m[3]];
    }
}
