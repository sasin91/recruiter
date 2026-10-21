<?php
require_once __DIR__ . '/Match_score.php';

/**
 * The tailored résumé's own match against the post next to the CV's, as the
 * résumé box prints it (views/resume_comparison.php): both scores, and the
 * résumé's saved match to open.
 */
class Resume_comparison {

    public const LINE = 'The tailored résumé scores %s against this post; your CV scored %s.';
    public const SAVED = 'Saved as its own match.';
    public const OPEN = 'Open it';

    /**
     * ['line' => "The tailored résumé scores 81% good against this post; your
     * CV scored 62% medium.", 'open_id' => 12]. $cv_match and $resume_match:
     * saved matches (Cv_match_model::find) of the CV and of the résumé.
     */
    public static function from(array $cv_match, array $resume_match): array {
        return [
            'line' => sprintf(self::LINE, self::score($resume_match), self::score($cv_match)),
            'open_id' => (int) $resume_match['id'],
        ];
    }

    private static function score(array $match): string {
        return Match_score::short_label($match['score'], (string) $match['tag']);
    }
}
