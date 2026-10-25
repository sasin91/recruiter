<?php
require_once __DIR__ . '/Match_score.php';

/**
 * A saved match as an entry for Jobnet's joblog ("Opret joblog" on
 * jobnet.dk), the log of applications Danish job seekers keep for the job
 * centre. Jobnet has no way in for other sites, so the CV checker shows each
 * field in Jobnet's own order and words, filled in from the match, for the
 * candidate to copy over.
 *
 * read() fills the fields by rule from the saved match (no model call):
 * - the job title, company and link come from the match;
 * - the deadline, working hours, address, postcode and town, and contact
 *   person are read from the post text (Danish or English). A postcode only
 *   counts when the town after it is that postcode's town (danish_postcodes.php),
 *   so "2026 Vi tilbyder" isn't an address;
 * - how far the application is: what the candidate last said (Ikke søgt
 *   until then) and, once applied, the day they said so; the post was found
 *   as a posted job and is applied for digitally;
 * - the notes: the score, what the CV meets and misses, and how the post
 *   asks to be applied to.
 * Anything not found is left empty, for the candidate to fill in on Jobnet.
 */
class Joblog {

    /** Jobnet's choices, by the value its form posts. */
    public const JOB_TYPES = ['Fuldtid' => 'Fuldtid', 'Deltid' => 'Deltid'];
    public const SEARCH_STATUSES = ['IkkeSoegt' => 'Ikke søgt', 'Soegt' => 'Søgt', 'Samtale' => 'Samtale'];
    // The statuses that say the candidate has applied.
    public const APPLIED_STATUSES = ['Soegt', 'Samtale'];
    public const FOUND = ['OpslaaetStilling' => 'Opslået stilling', 'Uopfordret' => 'Uopfordret', 'GennemNetvaerk' => 'Gennem Netværk'];
    public const APPLIED = ['Digitalt' => 'Digitalt', 'Personligt' => 'Personligt', 'Telefon' => 'Telefonisk'];

    // Jobnet's limits: part time is at most 36 hours, notes 2000 characters.
    public const MAX_PART_TIME_HOURS = 36;
    public const MAX_NOTES = 2000;

    private const MONTHS = [
        'januar' => 1, 'january' => 1, 'jan' => 1,
        'februar' => 2, 'february' => 2, 'feb' => 2,
        'marts' => 3, 'march' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'maj' => 5, 'may' => 5,
        'juni' => 6, 'june' => 6, 'jun' => 6,
        'juli' => 7, 'july' => 7, 'jul' => 7,
        'august' => 8, 'aug' => 8,
        'september' => 9, 'sept' => 9, 'sep' => 9,
        'oktober' => 10, 'october' => 10, 'okt' => 10, 'oct' => 10,
        'november' => 11, 'nov' => 11,
        'december' => 12, 'dec' => 12,
    ];

    // What comes right before an application deadline.
    private const DEADLINE = '/(?:ansøgningsfrist\w*|sidste ansøgningsdag|frist\w*|deadline|(?:søg|ansøg|send\w*)[^.\n]{0,40}?senest|senest|apply (?:by|before|no later than)|applications? (?:by|before)|closing date|last day to apply)/iu';

    // "Løbende": applications are read as they come, there is no deadline.
    private const ONGOING = '/(?:løbende|hurtigst muligt|asap|as soon as possible|on a rolling basis|ongoing basis)/iu';

    // A location line on its own, as LinkedIn and Jobindex print it:
    // "Odense, Region Syddanmark, Danmark" or "Aarhus C, Denmark".
    private const TOWN_LINE = '/^\s*(\p{Lu}[\p{L} .\-]{1,40}),\s*(?:Region\s+\p{L}+(?:\s+\p{L}+)?,\s*)?(?:Danmark|Denmark)\s*$/u';
    // Lines that say where the job is.
    private const PLACE = '/(?:arbejdssted|arbejdsplads|lokation|location|placering|beliggende|adresse|address|kontor(?:et)? (?:i|ligger)|office in|based in|job ?location|\bsted\b)\s*:?/iu';

    private const EMAIL = '/[\p{L}0-9._%+-]+@[\p{L}0-9-]+(?:\.[\p{L}0-9-]+)*\.\p{L}{2,}/u';

    private const PHONE = '/(?<![\d+])(?:\+45[\s-]?)?(\d{2}[\s-]?\d{2}[\s-]?\d{2}[\s-]?\d{2})(?![\d])/u';

    private const PHONE_CUE = '/(?:tlf|telefon|phone|mobil|mobile|\bring\b|\bcall\b|\btel\b|\+45)/iu';

    // Lines with a contact person: who to ask about the job.
    private const CONTACT = '/(?:kontakt\w*|contact|spørgsmål|questions|vil du vide mere|hører du gerne mere|høre mere|information|oplysninger)/iu';

    // Capitalised words that start a sentence or name a role, not a person.
    private const NOT_NAMES = ['Har', 'Hvis', 'Vil', 'Du', 'Kontakt', 'Kontakte', 'Ring', 'Send', 'Skriv', 'Spørgsmål', 'For', 'Yderligere',
        'Information', 'Oplysninger', 'If', 'Please', 'Contact', 'Feel', 'You', 'We', 'Vi', 'Questions', 'Call', 'Mail', 'Email', 'E-mail',
        'Tlf', 'Telefon', 'Phone', 'Mobil', 'Mobile', 'Head', 'Chief', 'Senior', 'Lead', 'Manager', 'Director', 'Direktør', 'Leder',
        'Afdelingsleder', 'Teamleder', 'Chef', 'HR', 'CTO', 'CEO', 'COO', 'CFO', 'Recruiter', 'Rekrutteringspartner', 'Ansøgningsfrist',
        'Mere', 'More', 'Learn', 'Read', 'Og', 'And', 'Eller', 'Or', 'Så', 'Til', 'To', 'Med', 'With', 'På', 'At', 'Om', 'About',
        'Engineering', 'Development', 'Udvikling', 'IT', 'Hej', 'Hello', 'Hi', 'Mvh', 'Venlig', 'Hilsen', 'Best', 'Regards',
        'Danmark', 'Denmark', 'Aarhus', 'København', 'Copenhagen', 'Odense', 'Aalborg'];

    /**
     * The joblog entry for a saved match (as Cv_match::saved_match gives
     * it): Jobnet's sections in order, each with its fields, then the three
     * PDFs it keeps.
     *
     * @return array{sections: list<array{heading: string, fields: list<array>}>, files: list<array>}
     */
    public static function read(array $match): array {
        $text = str_replace(["\r\n", "\r", "\u{a0}"], ["\n", "\n", ' '], (string) $match['job_text']);
        $saved_at = (int) ($match['created_at'] ?? time());
        [$job_type, $hours] = self::working_hours($text);
        [$address, $postal] = self::place($text);
        $contact = self::contact($text, (string) $match['company'], $match['apply_instructions'] ?? null);
        $written = trim((string) ($match['application_text'] ?? '')) !== '';
        $resume = trim((string) ($match['resume_text'] ?? '')) !== '';
        $jobnet = $match['jobnet_joblog'] ?? null;

        return [
            'sections' => [
                ['heading' => 'Om jobbet', 'fields' => [
                    self::field('occupation', 'Stilling eller arbejdsområde', trim((string) $match['job_title']), required: true),
                    self::field('submissionDeadline', 'Ansøgningsfrist', self::deadline($text, $saved_at), hint: 'dd-mm-åååå'),
                    self::field('jobType', 'Arbejdstid', $job_type, 'radio', self::JOB_TYPES, true),
                    self::field('hoursPerWeek', 'Antal timer pr. uge', $job_type === 'Deltid' ? $hours : '', 'number',
                        required: true, hint: 'Kun ved deltid, højst ' . self::MAX_PART_TIME_HOURS),
                ]],
                ['heading' => 'Om arbejdspladsen', 'fields' => [
                    self::field('name', 'Virksomhedens navn', trim((string) $match['company']), required: true),
                    self::field('companyAddress', 'Adresse', $address),
                    self::field('companyCountry', 'Land', $postal !== '' ? 'Danmark' : '', 'select'),
                    self::field('unknownPostalOrCity', 'Jeg kender hverken postnummer eller by', $postal === '', 'checkbox'),
                    self::field('postalCodeAndCity', 'Postnummer og by', $postal, required: true),
                    self::field('companyContactName', 'Navn på kontaktperson', $contact['name']),
                    self::field('companyContactPhone', 'Telefonnummer', $contact['phone']),
                    self::field('companyContactEmail', 'E-mail', $contact['email']),
                    self::field('jobAdLink', 'Link til jobannonce', trim((string) ($match['job_url'] ?? ''))),
                ]],
                ['heading' => 'Om din jobsøgning', 'fields' => [
                    // What the candidate said last (cv_match_integrations); a written
                    // application alone doesn't mean it was sent.
                    self::field('searchStatus', 'Hvor langt er du med at søge dette job?', $jobnet['status'] ?? 'IkkeSoegt', 'radio', self::SEARCH_STATUSES, true),
                    self::field('submissionDate', 'Hvilken dato har du søgt jobbet?', self::applied_on($jobnet), required: true,
                        hint: 'dd-mm-åååå, kun ved Søgt eller Samtale'),
                    self::field('applicationFormTypeFound', 'Hvordan fandt du jobbet?', 'OpslaaetStilling', 'radio', self::FOUND, true),
                    self::field('applicationFormTypeApplied', 'Hvordan søger du jobbet?', 'Digitalt', 'radio', self::APPLIED, true),
                    self::field('jobNotes', 'Skriv evt. noter om jobbet', self::notes($match), 'textarea'),
                ]],
            ],
            'files' => [
                ['kind' => 'application', 'label' => 'Jobansøgning', 'ready' => $written,
                    'missing' => 'Skriv ansøgningen først (Write application).'],
                ['kind' => $resume ? 'resume' : 'cv', 'label' => 'CV', 'ready' => trim((string) ($match['cv_text'] ?? '')) !== '',
                    'note' => $resume ? 'Det tilpassede CV' : 'Dit CV som tekst', 'missing' => 'Der er ikke gemt noget CV.'],
                ['kind' => 'job', 'label' => 'Jobannonce', 'ready' => trim($text) !== '', 'missing' => 'Der er ikke gemt noget jobopslag.'],
            ],
            // Whether the candidate has entered it in their joblog, and when.
            'logged_at' => isset($jobnet['logged_at']) ? (int) $jobnet['logged_at'] : null,
        ];
    }

    /**
     * The day the candidate applied as Jobnet wants it, dd-mm-yyyy (Danish
     * time): when they last set Søgt or Samtale (cv_match_integrations
     * updated_at). '' when they haven't applied, which is when Jobnet
     * doesn't ask.
     */
    public static function applied_on(?array $jobnet): string {
        if (!in_array($jobnet['status'] ?? '', self::APPLIED_STATUSES, true) || !isset($jobnet['updated_at'])) {
            return '';
        }
        return (new DateTimeImmutable('@' . (int) $jobnet['updated_at']))
            ->setTimezone(new DateTimeZone('Europe/Copenhagen'))->format('d-m-Y');
    }

    /**
     * The application deadline as Jobnet wants it, dd-mm-yyyy, or '' when the
     * post gives none (or reads applications as they come). A date without a
     * year is the next one after the match was saved, give or take a couple
     * of months for a post read after its deadline.
     */
    public static function deadline(string $text, int $saved_at): string {
        if (!preg_match_all(self::DEADLINE, $text, $cues, PREG_OFFSET_CAPTURE)) {
            return '';
        }
        foreach ($cues[0] as [$cue, $offset]) {
            $after = substr($text, $offset + strlen($cue), 120);
            // Up to the end of the sentence or line: a date further on is about something else.
            $after = preg_split('/\n|(?<=[a-zæøå])\.\s/u', $after)[0] ?? '';
            if (preg_match(self::ONGOING, mb_substr($after, 0, 30)) && !self::has_date($after)) {
                continue;
            }
            $date = self::first_date($after, $saved_at);
            if ($date !== '') {
                return $date;
            }
        }
        return '';
    }

    private static function has_date(string $text): bool {
        return self::first_date($text, time()) !== '';
    }

    /** The first date in a short piece of text, as dd-mm-yyyy, or ''. */
    private static function first_date(string $text, int $saved_at): string {
        $months = implode('|', array_map(fn($m) => preg_quote($m, '/'), array_keys(self::MONTHS)));
        $patterns = [
            // 2026-10-31
            '/(?<!\d)(\d{4})-(\d{1,2})-(\d{1,2})(?!\d)/u' => fn($m) => [$m[3], $m[2], $m[1]],
            // 31.10.2026, 31/10-26, 31-10-2026
            '/(?<![\d.])(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4}|\d{2})(?![\d])/u' => fn($m) => [$m[1], $m[2], $m[3]],
            // 31. oktober 2026, 31 Oct
            "/(?<!\\d)(\\d{1,2})\\.?\\s*(?:af\\s+)?($months)\\.?(?![\\p{L}])(?:,?\\s*(\\d{4}))?/iu" => fn($m) => [$m[1], self::MONTHS[mb_strtolower($m[2])], $m[3] ?? ''],
            // October 31, 2026
            "/(?<![\\p{L}])($months)\\.?\\s+(\\d{1,2})(?:st|nd|rd|th)?(?!\\d)(?:,?\\s*(\\d{4}))?/iu" => fn($m) => [$m[2], self::MONTHS[mb_strtolower($m[1])], $m[3] ?? ''],
            // 31/10 (no year), but not a time of day: "kl. 12.00"
            '/(?<![\d.]|kl\. |kl )(\d{1,2})[.\/](\d{1,2})(?!\d|[.\/]\d)/iu' => fn($m) => [$m[1], $m[2], ''],
        ];
        $found = [];
        foreach ($patterns as $pattern => $parts) {
            if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                $found[$m[0][1]] = $parts(array_map(fn($g) => $g[0], $m));
            }
        }
        ksort($found);
        foreach ($found as [$day, $month, $year]) {
            $day = (int) $day;
            $month = (int) $month;
            $year = (string) $year;
            if ($year === '') {
                $year = (int) date('Y', $saved_at);
                // Saved in December for a deadline in January.
                if (mktime(0, 0, 0, $month, $day, $year) < $saved_at - 60 * 86400) {
                    $year++;
                }
            } else {
                $year = strlen($year) === 2 ? 2000 + (int) $year : (int) $year;
            }
            if (checkdate($month, $day, $year)) {
                return sprintf('%02d-%02d-%04d', $day, $month, $year);
            }
        }
        return '';
    }

    /**
     * Full or part time, and the hours a week for part time. Hours below 37
     * a week, or the word itself, make it part time; full time otherwise,
     * the usual case.
     *
     * @return array{0: string, 1: string} [Fuldtid|Deltid, hours or '']
     */
    public static function working_hours(string $text): array {
        $hours = null;
        if (preg_match('/(?<![\d.,])(\d{1,2}(?:[.,]\d{1,2})?)\s*(?:-\s*\d{1,2}\s*)?(?:timer|timers|t\.|hours|hrs|h)\b[^\n.]{0,20}?(?:uge\w*|week\w*|ugentlig\w*)/iu', $text, $m)
            || preg_match('/(?:ugentlig\w*|weekly|om ugen|pr\.? uge|per week)[^\n.\d]{0,30}?(\d{1,2}(?:[.,]\d{1,2})?)\s*(?:timer|t\.|hours|hrs)\b/iu', $text, $m)) {
            $hours = (float) str_replace(',', '.', $m[1]);
        }
        if ($hours !== null && $hours > 0 && $hours < 37) {
            return ['Deltid', (string) min(self::MAX_PART_TIME_HOURS, (int) round($hours))];
        }
        $full = preg_match('/\bfuldtid\w*|\bfull[- ]?time\b/iu', $text) === 1;
        $part = preg_match('/\bdeltid\w*|\bpart[- ]?time\b/iu', $text) === 1;
        return [$part && !$full ? 'Deltid' : 'Fuldtid', ''];
    }

    /**
     * The workplace's street address and "postcode town" as Jobnet's
     * typeahead lists it ("8000 Aarhus C"). A postcode counts when the town
     * after it is the postcode's own; without one, a known town named after
     * "Arbejdssted:" or the like gives its first postcode.
     *
     * @return array{0: string, 1: string}
     */
    public static function place(string $text): array {
        $postcodes = self::postcodes();
        if (preg_match_all('/(?<![\d\p{L}])(?:DK-?\s?)?(\d{4})\s+(\p{Lu}[\p{L}.\-]*(?:\s+[\p{L}.\-]+)?)/u', $text, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($all as $m) {
                $code = $m[1][0];
                $town = $postcodes[$code] ?? null;
                if ($town === null || !self::names_town($m[2][0], $town)) {
                    continue;
                }
                // The street is on the same line, before the postcode.
                $line_start = strrpos(substr($text, 0, $m[0][1]), "\n");
                $before = substr($text, $line_start === false ? 0 : $line_start + 1, $m[0][1] - ($line_start === false ? 0 : $line_start + 1));
                return [self::street($before), "$code $town"];
            }
        }
        foreach (preg_split('/\n/u', $text) ?: [] as $line) {
            if (preg_match(self::PLACE, $line, $cue, PREG_OFFSET_CAPTURE)) {
                $rest = ' ' . self::town_words(substr($line, $cue[0][1] + strlen($cue[0][0]))) . ' ';
            } elseif (preg_match(self::TOWN_LINE, $line, $cue)) {
                $rest = ' ' . self::town_words($cue[1]) . ' ';
            } else {
                continue;
            }
            // The longest town named in full ("Kongens Lyngby" over "Lyngby"),
            // else a town's first word ("Odense" for 5000 Odense C).
            $full = $first = '';
            foreach ($postcodes as $code => $town) {
                $name = mb_strtolower($town);
                if (str_contains($rest, " $name ") && mb_strlen($name) > mb_strlen($full === '' ? '' : explode(' ', $full, 2)[1])) {
                    $full = "$code $town";
                } elseif ($first === '' && str_contains($rest, ' ' . explode(' ', $name)[0] . ' ')) {
                    $first = "$code $town";
                }
            }
            if ($full !== '' || $first !== '') {
                return ['', $full !== '' ? $full : $first];
            }
        }
        return ['', ''];
    }

    /** Lower case words with "Kgs." and "Copenhagen" as the postcode list writes them. */
    private static function town_words(string $text): string {
        $text = mb_strtolower($text);
        $text = preg_replace(['/\bkgs\.?\s*/u', '/\bcopenhagen\b/u'], ['kongens ', 'københavn'], $text) ?? $text;
        return trim(preg_replace('/[^\p{L}\d]+/u', ' ', $text) ?? $text);
    }

    /** Whether the words after a postcode name its town ("Aarhus" for "Aarhus C"). */
    private static function names_town(string $words, string $town): bool {
        $words = self::town_words($words);
        $town = mb_strtolower($town);
        $first = explode(' ', $town)[0];
        return str_starts_with($words . ' ', $town . ' ') || str_starts_with($words, $town)
            || preg_match('/^' . preg_quote($first, '/') . '(?![\p{L}])/u', $words) === 1;
    }

    /** The street address at the end of the text before a postcode: "Vestergade 12, 2. th.". */
    private static function street(string $before): string {
        $before = trim($before, " \t,");
        // The last part that starts with a street name and has a house number.
        $parts = array_reverse(array_map('trim', explode(',', $before)));
        $street = [];
        foreach ($parts as $part) {
            array_unshift($street, $part);
            // "Adresse: Vestergade 12": only the address.
            $part = $street[0] = preg_replace('/^.*:\s*/u', '', $part) ?? $part;
            if (preg_match('/^\p{Lu}[\p{L}.\-]*(?:\s+[\p{L}.\-]+){0,3}\s+\d{1,4}\s*\p{L}?\b/u', $part)) {
                return implode(', ', $street);
            }
            if (count($street) >= 3 || !preg_match('/^\d|\b(?:sal|th|tv|mf|st)\b/iu', $part)) {
                break;
            }
        }
        return '';
    }

    /** @return array<string, string> postcode => town */
    private static function postcodes(): array {
        static $postcodes = null;
        return $postcodes ??= require __DIR__ . '/danish_postcodes.php';
    }

    /**
     * The contact person: the name, phone (8 digits, as Jobnet's example)
     * and email from the post's contact lines ("Har du spørgsmål, så kontakt
     * Mette Hansen på 12 34 56 78"). The email falls back to the address the
     * post wants applications sent to.
     *
     * @return array{name: string, phone: string, email: string}
     */
    public static function contact(string $text, string $company = '', ?array $apply = null): array {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\n/u', $text) ?: []), fn($line) => $line !== ''));
        $name = $phone = $email = '';
        foreach ($lines as $i => $line) {
            $contact = preg_match(self::CONTACT, $line) === 1;
            // A contact sentence often ends a line and its details are on the next.
            $block = $contact ? implode("\n", array_slice($lines, $i, 3)) : $line;
            if ($phone === '' && ($contact || preg_match(self::PHONE_CUE, $line))) {
                foreach (preg_split('/\n/u', $block) as $part) {
                    if (preg_match(self::PHONE_CUE, $part) && !preg_match('/\bcvr\b/iu', $part) && preg_match(self::PHONE, $part, $m)) {
                        $phone = preg_replace('/\D/', '', $m[1]);
                        break;
                    }
                }
            }
            if ($contact && $email === '' && preg_match(self::EMAIL, $block, $m)) {
                $email = $m[0];
            }
            if ($contact && $name === '') {
                $name = self::person($block, $company, $email);
            }
        }
        if ($email === '') {
            $email = (string) ($apply['email'] ?? '');
        }
        return ['name' => $name, 'phone' => $phone, 'email' => $email];
    }

    /** The first person's name in a contact line, or ''. */
    private static function person(string $line, string $company, string $email): string {
        $line = preg_replace(self::EMAIL, ' ', $line) ?? $line;
        $word = '\p{Lu}\p{Ll}+(?:-\p{Lu}\p{Ll}+)?';
        if (!preg_match_all("/(?<![\\p{L}])$word(?:\\s+\\p{Lu}\\.)?(?:\\s+$word){1,2}(?![\\p{L}])/u", $line, $all)) {
            return '';
        }
        // "peter.olsen@" names the person the line is about.
        $local = $email !== '' ? preg_split('/[._-]/', mb_strtolower(explode('@', $email)[0])) : [];
        $candidates = [];
        foreach ($all[0] as $candidate) {
            $words = preg_split('/\s+/u', $candidate);
            // "Kontakt Mette Hansen": drop the words that aren't names.
            while ($words && in_array($words[0], self::NOT_NAMES, true)) {
                array_shift($words);
            }
            if (count($words) < 2 || array_intersect($words, self::NOT_NAMES) || ($company !== '' && mb_stripos($company, implode(' ', $words)) !== false)) {
                continue;
            }
            $candidates[] = implode(' ', $words);
        }
        foreach ($candidates as $candidate) {
            if ($local && in_array(mb_strtolower(explode(' ', $candidate)[0]), $local, true)) {
                return $candidate;
            }
        }
        return $candidates[0] ?? '';
    }

    /**
     * Notes for the job centre: the match score, what the CV meets and
     * misses, and how the post asks to be applied to, within Jobnet's limit.
     */
    public static function notes(array $match): string {
        $met = $missing = [];
        foreach ($match['items'] ?? [] as $item) {
            if (($item['criterion'] ?? '') === 'title') {
                continue;
            }
            if ($item['verdict'] === 'missing') {
                $missing[] = $item['text'];
            } else {
                $met[] = $item['text'];
            }
        }
        $lines = [];
        if (isset($match['score'])) {
            $lines[] = 'Match: ' . Match_score::percent($match['score']) . ' %' . (($match['tag'] ?? '') !== '' ? " ({$match['tag']})" : '') . '.';
        }
        if ($met) {
            $lines[] = 'Opfylder: ' . implode(', ', $met) . '.';
        }
        if ($missing) {
            $lines[] = 'Mangler: ' . implode(', ', $missing) . '.';
        }
        $apply = $match['apply_instructions'] ?? null;
        if ($apply && $apply['email'] !== '') {
            $lines[] = "Ansøgningen sendes til {$apply['email']}" . ($apply['subject'] !== '' ? " med emnet \"{$apply['subject']}\"" : '') . '.';
        }
        $notes = implode("\n", $lines);
        return mb_strlen($notes) > self::MAX_NOTES ? rtrim(mb_substr($notes, 0, self::MAX_NOTES - 1)) . '…' : $notes;
    }

    private static function field(string $name, string $label, string|bool $value, string $type = 'text', array $options = [],
            bool $required = false, string $hint = ''): array {
        $field = ['name' => $name, 'label' => $label, 'type' => $type, 'value' => $value, 'required' => $required];
        if ($options) {
            $field['options'] = $options;
        }
        if ($hint !== '') {
            $field['hint'] = $hint;
        }
        return $field;
    }

}
