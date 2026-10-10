<?php
require_once __DIR__ . '/Resource.php';
require_once __DIR__ . '/../company/Company_rules.php';

/**
 * Every table the admin panel shows under resources/, grouped as in its
 * index. A table with its own admin page (queue/manage,
 * trongate_administrators/manage) is in ELSEWHERE instead; every table in
 * db/schema.sql is in one of the two, and every column of it is listed
 * (tests/every_table_and_column_is_covered.phpt), so a new column means
 * deciding whether the admin sees it.
 *
 * Accounts (companies, members, candidates) can be edited, never deleted:
 * switch active off instead. What the app writes (posts, applications,
 * scores, actions, CV checker results) is read-only, and the parts of it
 * that are moderated can be deleted. The taxonomy is read-only: matching
 * reads the index built from db/taxonomy.sql, not these tables.
 */
final class Resource_catalog {

    /** Tables with an admin page of their own: table => its URL. */
    public const ELSEWHERE = [
        'trongate_administrators' => 'trongate_administrators/manage',
        'queue_jobs' => 'queue/manage',
        'queue_workers' => 'queue/manage',
    ];

    /** @var array<string, Resource>|null */
    private static ?array $all = null;

    /** @return array<string, Resource> table => resource, in menu order */
    public static function all(): array {
        if (self::$all !== null) {
            return self::$all;
        }
        $list = [...self::companies(), ...self::candidates(), ...self::jobs(), ...self::scoring(), ...self::cv_checker(), ...self::taxonomy(), ...self::sign_in()];
        $all = [];
        foreach ($list as $resource) {
            $all[$resource->table] = $resource;
        }
        return self::$all = $all;
    }

    public static function find(string $table): ?Resource {
        return self::all()[$table] ?? null;
    }

    /** @return array<string, Resource[]> group => its resources */
    public static function groups(): array {
        $groups = [];
        foreach (self::all() as $resource) {
            $groups[$resource->group][] = $resource;
        }
        return $groups;
    }

    /**
     * The lists that point at a row of $table: [resource, column] for every
     * ref column to it, for the record page's "Related" links.
     *
     * @return array<int, array{0: Resource, 1: string}>
     */
    public static function referring_to(string $table): array {
        $found = [];
        foreach (self::all() as $resource) {
            foreach ($resource->fields as $column => $field) {
                if ($field->kind === 'ref' && $field->table === $table) {
                    $found[] = [$resource, $column];
                }
            }
        }
        return $found;
    }

    private static function companies(): array {
        $g = 'Companies';
        return [
            (new Resource('companies', 'Companies', $g, 'Companies that signed up. Switch active off to shut their staff out.', [
                'id' => Field::id(),
                'name' => Field::text(255),
                'cvr_number' => Field::text(8, true, self::cvr(...)),
                'company_type_term_id' => Field::ref('terms', true),
                'contact_email' => Field::text(255, true, self::email(...)),
                'active' => Field::bool(),
                'created_at' => Field::time(),
                'updated_at' => Field::time(),
            ]))->titled('name')->lists('id', 'name', 'cvr_number', 'contact_email', 'active', 'created_at')
                ->searches('name', 'cvr_number', 'contact_email')
                ->edits('name', 'cvr_number', 'contact_email', 'active'),

            (new Resource('company_members', 'Company members', $g, "Companies' staff. Switch active off to stop one signing in.", [
                'id' => Field::id(),
                'company_id' => Field::ref('companies'),
                'trongate_user_id' => Field::ref('trongate_users'),
                'name' => Field::text(255),
                'email' => Field::text(255, false, self::email(...)),
                'password' => Field::secret(),
                'role' => Field::code(array_keys(Company_rules::ROLES)),
                'active' => Field::bool(),
                'invite_token' => Field::secret(),
                'invited_at' => Field::time(true),
                'num_logins' => Field::int(),
                'last_login' => Field::time(true),
                'created_at' => Field::time(),
                'updated_at' => Field::time(),
            ]))->titled('name')->lists('id', 'name', 'email', 'company_id', 'role', 'active', 'last_login')
                ->searches('name', 'email')
                ->edits('name', 'email', 'role', 'active')
                ->guards(self::keeps_an_owner(...)),

            (new Resource('company_llm_keys', 'Company AI keys', $g, "Companies' own AI keys: provider, model and the key's last 4 characters. The key itself is never shown.", [
                'id' => Field::id(),
                'company_id' => Field::ref('companies'),
                'provider' => Field::text(16),
                'model' => Field::text(64),
                'api_key_encrypted' => Field::secret(),
                'key_hint' => Field::text(4),
                'created_at' => Field::time(),
                'updated_at' => Field::time(),
            ]))->lists('id', 'company_id', 'provider', 'model', 'key_hint', 'updated_at')
                ->deletes('The company\'s AI features stop until an owner saves a key again.'),
        ];
    }

    private static function candidates(): array {
        $g = 'Candidates';
        return [
            (new Resource('candidates', 'Candidates', $g, 'People who signed up to apply. Switch active off to stop one signing in.', [
                'id' => Field::id(),
                'trongate_user_id' => Field::ref('trongate_users'),
                'name' => Field::text(255),
                'email' => Field::text(255, false, self::email(...)),
                'password' => Field::secret(),
                'active' => Field::bool(),
                'num_logins' => Field::int(),
                'last_login' => Field::time(true),
                'phone' => Field::text(32, true),
                'postal_code' => Field::text(10, true),
                'open_to_work' => Field::bool(),
                'created_at' => Field::time(),
                'updated_at' => Field::time(),
            ]))->titled('name')->lists('id', 'name', 'email', 'postal_code', 'open_to_work', 'active', 'last_login')
                ->searches('name', 'email', 'phone', 'postal_code')
                ->edits('name', 'email', 'phone', 'postal_code', 'open_to_work', 'active'),

            (new Resource('candidate_resumes', 'Candidate résumés', $g, 'The CV a candidate gave, as read; a new version each time they replace it.', [
                'id' => Field::id(),
                'candidate_id' => Field::ref('candidates'),
                'version' => Field::int(),
                'cv_name' => Field::text(255),
                'cv_text' => Field::long(),
                'current_title' => Field::text(255, true),
                'postal_code' => Field::text(10, true),
                'experience_years' => Field::int(true),
                'language' => Field::text(2),
                'extractor' => Field::text(32),
                'confirmed_at' => Field::time(),
                'created_at' => Field::time(),
                'updated_at' => Field::time(),
            ]))->titled('current_title')->lists('id', 'candidate_id', 'version', 'current_title', 'experience_years', 'updated_at')
                ->searches('cv_name', 'current_title'),

            (new Resource('candidate_resume_terms', 'Résumé terms', $g, 'What was read from a résumé, mapped onto the taxonomy.', [
                'id' => Field::id(),
                'candidate_resume_id' => Field::ref('candidate_resumes'),
                'kind' => Field::text(24),
                'term_id' => Field::ref('terms', true),
                'raw_text' => Field::text(255),
                'normalised' => Field::text(191),
                'years' => Field::int(true),
                'level' => Field::text(24, true),
                'match_method' => Field::text(16),
                'similarity' => Field::decimal(true),
                'sort_order' => Field::int(),
            ]))->titled('raw_text')->lists('id', 'candidate_resume_id', 'kind', 'raw_text', 'term_id', 'match_method')
                ->searches('raw_text'),
        ];
    }

    private static function jobs(): array {
        $g = 'Job posts and applications';
        return [
            (new Resource('job_posts', 'Job posts', $g, 'Posts companies wrote. Delete takes a post down for good; one with applications has to lose those first.', [
                'id' => Field::id(),
                'company_id' => Field::ref('companies'),
                'created_by' => Field::ref('company_members', true),
                'title' => Field::text(255),
                'level' => Field::text(24, true),
                'workplace_flexibility' => Field::text(16, true),
                'work_hours' => Field::text(16, true),
                'postal_code' => Field::text(10, true),
                'country_code' => Field::text(2),
                'language' => Field::text(2),
                'status' => Field::text(16),
                'version' => Field::int(),
                'raw_text' => Field::long(),
                'pitch' => Field::long(true),
                'extractor' => Field::text(32),
                'external_id' => Field::text(64, true),
                'public_token' => Field::text(12, true),
                'accepts_suggested' => Field::bool(),
                'max_suggested_applications' => Field::int(),
                'published_at' => Field::time(true),
                'paused_at' => Field::time(true),
                'closed_at' => Field::time(true),
                'archived_at' => Field::time(true),
                'closes_at' => Field::time(true),
                'created_at' => Field::time(),
                'updated_at' => Field::time(),
            ]))->titled('title')->lists('id', 'title', 'company_id', 'status', 'version', 'published_at')
                ->searches('title', 'public_token', 'external_id')
                ->deletes('Its requirements, scores and who viewed it go with it.'),

            (new Resource('job_post_terms', 'Job post terms', $g, "A post's requirements and title, mapped onto the taxonomy.", [
                'id' => Field::id(),
                'job_post_id' => Field::ref('job_posts'),
                'kind' => Field::text(24),
                'term_id' => Field::ref('terms', true),
                'raw_text' => Field::text(255),
                'english' => Field::text(255, true),
                'normalised' => Field::text(191),
                'is_required' => Field::bool(),
                'is_highlighted' => Field::bool(),
                'alt_group' => Field::int(true),
                'min_years' => Field::int(true),
                'min_level' => Field::text(24, true),
                'match_method' => Field::text(16),
                'similarity' => Field::decimal(true),
                'sort_order' => Field::int(),
            ]))->titled('raw_text')->lists('id', 'job_post_id', 'kind', 'raw_text', 'is_required', 'term_id', 'match_method')
                ->searches('raw_text', 'english'),

            (new Resource('job_post_member_views', 'Job post views', $g, 'When each member last opened a post\'s applicants.', [
                'job_post_id' => Field::ref('job_posts'),
                'company_member_id' => Field::ref('company_members'),
                'last_viewed_at' => Field::time(),
            ], ['job_post_id', 'company_member_id'])),

            (new Resource('job_applications', 'Job applications', $g, 'Applications candidates sent. Delete removes one with its scores and actions.', [
                'id' => Field::id(),
                'job_post_id' => Field::ref('job_posts'),
                'candidate_id' => Field::ref('candidates'),
                'current_title' => Field::text(255, true),
                'level' => Field::text(24, true),
                'workplace_flexibility' => Field::text(16, true),
                'work_hours' => Field::text(16, true),
                'postal_code' => Field::text(10, true),
                'language' => Field::text(2),
                'source' => Field::text(16),
                'raw_text' => Field::long(true),
                'cover_letter' => Field::long(true),
                'extractor' => Field::text(32),
                'candidate_resume_version' => Field::int(true),
                'status' => Field::text(16),
                'submitted_at' => Field::time(true),
                'shortlisted_at' => Field::time(true),
                'bookmarked_at' => Field::time(true),
                'rejected_at' => Field::time(true),
                'reject_reason' => Field::text(255, true),
                'withdrawn_at' => Field::time(true),
                'created_at' => Field::time(),
                'updated_at' => Field::time(),
            ]))->titled('current_title')->lists('id', 'job_post_id', 'candidate_id', 'current_title', 'status', 'source', 'submitted_at')
                ->searches('current_title')
                ->deletes('Its terms, scores and the actions on it go with it.'),

            (new Resource('job_application_terms', 'Application terms', $g, 'What was read from an application, mapped onto the taxonomy.', [
                'id' => Field::id(),
                'job_application_id' => Field::ref('job_applications'),
                'kind' => Field::text(24),
                'term_id' => Field::ref('terms', true),
                'raw_text' => Field::text(255),
                'normalised' => Field::text(191),
                'years' => Field::int(true),
                'level' => Field::text(24, true),
                'is_highlighted' => Field::bool(),
                'match_method' => Field::text(16),
                'similarity' => Field::decimal(true),
                'sort_order' => Field::int(),
            ]))->titled('raw_text')->lists('id', 'job_application_id', 'kind', 'raw_text', 'term_id', 'match_method')
                ->searches('raw_text'),

            (new Resource('job_application_actions', 'Application actions', $g, 'What was done to an application, by whom and when.', [
                'id' => Field::id(),
                'job_application_id' => Field::ref('job_applications'),
                'company_member_id' => Field::ref('company_members', true),
                'candidate_id' => Field::ref('candidates', true),
                'action' => Field::text(24),
                'from_status' => Field::text(16, true),
                'to_status' => Field::text(16),
                'job_post_version' => Field::int(),
                'match_score_id' => Field::ref('match_scores', true),
                'final_rank' => Field::int(true),
                'source' => Field::text(24),
                'reason' => Field::text(32, true),
                'note' => Field::text(500, true),
                'created_at' => Field::time(),
            ]))->titled('action')->lists('id', 'job_application_id', 'action', 'to_status', 'company_member_id', 'source', 'created_at')
                ->searches('action', 'reason', 'note'),

            (new Resource('attributes', 'Attributes', $g, 'Display extras of posts, applications, companies and candidates (key/value).', [
                'id' => Field::id(),
                'owner_type' => Field::text(16),
                'owner_id' => Field::int(),
                'attr_key' => Field::text(64),
                'value' => Field::long(),
                'lang' => Field::text(2, true),
                'sort_order' => Field::int(),
                'created_at' => Field::time(),
            ]))->titled('attr_key')->lists('id', 'owner_type', 'owner_id', 'attr_key', 'lang', 'created_at')
                ->searches('owner_type', 'attr_key'),
        ];
    }

    private static function scoring(): array {
        $g = 'Scoring';
        return [
            (new Resource('match_scores', 'Match scores', $g, 'Each application\'s score against its post, per post version and ranking version.', [
                'id' => Field::id(),
                'job_post_id' => Field::ref('job_posts'),
                'job_application_id' => Field::ref('job_applications'),
                'job_post_version' => Field::int(),
                'ranking_version' => Field::text(16),
                'deterministic_score' => Field::decimal(),
                'llm_score' => Field::decimal(true),
                'combined_score' => Field::decimal(),
                'tag' => Field::text(8),
                'laya_requirements_probability' => Field::decimal(true),
                'laya_fit_expected' => Field::decimal(true),
                'laya_choice' => Field::text(16, true),
                'needs_human' => Field::bool(),
                'final_rank' => Field::int(true),
                'computed_at' => Field::time(),
            ]))->lists('id', 'job_post_id', 'job_application_id', 'combined_score', 'tag', 'final_rank', 'computed_at'),

            (new Resource('match_score_details', 'Match score details', $g, 'The "why" behind a score: one row per criterion or question.', [
                'id' => Field::id(),
                'match_score_id' => Field::ref('match_scores'),
                'stage' => Field::text(16),
                'criterion' => Field::text(32),
                'job_post_term_id' => Field::ref('job_post_terms', true),
                'job_application_term_id' => Field::ref('job_application_terms', true),
                'rule' => Field::text(16),
                'weight' => Field::decimal(),
                'rank' => Field::decimal(),
                'passed' => Field::bool(),
                'probability' => Field::decimal(true),
                'reason' => Field::text(500, true),
            ]))->lists('id', 'match_score_id', 'stage', 'criterion', 'rule', 'passed', 'rank')
                ->searches('criterion', 'reason'),
        ];
    }

    private static function cv_checker(): array {
        $g = 'CV checker';
        return [
            (new Resource('cv_matches', 'CV matches', $g, 'CVs checked against a job post on /cv_match. Delete removes one with its résumé.', [
                'id' => Field::id(),
                'trongate_user_id' => Field::ref('trongate_users', true),
                'job_title' => Field::text(255),
                'company' => Field::text(255),
                'job_url' => Field::text(2048, true),
                'job_text' => Field::long(),
                'cv_name' => Field::text(255),
                'cv_text' => Field::long(),
                'score' => Field::decimal(),
                'points' => Field::int(),
                'max_points' => Field::int(),
                'tag' => Field::text(8),
                'application_text' => Field::long(true),
                'application_written_at' => Field::time(true),
                'resume_text' => Field::long(true),
                'resume_written_at' => Field::time(true),
                'created_at' => Field::time(),
            ]))->titled('job_title')->lists('id', 'job_title', 'company', 'trongate_user_id', 'score', 'tag', 'created_at')
                ->searches('job_title', 'company', 'cv_name')
                ->deletes('Its scored items and tailored résumé go with it, and its link stops working.'),

            (new Resource('cv_match_items', 'CV match items', $g, 'Each scored criterion of a CV match.', [
                'id' => Field::id(),
                'cv_match_id' => Field::ref('cv_matches'),
                'criterion' => Field::text(24),
                'text' => Field::text(500),
                'verdict' => Field::text(8),
                'decided_by' => Field::text(16),
                'reason' => Field::text(500),
                'evidence' => Field::text(500),
                'sort_order' => Field::int(),
            ]))->titled('text')->lists('id', 'cv_match_id', 'criterion', 'text', 'verdict', 'decided_by')
                ->searches('text'),

            (new Resource('cv_match_resumes', 'Tailored résumés', $g, 'The header of a tailored résumé, one per CV match.', [
                'cv_match_id' => Field::ref('cv_matches'),
                'name' => Field::text(255),
                'title' => Field::text(255),
                'location' => Field::text(255),
                'phone' => Field::text(64),
                'email' => Field::text(255),
                'links' => Field::text(1000),
                'intro' => Field::long(),
                'education_note' => Field::text(500),
                'experience_heading' => Field::text(64),
                'skills_heading' => Field::text(64),
                'education_heading' => Field::text(64),
                'languages_heading' => Field::text(64),
            ], ['cv_match_id']))->titled('name')->lists('cv_match_id', 'name', 'title', 'location')
                ->searches('name', 'title'),

            (new Resource('cv_match_resume_entries', 'Tailored résumé entries', $g, 'Roles and schools of a tailored résumé.', [
                'id' => Field::id(),
                'cv_match_id' => Field::ref('cv_matches'),
                'section' => Field::text(12),
                'title' => Field::text(255),
                'organisation' => Field::text(255),
                'location' => Field::text(255),
                'starts' => Field::text(32),
                'ends' => Field::text(32),
                'summary' => Field::text(500),
                'note' => Field::text(1000),
                'sort_order' => Field::int(),
            ]))->titled('title')->lists('id', 'cv_match_id', 'section', 'title', 'organisation', 'starts', 'ends')
                ->searches('title', 'organisation'),

            (new Resource('cv_match_resume_lines', 'Tailored résumé lines', $g, 'Bullets, skills and languages of a tailored résumé.', [
                'id' => Field::id(),
                'cv_match_id' => Field::ref('cv_matches'),
                'entry_id' => Field::ref('cv_match_resume_entries', true),
                'kind' => Field::text(12),
                'text' => Field::text(1000),
                'sort_order' => Field::int(),
            ]))->titled('text')->lists('id', 'cv_match_id', 'entry_id', 'kind', 'text')
                ->searches('text'),

            (new Resource('user_llm_keys', 'User AI keys', $g, "People's own AI keys: provider, model and the key's last 4 characters. The key itself is never shown.", [
                'id' => Field::id(),
                'trongate_user_id' => Field::ref('trongate_users'),
                'provider' => Field::text(16),
                'model' => Field::text(64),
                'api_key_encrypted' => Field::secret(),
                'key_hint' => Field::text(4),
                'created_at' => Field::time(),
                'updated_at' => Field::time(),
            ]))->lists('id', 'trongate_user_id', 'provider', 'model', 'key_hint', 'updated_at')
                ->deletes('Their AI features stop until they save a key again.'),
        ];
    }

    private static function taxonomy(): array {
        $g = 'Taxonomy';
        return [
            (new Resource('terms', 'Terms', $g, 'Skills, roles, titles and the rest of the vocabulary. Read-only: matching uses the index built from db/taxonomy.sql.', [
                'id' => Field::id(),
                'kind' => Field::text(24),
                'parent_id' => Field::ref('terms', true),
                'slug' => Field::text(191),
                'definition' => Field::long(true),
                'source' => Field::text(24),
                'status' => Field::text(16),
                'merged_into_id' => Field::ref('terms', true),
                'created_at' => Field::time(),
                'updated_at' => Field::time(),
            ]))->titled('slug')->lists('id', 'kind', 'slug', 'parent_id', 'status')
                ->searches('slug'),

            (new Resource('term_labels', 'Term labels', $g, 'Danish and English names of each term.', [
                'id' => Field::id(),
                'term_id' => Field::ref('terms'),
                'lang' => Field::text(2),
                'label' => Field::text(191),
                'normalised' => Field::text(191),
                'is_preferred' => Field::bool(),
            ]))->titled('label')->lists('id', 'term_id', 'lang', 'label', 'is_preferred')
                ->searches('label'),

            (new Resource('term_relations', 'Term relations', $g, 'Links between terms: title to role, role to skill, related.', [
                'id' => Field::id(),
                'term_id' => Field::ref('terms'),
                'related_term_id' => Field::ref('terms'),
                'relation' => Field::text(24),
                'confidence' => Field::decimal(true),
            ]))->lists('id', 'term_id', 'related_term_id', 'relation', 'confidence'),

            (new Resource('unmatched_terms', 'Unmatched terms', $g, 'Phrases the taxonomy had no term for, one row per phrase.', [
                'id' => Field::id(),
                'kind' => Field::text(24),
                'lang' => Field::text(2),
                'normalised' => Field::text(191),
                'example_raw_text' => Field::text(255),
                'occurrences' => Field::int(),
                'suggested_term_id' => Field::ref('terms', true),
                'suggested_similarity' => Field::decimal(true),
                'status' => Field::text(16),
                'resolved_term_id' => Field::ref('terms', true),
                'resolved_by' => Field::ref('trongate_users', true),
                'resolved_at' => Field::time(true),
                'created_at' => Field::time(),
            ]))->titled('example_raw_text')->lists('id', 'kind', 'lang', 'example_raw_text', 'occurrences', 'status')
                ->searches('normalised', 'example_raw_text'),
        ];
    }

    private static function sign_in(): array {
        $g = 'Sign-in';
        return [
            (new Resource('trongate_users', 'Users', $g, 'One per person who can sign in: an administrator, a company member or a candidate.', [
                'id' => Field::id(),
                'code' => Field::secret(),
                'user_level_id' => Field::ref('trongate_user_levels'),
            ])),

            (new Resource('trongate_user_levels', 'User levels', $g, 'admin, company_member and candidate. The code relies on these ids.', [
                'id' => Field::id(),
                'level_title' => Field::text(125),
            ]))->titled('level_title'),

            (new Resource('trongate_tokens', 'Sign-in tokens', $g, 'Signed-in sessions. Delete one to sign that session out.', [
                'id' => Field::id(),
                'token' => Field::secret(),
                'user_id' => Field::ref('trongate_users'),
                'expiry_date' => Field::time(true),
                'code' => Field::text(3),
            ]))->lists('id', 'user_id', 'expiry_date', 'code')
                ->deletes('That session is signed out.'),

            (new Resource('login_attempts', 'Failed sign-ins', $g, 'Failed sign-in attempts. Too many lock an account for a while; delete them to lift that.', [
                'id' => Field::id(),
                'target_table' => Field::text(255),
                'identifier' => Field::text(255),
                'ip_address' => Field::text(45),
                'attempted_at' => Field::time(),
            ]))->titled('identifier')->searches('identifier', 'ip_address')
                ->deletes(),

            (new Resource('password_resets', 'Password resets', $g, 'Password reset links sent. Delete one to make its link stop working.', [
                'id' => Field::id(),
                'target_table' => Field::text(255),
                'identifier' => Field::text(255),
                'token' => Field::secret(),
                'expiry_date' => Field::time(),
                'used' => Field::bool(),
                'created_at' => Field::time(),
            ]))->titled('identifier')->searches('identifier')
                ->deletes('Its link stops working.'),
        ];
    }

    /** A Danish CVR number (Company_rules::cvr), stored as its 8 digits. */
    private static function cvr(string $typed): string {
        return Company_rules::cvr($typed) ?? throw new InvalidArgumentException("isn't a valid Danish CVR number.");
    }

    private static function email(string $typed): string {
        if (filter_var($typed, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("isn't an email address.");
        }
        return strtolower($typed);
    }

    /**
     * A company keeps at least one active owner (as Company_rules has it for
     * its own staff): an edit that would take the last one away is refused.
     */
    private static function keeps_an_owner(array $row, array $values, Closure $count): ?string {
        $was_owner = $row['role'] === 'owner' && (int) $row['active'] === 1;
        $stays_owner = $values['role'] === 'owner' && (int) $values['active'] === 1;
        if (!$was_owner || $stays_owner) {
            return null;
        }
        $others = $count(
            "SELECT COUNT(*) FROM company_members WHERE company_id = :company_id AND role = 'owner' AND active = 1 AND id <> :id",
            ['company_id' => (int) $row['company_id'], 'id' => (int) $row['id']]
        );
        return $others === 0 ? 'A company needs at least one active owner. Make another member owner first.' : null;
    }
}
