<?php
require_once __DIR__ . '/Document_box.php';

/**
 * The "Application and résumé" panel of a saved match, as the result prints
 * it (views/documents.php): the notes for the AI, a box per document
 * (Document_box), the Apply button and the Jobnet joblog. A match that
 * wasn't saved gets the reason instead, since nothing can be written for it.
 */
class Match_documents {

    public const NOT_SAVED = 'This match wasn\'t saved, so nothing can be written for it.';
    public const INTRO = 'Saved. Write a job application or a résumé tailored to this post, from your CV. Tailoring the résumé writes the application too, when there is none yet:';
    public const NOTES_LABEL = 'Notes for the application and résumé';
    public const NOTES_HINT = 'Optional: anything the AI should know or stress. E.g. "I can start right away", "stress my Laravel work", "keep it short".';
    public const NOTES_MAX = 2000;

    /**
     * ['id' => 7, 'error' => '', 'boxes' => [Document_box, ...]], or with no
     * id, the error and no boxes. $written: kind => what Document_box takes;
     * $uses: kind => whether the user's key may write it.
     */
    public static function from(?int $id, string $save_error, array $written, array $uses, ?array $apply): array {
        if (!$id) {
            return ['id' => null, 'error' => $save_error !== '' ? $save_error : self::NOT_SAVED, 'boxes' => []];
        }
        return ['id' => $id, 'error' => '', 'boxes' => self::boxes($id, $written, $uses, $apply)];
    }

    /** What a saved match (Cv_match_model::find) has written, as from() takes it. */
    public static function written_of(array $match): array {
        return [
            'application' => ['text' => $match['application_text'] ?? '', 'written_at' => $match['application_written_at'] ?? ''],
            'resume' => ['text' => $match['resume_text'] ?? '', 'written_at' => $match['resume_written_at'] ?? '',
                'structured' => (int) ($match['resume_structured'] ?? 0) === 1],
        ];
    }

    private static function boxes(int $id, array $written, array $uses, ?array $apply): array {
        $boxes = [];
        foreach (array_keys(Document_box::KINDS) as $kind) {
            $boxes[] = Document_box::from($kind, $id, $written[$kind] ?? [], (bool) ($uses[$kind] ?? false), $apply);
        }
        return $boxes;
    }
}
