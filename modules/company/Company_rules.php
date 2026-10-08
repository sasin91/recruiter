<?php
/**
 * The company side's plain rules: what counts as a CVR number, who may change
 * which member, and the roles there are. No database, so the tests in tests/ can run them.
 */
class Company_rules {

    public const ROLES = ['owner' => 'Owner', 'recruiter' => 'Recruiter'];

    /**
     * A Danish CVR number: 8 digits whose weighted sum (2, 7, 6, 5, 4, 3, 2, 1)
     * is divisible by 11. Spaces and a leading "DK" are allowed when typed.
     *
     * @return string|null the 8 digits, or null when it isn't one
     */
    public static function cvr(string $typed): ?string {
        $digits = preg_replace('/\s+/', '', strtoupper(trim($typed)));
        if (str_starts_with($digits, 'DK')) {
            $digits = substr($digits, 2);
        }
        if (!preg_match('/^[1-9][0-9]{7}$/', $digits)) {
            return null;
        }
        $sum = 0;
        foreach ([2, 7, 6, 5, 4, 3, 2, 1] as $i => $weight) {
            $sum += $weight * (int) $digits[$i];
        }
        return $sum % 11 === 0 ? $digits : null;
    }

    /**
     * Why $actor may not make $change to $member, or null when they may.
     * Only owners change members; nobody changes themselves this way, and a
     * company always keeps at least one active owner.
     *
     * @param array $actor   the signed-in member (id, role)
     * @param array $member  the member being changed (id, role, active)
     * @param string $change 'deactivate', 'activate', 'make_owner' or 'make_recruiter'
     * @param int $active_owners how many active owners the company has now
     */
    public static function refuse_change(array $actor, array $member, string $change, int $active_owners): ?string {
        if ($actor['role'] !== 'owner') {
            return 'Only an owner can change members.';
        }
        if ((int) $actor['id'] === (int) $member['id']) {
            return "You can't change your own access. Ask another owner.";
        }
        $removes_owner = $member['role'] === 'owner' && (int) $member['active'] === 1
            && in_array($change, ['deactivate', 'make_recruiter'], true);
        if ($removes_owner && $active_owners <= 1) {
            return 'A company needs at least one owner.';
        }
        return in_array($change, ['deactivate', 'activate', 'make_owner', 'make_recruiter'], true)
            ? null
            : 'Unknown change.';
    }

    /** A fresh invite token: 32 hex characters. */
    public static function invite_token(): string {
        return bin2hex(random_bytes(16));
    }

    /** Whether $token has the shape invite_token() makes (checked before any lookup). */
    public static function is_invite_token(string $token): bool {
        return (bool) preg_match('/^[0-9a-f]{32}$/', $token);
    }

}
