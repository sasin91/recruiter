<?php
/**
 * How a job post asks to be applied to, when it asks for more than a click.
 *
 * Some posts tell applicants to send an email (to an address, with a subject
 * line, a keyword, or answers to a few questions) or to write a given word in
 * the application, partly to sort out people who only press Easy Apply. read()
 * finds those instructions in the raw post text, Danish or English, by rule
 * (no model call), so the CV checker can show them prominently and the
 * application can follow them.
 *
 * The answer is null when the post gives no such instruction, otherwise
 * { by_email, email, subject, keyword, asks, lines, trap }:
 * - by_email: the post asks for the application by email.
 * - email: the address to send it to, when the instruction names one.
 * - subject / keyword: the quoted subject line or word to include.
 * - asks: what is asked beyond sending it, from ASKS.
 * - lines: the post's own sentences that say so (with the questions they
 *   list), to quote to the applicant.
 * - trap: something is asked beyond a plain "send it to this address", the
 *   kind of thing a one-click application misses.
 */
class Apply_instructions {

    /** What a post can ask for beyond sending the application, in plain words. */
    public const ASKS = [
        'subject' => 'a specific subject line',
        'keyword' => 'a specific word or phrase',
        'questions' => 'answers to questions',
        'no_easy_apply' => 'no one-click applications',
        'proof' => 'proof that the post was read',
    ];

    // Most sentences quoted back, questions included.
    private const MAX_LINES = 10;

    // Most list lines taken as the questions a sentence introduces.
    private const MAX_QUESTIONS = 8;

    private const EMAIL = '/[\p{L}0-9._%+-]+@[\p{L}0-9-]+(?:\.[\p{L}0-9-]+)*\.\p{L}{2,}/u';

    private const MAIL_WORD = '/\b(?:e-?mail\w*|mail\w*|mail os|send os en mail)\b/iu';

    // Words that say a sentence is about applying, not only about mail.
    private const APPLY = '/(?:ansøg\w*|\bsøg\w*|application|apply|applying|applicants?|\bcv\b|resume|résumé|motivation\w*|cover letter)/iu';

    // Mailing or writing something: "send", "skriv", "email us".
    private const SEND = '/\b(?:send\w*|skriv\w*|write|writing|email us|mail us|mail os|include|inkluder\w*|vedhæft\w*|attach\w*)\b/iu';

    // "Questions? Write to ...": a contact line, not an application instruction.
    private const CONTACT = '/(?:spørgsmål til stillingen|har du (?:yderligere |flere |nogen )?spørgsmål|hvis du har (?:yderligere |flere )?spørgsmål|if you have (?:any )?(?:further |more )?questions|questions about (?:the|this)|any questions|for (?:more|further) information|vil du vide mere|vil du høre mere|hører du gerne mere|feel free to (?:contact|reach)|kontakt(?:e)? gerne)/iu';

    private const CUES = [
        'subject' => '/(?:subject|\bemne(?:t|felt\w*|linje\w*)?\b|\bmærk(?:et|es|e)?\b|overskrift\w*)/iu',
        'keyword' => '/(?:keyword|code ?word|password|secret word|kodeord\w*|stikord\w*|nøgleord\w*|\bthe word\b|\bordet\b|\bthe phrase\b|\bsætningen\b)/iu',
        'questions' => '/(?:\banswer\w*|besvar\w*|svar på|svar på følgende|following questions|questions below|these questions|the questions|følgende spørgsmål|spørgsmålene|nedenstående spørgsmål)/iu',
        'no_easy_apply' => '/(?:easy apply|one-click|1-click|et klik|(?:will not|won\'t|wont|cannot|can\'t) be (?:considered|read|processed)|(?:not|never) (?:be )?considered|tages ikke i betragtning|behandles ikke|bliver ikke behandlet|læses ikke|kun ansøgninger|only applications|ignored|ignoreres|automatically (?:rejected|discarded)|frasorteres)/iu',
        'proof' => '/(?:you(?:\'ve| have)? (?:actually )?read|read (?:the|this) (?:whole |entire )?(?:post|ad|job)|læst (?:hele )?(?:opslaget|annoncen|stillingsopslaget)|start (?:your|the) (?:application|cover letter|email|e-mail|mail|message) with|begin (?:your|the) (?:application|cover letter|email|e-mail|mail|message)|top of your (?:application|email|cover letter)|øverst i (?:din |ansøgningen|mailen)|start (?:din|ansøgningen|mailen) med|begynd (?:din|ansøgningen|mailen) med)/iu',
    ];

    private const QUOTED = '/["“”«»„]([^"“”«»„\n]{1,80})["“”«»]|(?<![\p{L}])[\'‘’]([^\'‘’\n]{1,60})[\'‘’](?![\p{L}])/u';

    // A line in a list: "- ...", "1. ...", "• ...", or a question.
    private const LIST_LINE = '/^\s*(?:[-*•–·]|\d{1,2}[.)])\s+\S|\?\s*$/u';

    /**
     * The post's application instructions, or null when it gives none.
     *
     * @return array{by_email: bool, email: string, subject: string, keyword: string, asks: list<string>, lines: list<string>, trap: bool}|null
     */
    public static function read(string $text): ?array {
        $lines = preg_split('/\R/u', $text) ?: [];
        $picked = [];
        $asks = [];
        $by_email = false;
        $after_instruction = false;
        foreach ($lines as $index => $line) {
            foreach (self::sentences($line) as $sentence) {
                // "Send it to job@x.dk. Skriv 'Backend' i emnefeltet.": the
                // second sentence goes on with the first.
                $found = self::instruction($sentence) ?? ($after_instruction ? self::follow_up($sentence) : null);
                $after_instruction = $found !== null;
                if ($found === null) {
                    continue;
                }
                $by_email = $by_email || $found['by_email'];
                $asks += array_fill_keys($found['asks'], true);
                $picked[] = $sentence;
                // "Answer these questions:" takes the list that follows.
                if (in_array('questions', $found['asks'], true) || preg_match('/:\s*$/u', $sentence)) {
                    $asks += self::questions_after($lines, $index, $picked);
                }
            }
        }
        if (!$picked) {
            return null;
        }
        $picked = array_values(array_unique($picked));
        $quoted = implode("\n", $picked);
        $asks = array_values(array_intersect(array_keys(self::ASKS), array_keys($asks)));
        return [
            'by_email' => $by_email,
            'email' => preg_match(self::EMAIL, $quoted, $m) ? $m[0] : '',
            'subject' => in_array('subject', $asks, true) ? self::quoted_after($picked, self::CUES['subject']) : '',
            'keyword' => in_array('keyword', $asks, true) || in_array('proof', $asks, true)
                ? self::quoted_after($picked, self::CUES['keyword'], self::CUES['proof']) : '',
            'asks' => $asks,
            'lines' => array_slice($picked, 0, self::MAX_LINES),
            'trap' => $asks !== [],
        ];
    }

    /**
     * The instructions as a few lines for a prompt, empty when there are none.
     */
    public static function for_prompt(?array $found): string {
        if (!$found) {
            return '';
        }
        $quote = implode("\n", array_map(fn($line) => "> $line", $found['lines']));
        $asks = $found['asks'] ? implode(', ', array_map(fn($ask) => self::ASKS[$ask], $found['asks'])) : 'nothing beyond sending it';
        return "How the post asks to be applied to (it asks for: $asks):\n$quote";
    }

    /** One sentence's instruction: { by_email, asks }, or null when it gives none. */
    private static function instruction(string $sentence): ?array {
        $has_email = (bool) preg_match(self::EMAIL, $sentence);
        $mail = $has_email || preg_match(self::MAIL_WORD, $sentence);
        $apply = (bool) preg_match(self::APPLY, $sentence);
        $asks = [];
        foreach (self::CUES as $ask => $pattern) {
            if (preg_match($pattern, $sentence)) {
                $asks[] = $ask;
            }
        }
        // A subject line only counts with mail or an application named.
        if (in_array('subject', $asks, true) && !$mail && !$apply) {
            $asks = array_values(array_diff($asks, ['subject']));
        }
        // "Har du spørgsmål, så skriv til ..." is the contact person, unless
        // the sentence also says to apply there.
        if (preg_match(self::CONTACT, $sentence) && !preg_match('/(?:ansøg\w*|application|apply|\bcv\b)/iu', $sentence)) {
            return null;
        }
        if ($mail && ($apply || $asks) && ($has_email || preg_match(self::SEND, $sentence) || $asks)) {
            return ['by_email' => true, 'asks' => $asks];
        }
        // Without mail: "write BANANA at the top of your application",
        // "applications through LinkedIn won't be considered".
        $asks = array_values(array_diff($asks, ['questions', 'subject']));
        if ($apply && $asks) {
            return ['by_email' => false, 'asks' => $asks];
        }
        return null;
    }

    /** What a sentence right after an instruction adds to it: { by_email, asks }, or null. */
    private static function follow_up(string $sentence): ?array {
        if (preg_match(self::CONTACT, $sentence)) {
            return null;
        }
        $asks = [];
        foreach (self::CUES as $ask => $pattern) {
            if (preg_match($pattern, $sentence)) {
                $asks[] = $ask;
            }
        }
        return $asks ? ['by_email' => false, 'asks' => $asks] : null;
    }

    /** A line split into sentences; email addresses and "e.g." stay whole. */
    private static function sentences(string $line): array {
        $line = trim($line);
        if ($line === '') {
            return [];
        }
        $parts = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}"“„«0-9])/u', $line) ?: [$line];
        return array_values(array_filter(array_map('trim', $parts), fn($s) => $s !== ''));
    }

    /**
     * The list lines right after line $index, added to $picked: the questions
     * an instruction introduces. Returns ['questions' => true] when it found any.
     */
    private static function questions_after(array $lines, int $index, array &$picked): array {
        $found = 0;
        for ($i = $index + 1; $i < count($lines) && $found < self::MAX_QUESTIONS; $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                if ($found > 0) {
                    break;
                }
                continue;
            }
            if (!preg_match(self::LIST_LINE, $line)) {
                break;
            }
            $picked[] = $line;
            $found++;
        }
        return $found > 0 ? ['questions' => true] : [];
    }

    /** The first quoted text in a sentence matching one of $patterns. */
    private static function quoted_after(array $sentences, string ...$patterns): string {
        foreach ($sentences as $sentence) {
            foreach ($patterns as $pattern) {
                if (!preg_match($pattern, $sentence, $cue, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                // The quote after the cue ("subject: 'X'"), else before it ("'X' in the subject").
                if (preg_match(self::QUOTED, $sentence, $m, 0, $cue[0][1]) || preg_match(self::QUOTED, $sentence, $m)) {
                    return trim(($m[1] ?? '') !== '' ? $m[1] : $m[2]);
                }
            }
        }
        return '';
    }

}
