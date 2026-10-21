<?php
require_once __DIR__ . '/Company_rules.php';

/**
 * A company member as a row of the members page (views/members.php), with
 * the changes an owner can make to them.
 */
class Member_row {

    /** ['id', 'name', 'is_me', 'email', 'role_name', 'active', 'status', 'invite_url', 'buttons' => [['label', 'fields']]]. */
    public static function from(array $m, int $me_id, bool $viewer_is_owner): array {
        $is_me = (int) $m['id'] === $me_id;
        return [
            'id' => (int) $m['id'],
            'name' => (string) $m['name'],
            'is_me' => $is_me,
            'email' => (string) $m['email'],
            'role_name' => Company_rules::ROLES[$m['role']] ?? (string) $m['role'],
            'active' => (int) $m['active'] === 1,
            'status' => self::status($m),
            'invite_url' => $viewer_is_owner && !$is_me && $m['invite_pending'] ? BASE_URL . 'company/join/' . $m['invite_token'] : null,
            'buttons' => $viewer_is_owner && !$is_me ? self::buttons($m) : [],
        ];
    }

    /** "Invited 20 Oct", "Deactivated", "Last signed in 20 Oct" or "Active". */
    private static function status(array $m): string {
        if ($m['invite_pending']) {
            return 'Invited ' . date('j M', (int) $m['invited_at']);
        }
        if ((int) $m['active'] !== 1) {
            return 'Deactivated';
        }
        return $m['last_login'] ? 'Last signed in ' . date('j M', (int) $m['last_login']) : 'Active';
    }

    /** What an owner can change about another member, as [change, label] pairs. */
    private static function buttons(array $m): array {
        if ($m['invite_pending']) {
            $changes = [['revoke', 'Withdraw invite']];
        } elseif ((int) $m['active'] !== 1) {
            $changes = [['activate', 'Reactivate']];
        } else {
            $changes = [$m['role'] === 'owner' ? ['make_recruiter', 'Make recruiter'] : ['make_owner', 'Make owner'], ['deactivate', 'Deactivate']];
        }
        return array_map(fn(array $c) => ['label' => $c[1], 'fields' => ['member_id' => (string) $m['id'], 'change' => $c[0]]], $changes);
    }
}
