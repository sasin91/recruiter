<?php
/**
 * The three language-model reads the matching runs on, shared by the CV
 * checker (Cv_match) and the company side: a job post into its requirements
 * list, a CV into a profile, and a verdict on each requirement the taxonomy
 * couldn't decide. Each takes the Llm to call, so the caller decides whose
 * key pays (the user's, the company's or the server's).
 */
class Match_prompts {

    /** A job post as { text_en, job_title, job_title_en, company, requirements, responsibilities }. */
    public static function read_job(Llm $llm, string $text): array {
        $system = <<<PROMPT
            You read job posts (often Danish) for a candidate and list what the employer asks for. Use only what the post says.

            requirements is one list of what a candidate must or should have. Keep each entry atomic: one skill, tool, language, certificate, education, amount of experience or trait ("Erfaring med PHP og Laravel" is two entries).
            - value names it as the post does, without qualifiers like "erfaring med" or "kendskab til".
            - kind: skill, education, certificate, experience, language, or soft_skill for personal traits ("selvstændig", "god til at samarbejde").
            - required is false for nice-to-haves ("en fordel", "gerne", "a plus").
            - Alternatives share an alt_group label ("pædagog eller pædagogisk assistent": two entries, alt_group "a"; "uddannet kok eller 5 års erfaring": an education and an experience entry, alt_group "b"); alt_group is empty otherwise.
            - min_years: the years of experience asked for ("et par år" = 2, "flere års" = 3, none stated = 0).
            - min_level: the level asked for, in English, when the post states one ("flydende" = "fluent", "modersmål" = "native", "kandidat" = "master's degree", "senior"); empty otherwise.

            responsibilities: at most 6, a few words each.
            text_en: the whole post as plain English text, translated if needed; keep every fact, drop layout.
            job_title_en and english: the job title and each value in English (the same text when it already is English).
            PROMPT;
        return $llm->structured($system, "Job post:\n\n$text", self::job_schema(), 'low');
    }

    /** A CV as a profile: { name, titles, skills, languages, certifications, education, experience_years, responsibilities, text_en }. */
    public static function read_cv(Llm $llm, string $text): array {
        $system = <<<PROMPT
            You read a CV and list what it shows the candidate can do. Use only what the CV says or directly shows: a bullet like "Upgraded Symfony from 6 to 7" shows Symfony and PHP.

            Keep each skill atomic (one technology, tool, method or language per item) and name it as commonly written ("Laravel", "Kubernetes", "CI/CD").
            titles: job titles held.
            experience_years: total years of professional work, from the dates.
            Include the languages the CV is written in or names.
            text_en: the whole CV as plain English text, translated if it is in another language, otherwise as given; keep every fact, drop layout.
            PROMPT;
        return $llm->structured($system, "CV:\n\n$text", self::cv_schema(), 'low');
    }

    /**
     * The model's verdict on each requirement the taxonomy couldn't decide:
     * { verdicts: [{ id, verdict: met|partial|missing, evidence, reason }] }.
     *
     * @param array $items [{ id, text, kind }]
     */
    public static function judge(Llm $llm, string $job_title, array $items, string $cv_text): array {
        $list = implode("\n", array_map(
            fn($item) => "- [{$item['id']}] ({$item['kind']}) {$item['text']}",
            $items
        ));
        $system = <<<PROMPT
            You judge whether a candidate meets job requirements, from their CV alone. For each requirement say:
            - "met" when the CV shows it (directly, or through something that clearly includes it: Laravel shows PHP),
            - "partial" when the CV shows something close or transferable but not the thing itself (Vue for React, Symfony for Laravel),
            - "missing" when the CV shows nothing relevant.

            Be strict: a guess is "missing". evidence quotes the CV briefly, empty when missing. reason is one short sentence in English.
            PROMPT;
        $user = <<<TEXT
            Job title: $job_title

            Requirements:
            $list

            CV:

            $cv_text
            TEXT;
        return $llm->structured($system, $user, self::judge_schema(), 'medium');
    }

    private static function job_schema(): array {
        // One requirements list, as agreed for job_post_terms: kind, value,
        // required, alt_group (alternatives share a label), min_years and
        // min_level. Soft skills are entries of kind soft_skill.
        $requirement = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value', 'english', 'kind', 'required', 'alt_group', 'min_years', 'min_level'],
            'properties' => [
                'value' => ['type' => 'string'],
                'english' => ['type' => 'string'],
                'kind' => ['type' => 'string', 'enum' => ['skill', 'education', 'certificate', 'experience', 'language', 'soft_skill']],
                'required' => ['type' => 'boolean'],
                'alt_group' => ['type' => 'string'],
                'min_years' => ['type' => 'integer'],
                'min_level' => ['type' => 'string'],
            ],
        ];
        $strings = ['type' => 'array', 'items' => ['type' => 'string']];
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['text_en', 'job_title', 'job_title_en', 'company', 'requirements', 'responsibilities'],
            'properties' => [
                'text_en' => ['type' => 'string'],
                'job_title' => ['type' => 'string'],
                'job_title_en' => ['type' => 'string'],
                'company' => ['type' => 'string'],
                'requirements' => ['type' => 'array', 'items' => $requirement],
                'responsibilities' => $strings,
            ],
        ];
    }

    private static function cv_schema(): array {
        $list = ['type' => 'array', 'items' => ['type' => 'string']];
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['name', 'titles', 'skills', 'languages', 'certifications', 'education', 'experience_years', 'responsibilities', 'text_en'],
            'properties' => [
                'name' => ['type' => 'string'],
                'text_en' => ['type' => 'string'],
                'titles' => $list,
                'skills' => $list,
                'languages' => $list,
                'certifications' => $list,
                'education' => $list,
                'experience_years' => ['type' => 'integer'],
                'responsibilities' => $list,
            ],
        ];
    }

    private static function judge_schema(): array {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['verdicts'],
            'properties' => [
                'verdicts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['id', 'verdict', 'evidence', 'reason'],
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'verdict' => ['type' => 'string', 'enum' => ['met', 'partial', 'missing']],
                            'evidence' => ['type' => 'string'],
                            'reason' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

}
