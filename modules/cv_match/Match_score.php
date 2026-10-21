<?php
/**
 * A match score as the pages show it: the 0..1 score as a percent, and the
 * legacy tag (Cv_matcher::match_tag) with its name. Every page that prints
 * a score (saved matches, job scan posts, Matchmaker cards, the apply
 * preview, Jobnet's joblog) gets it from here.
 */
class Match_score {

    public const TAG_NAMES = ['top' => 'Top match', 'good' => 'Good match', 'medium' => 'Medium match', 'poor' => 'Weak match'];

    /**
     * ['percent' => 81, 'tag' => 'good', 'tag_name' => 'Good match'], or
     * null when there is no score yet.
     */
    public static function from(float|string|null $score, ?string $tag): ?array {
        if ($score === null) {
            return null;
        }
        return [
            'percent' => self::percent($score),
            'tag' => (string) $tag,
            'tag_name' => self::tag_name((string) $tag),
        ];
    }

    /** 0.814 -> 81. */
    public static function percent(float|string $score): int {
        return (int) round((float) $score * 100);
    }

    /** "Good match" for good; a tag it doesn't know as it is. */
    public static function tag_name(string $tag): string {
        return self::TAG_NAMES[$tag] ?? $tag;
    }

    /** "81% good". */
    public static function short_label(float|string $score, string $tag): string {
        return self::percent($score) . '% ' . $tag;
    }
}
