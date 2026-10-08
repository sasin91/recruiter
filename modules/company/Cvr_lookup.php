<?php
require_once __DIR__ . '/Company_rules.php';

/**
 * A company's public details by its CVR number, from cvrapi.dk, so the
 * sign-up form can fill them in. Plain PHP: read() is tested from the CLI
 * (tests/*.phpt), fetch() makes the call.
 *
 * cvrapi.dk asks every caller to send a User-Agent naming the site and a
 * contact; CVRAPI_USER_AGENT overrides ours. A paid plan's token goes in
 * CVRAPI_TOKEN. CVRAPI_URL points it at another copy (a local stand-in).
 */
class Cvr_lookup {

    public const URL = 'https://cvrapi.dk/api';

    public const USER_AGENT = 'recruiter.trongate.dev - company sign-up - https://github.com/sasin91/recruiter';

    // What cvrapi.dk's error codes mean for someone signing up.
    private const ERRORS = [
        'NOT_FOUND' => 'No company has that CVR number.',
        'INVALID_VAT' => "That isn't a CVR number.",
        'QUOTA_EXCEEDED' => "The CVR lookup is busy right now. Fill in the company's name yourself.",
        'BANNED' => "The CVR lookup isn't available right now. Fill in the company's name yourself.",
        'INTERNAL_ERROR' => "The CVR lookup isn't available right now. Fill in the company's name yourself.",
    ];

    public function __construct(
        private readonly string $url = self::URL,
        private readonly string $user_agent = self::USER_AGENT,
        private readonly ?string $token = null,
        private readonly int $timeout = 6,
    ) {}

    /** The lookup as configured by CVRAPI_URL, CVRAPI_USER_AGENT and CVRAPI_TOKEN. */
    public static function from_env(): self {
        require_once __DIR__ . '/../llm/Llm.php';
        return new self(
            Llm::env('CVRAPI_URL') ?? self::URL,
            Llm::env('CVRAPI_USER_AGENT') ?? self::USER_AGENT,
            Llm::env('CVRAPI_TOKEN')
        );
    }

    /** The request URL for an 8-digit CVR number. */
    public function request_url(string $cvr): string {
        $query = ['search' => $cvr, 'country' => 'dk'];
        if ($this->token !== null) {
            $query['token'] = $this->token;
        }
        return $this->url . '?' . http_build_query($query);
    }

    /**
     * cvrapi.dk's answer as { cvr, name, address, postal_code, city,
     * company_type, ended }: ended is true for a company that has closed.
     *
     * @throws RuntimeException with a message for the person signing up
     */
    public static function read(array $response): array {
        if (isset($response['error'])) {
            throw new RuntimeException(self::ERRORS[$response['error']] ?? self::ERRORS['INTERNAL_ERROR']);
        }
        $name = trim((string) ($response['name'] ?? ''));
        if ($name === '' || !isset($response['vat'])) {
            throw new RuntimeException(self::ERRORS['INTERNAL_ERROR']);
        }
        return [
            'cvr' => str_pad((string) $response['vat'], 8, '0', STR_PAD_LEFT),
            'name' => $name,
            'address' => trim((string) ($response['address'] ?? '')),
            'postal_code' => trim((string) ($response['zipcode'] ?? '')),
            'city' => trim((string) ($response['city'] ?? '')),
            'company_type' => trim((string) ($response['companydesc'] ?? '')),
            'ended' => ($response['enddate'] ?? null) !== null && $response['enddate'] !== '',
        ];
    }

    /**
     * The company with this CVR number (as typed: spaces and "DK" allowed).
     *
     * @throws RuntimeException with a message for the person signing up
     */
    public function fetch(string $typed): array {
        $cvr = Company_rules::cvr($typed);
        if ($cvr === null) {
            throw new RuntimeException(self::ERRORS['INVALID_VAT']);
        }
        $curl = curl_init($this->request_url($cvr));
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->user_agent,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $raw = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        $response = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($response)) {
            error_log('cvrapi.dk: ' . ($raw === false ? $error : 'not JSON'));
            throw new RuntimeException(self::ERRORS['INTERNAL_ERROR']);
        }
        return self::read($response);
    }

}
