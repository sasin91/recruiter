<?php
/**
 * The top of Matchmaker's list (views/post.php): how many applied, and the
 * notices about applications that aren't scored, or scored without Laya.
 */
class Candidate_list_head {

    /**
     * ['applicants' => "12 applicants", 'has_applicants', 'new', 'unscored' => "3 applications aren't" or '',
     * 'no_laya' => null or ['were' => "2 applications were", 'rank' => "they rank"]].
     */
    public static function from(array $counts, bool $laya_on): array {
        $applicants = (int) $counts['all'] + (int) $counts['rejected'];
        return [
            'applicants' => self::plural($applicants, 'applicant', 'applicants'),
            'has_applicants' => $applicants > 0,
            'new' => (int) $counts['new'],
            'unscored' => self::unscored((int) $counts['unscored']),
            'no_laya' => $laya_on ? self::no_laya((int) $counts['no_laya']) : null,
        ];
    }

    /** "3 applications aren't" (scored on this version), or '' when all are. */
    private static function unscored(int $n): string {
        return $n === 0 ? '' : self::plural($n, "application isn't", "applications aren't");
    }

    /** How to say that $n applications were scored without Laya, or null for none. */
    private static function no_laya(int $n): ?array {
        if ($n === 0) {
            return null;
        }
        return ['were' => self::plural($n, 'application was', 'applications were'), 'rank' => $n === 1 ? 'it ranks' : 'they rank'];
    }

    /** "1 applicant", "12 applicants". */
    private static function plural(int $n, string $one, string $many): string {
        return $n . ' ' . ($n === 1 ? $one : $many);
    }
}
