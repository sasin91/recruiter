<?php
/**
 * The candidates table and the trongate_users row each candidate signs in as.
 */
class Candidates_model extends Model {

    private const USER_LEVEL = 3;

    /** Creates the candidate and returns its trongate_users id. */
    public function create(string $name, string $email, string $password_hash): int {
        $user_id = $this->db->insert([
            'code' => make_rand_str(32),
            'user_level_id' => self::USER_LEVEL,
        ], 'trongate_users');
        $now = time();
        $this->db->insert([
            'trongate_user_id' => $user_id,
            'name' => $name,
            'email' => $email,
            'password' => $password_hash,
            'active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'candidates');
        return $user_id;
    }

    public function email_taken(string $email): bool {
        return $this->db->get_one_where('email', $email, 'candidates') !== false;
    }

}
