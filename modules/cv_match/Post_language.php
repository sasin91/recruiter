<?php
/**
 * The language a job post is written in, told to the model in so many words
 * when it writes the application or the tailored résumé. Asked only to "write
 * in the post's language", it went by the Danish CV, requirements and apply
 * lines around an English post and wrote Danish.
 *
 * Counts common words only Danish or only English uses; a post's page may
 * carry a few words of the other language (a Danish job board's buttons),
 * so the language with the most wins.
 */
class Post_language {

    // Frequent words of one language that aren't words of the other ("i",
    // "for", "en", "under", "over" are both and left out).
    private const DANISH = 'og at er til med du vi som på af det har kan ikke eller der den vil skal hos også jeg din dine dit vores os de dig være bliver arbejde erfaring stilling ansøgning';
    private const ENGLISH = 'the and to of you we with is are will our your have be this that as on from work experience role team who what';

    // Fewer hits than this in both says nothing (a title, a link).
    private const MIN_HITS = 5;

    /** "Danish", "English", or null when the text doesn't say. */
    public static function of(string $text): ?string {
        $danish = array_fill_keys(explode(' ', self::DANISH), true);
        $english = array_fill_keys(explode(' ', self::ENGLISH), true);
        $da = $en = 0;
        foreach (preg_split('/[^\p{L}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $da += isset($danish[$word]) ? 1 : 0;
            $en += isset($english[$word]) ? 1 : 0;
        }
        if (max($da, $en) < self::MIN_HITS || $da === $en) {
            return null;
        }
        return $da > $en ? 'Danish' : 'English';
    }

    /**
     * The prompt's line on which language to write $document in, for the
     * post $job_text.
     */
    public static function rule(string $job_text, string $document): string {
        $language = self::of($job_text);
        if ($language === null) {
            return "Write the $document in the language of the job post, whatever language the CV, the requirements or the notes are in.";
        }
        return "Write the $document in $language, because the job post is in $language, even where the CV, the requirements or the notes are in another language.";
    }
}
