<?php
require_once __DIR__ . '/../cv_match/Cv_matcher.php';
require_once __DIR__ . '/../cv_match/Match_score.php';

/**
 * An application as a card of Matchmaker's list (views/cards.php): the
 * score, who applied, Laya's answer, the scoring state, how each
 * requirement was met, the CV, and the buttons.
 *
 * $list is what every card of the batch shares: post_id, groups
 * (Cv_matcher::criteria), laya_on, and fields, the list's place
 * (tab, tag, required, after, since) that each button posts back.
 */
class Applicant_card {

    // A requirement chip's verdict, for screen readers.
    private const VERDICT_NAMES = ['met' => 'met', 'partial' => 'partly met', 'missing' => 'missing'];

    private const GROUP_HEADINGS = ['location' => 'Location', 'requirements' => 'Requirements', 'skills' => 'Nice to have'];

    public static function from(array $a, array $list): array {
        return [
            'id' => (int) $a['id'],
            'rejected' => $a['status'] === 'rejected',
            'score' => Match_score::from($a['combined_score'], $a['tag']),
            'rank' => $a['final_rank'] !== null && $a['status'] === 'in_review' ? (int) $a['final_rank'] : null,
            'name' => (string) $a['name'],
            'is_new' => (bool) $a['is_new'],
            'meta' => self::meta_line($a),
            'tag' => (string) $a['tag'],
            'tag_name' => Match_score::tag_name((string) $a['tag']),
            'flags' => self::flags($a),
            'laya' => self::laya($a),
            'laya_missing' => $list['laya_on'] && $a['laya_meets'] === null && $a['current'],
            'scoring' => self::scoring_note($a['scoring']),
            'chips' => $a['current'] ? self::chip_groups($a, $list['groups']) : [],
            'not_current' => self::not_current_note($a),
            'cv' => self::cv($a, $list['groups']),
            'buttons' => self::buttons($a, $list),
        ];
    }

    /** "Barista · Applied 21 Oct". */
    private static function meta_line(array $a): string {
        return implode(' · ', array_filter([(string) $a['current_title'], 'Applied ' . date('j M', (int) $a['submitted_at'])]));
    }

    /** ["Shortlisted", "Bookmarked"], those that apply. */
    private static function flags(array $a): array {
        return array_keys(array_filter(['Shortlisted' => (bool) $a['shortlisted_at'], 'Bookmarked' => (bool) $a['bookmarked_at']]));
    }

    /** ['text' => "Laya: likely meets every requirement (82%), …", 'unsure'], or null without an answer. */
    private static function laya(array $a): ?array {
        if ($a['laya_meets'] === null) {
            return null;
        }
        $verdict = (float) $a['laya_meets'] >= 0.5 ? 'likely meets every requirement' : 'likely misses a requirement';
        return [
            'text' => "Laya: $verdict (" . Match_score::percent($a['laya_meets']) . '%), ' . $a['laya_choice'] . '.',
            'unsure' => (bool) (int) $a['needs_human'],
        ];
    }

    /** ['text', 'warn'] for a scoring job (queue Job) still queued, running or failed, else null. */
    private static function scoring_note(?Job $scoring): ?array {
        if ($scoring === null) {
            return null;
        }
        if ($scoring->is_failed()) {
            $tries = (int) $scoring->attempts === 1 ? 'try' : 'tries';
            return ['text' => "Scoring failed after {$scoring->attempts} $tries: {$scoring->error_message} Re-score to try again.", 'warn' => true];
        }
        $state = $scoring->is_running() ? 'Being scored now.' : 'Waiting to be scored.';
        $retry = $scoring->error_message !== null ? " The last try failed ({$scoring->error_message}), so it will try again." : '';
        return ['text' => $state . $retry, 'warn' => false];
    }

    /** Why a card has no chips: scored on an older version, or never scored. '' when it has chips or a scoring note. */
    private static function not_current_note(array $a): string {
        if ($a['current']) {
            return '';
        }
        if ($a['score_id'] !== null) {
            return 'Scored on version ' . (int) $a['score_version'] . ' of the post. Re-score to match it against the requirements as they are now.';
        }
        return $a['scoring'] === null ? 'Not scored: scoring failed when it came in. Re-score to try again.' : '';
    }

    /** [['heading', 'required', 'chips' => [['text', 'verdict', 'verdict_name', 'reason']]]] for the groups that have items. */
    private static function chip_groups(array $a, array $groups): array {
        $chip_groups = [];
        foreach (self::GROUP_HEADINGS as $group => $heading) {
            // A post has a location group only when it asks for one.
            if ($groups[$group] ?? []) {
                $chips = array_map(fn(array $item) => self::chip($item, $a['details'][$item['id']] ?? null), $groups[$group]);
                $chip_groups[] = ['heading' => $heading, 'required' => $group !== 'skills', 'chips' => $chips];
            }
        }
        return $chip_groups;
    }

    private static function chip(array $item, ?array $detail): array {
        $verdict = $detail ? Cv_matcher::verdict_of_credit((float) $detail['rank']) : 'missing';
        return [
            'text' => (string) $item['text'],
            'verdict' => $verdict,
            'verdict_name' => self::VERDICT_NAMES[$verdict],
            'reason' => (string) ($detail['reason'] ?? ''),
        ];
    }

    /** The CV panel: its summary, contact line, cover letter, CV text, and the reason behind each judged item. */
    private static function cv(array $a, array $groups): array {
        return [
            'summary' => $a['cover_letter'] ? 'CV and cover letter' : 'CV',
            'email' => (string) $a['email'],
            'phone' => (string) $a['phone'],
            'cover_letter' => (string) $a['cover_letter'],
            'text' => (string) $a['raw_text'],
            'reasons' => $a['current'] && $a['details'] ? self::reasons($a['details'], $groups) : [],
        ];
    }

    /** [['item', 'reason', 'judged_by']] for each requirement and skill with a detail. */
    private static function reasons(array $details, array $groups): array {
        $reasons = [];
        foreach (array_merge($groups['requirements'], $groups['skills']) as $item) {
            if ($detail = $details[$item['id']] ?? null) {
                $reasons[] = ['item' => (string) $item['text'], 'reason' => (string) $detail['reason'], 'judged_by' => self::judged_by((string) $detail['stage'])];
            }
        }
        return $reasons;
    }

    /** "AI", "not judged", or the stage as it is (taxonomy, rule). */
    private static function judged_by(string $stage): string {
        return match ($stage) {
            'llm' => 'AI',
            'none' => 'not judged',
            default => $stage,
        };
    }

    /** The card's buttons as [['label', 'class', 'url', 'fields']], in the order they show. */
    private static function buttons(array $a, array $list): array {
        $actions = array_filter([
            self::shortlist_action($a),
            self::bookmark_action($a),
            self::reject_action($a),
            self::needs_rescore($a, $list['laya_on']) ? ['rescore', 'Re-score', ''] : null,
        ]);
        return array_values(array_map(fn(array $action) => self::button($a, $list, ...$action), $actions));
    }

    /** [action, label, extra class], or null when the card doesn't offer it. */
    private static function shortlist_action(array $a): ?array {
        if ($a['status'] !== 'in_review') {
            return null;
        }
        return $a['shortlisted_at'] ? ['unshortlist', 'Remove from shortlist', ''] : ['shortlist', 'Shortlist', 'primary'];
    }

    private static function bookmark_action(array $a): ?array {
        if ($a['status'] === 'rejected') {
            return null;
        }
        return $a['bookmarked_at'] ? ['unbookmark', 'Remove bookmark', ''] : ['bookmark', 'Bookmark', ''];
    }

    private static function reject_action(array $a): ?array {
        return match ($a['status']) {
            'in_review' => ['reject', 'Reject', ''],
            'rejected' => ['unreject', 'Undo reject', ''],
            default => null,
        };
    }

    /** Re-score an application scored on an old version, or one Laya didn't answer for. */
    private static function needs_rescore(array $a, bool $laya_on): bool {
        return $a['status'] === 'in_review' && (!$a['current'] || ($laya_on && $a['laya_meets'] === null));
    }

    private static function button(array $a, array $list, string $action, string $label, string $class): array {
        return [
            'label' => $label,
            'class' => trim("button small $class"),
            'url' => 'matchmaker/submit_action/' . (int) $list['post_id'] . '/' . (int) $a['id'],
            'fields' => ['action' => $action] + $list['fields'],
        ];
    }
}
