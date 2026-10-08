<?php
/**
 * Laya, the decision model that refines a ranked match, over HTTP. Laya runs
 * as its own service (pip install "laya[serve]"; python -m laya.serve) and
 * this client asks its POST /v1/systemone two questions about a match.
 *
 * The state is Cv_matcher::laya_summary(): the code-computed verdicts in one
 * English sentence pair, which the English checkpoint separated with AUC 0.98
 * in the bake-off (eval/README.md). Only the yes/no and 0-4 fit heads are
 * asked; the interview/maybe/reject choice head was poor everywhere.
 *
 * To run the service locally:
 *
 *   pip install "laya[serve]"
 *   LAYA_MODELS=english LAYA_API_KEY=some-secret python -m laya.serve   # port 8000
 *
 * LAYA_URL is the service's base URL (http://localhost:8000 above) and
 * LAYA_API_KEY its bearer key (the service's own LAYA_API_KEY). With no LAYA_URL, from_env() gives null and
 * the match goes on without Laya.
 *
 *   $laya = Laya_client::from_env();
 *   $answer = $laya?->decide($summary); // ['meets' => 0.68, 'fit' => 3.1, 'fit_label' => 'good fit']
 */
class Laya_client {

    // The checkpoint the bake-off picked; the multilingual one was near chance.
    public const MODEL = 'english';

    public const FIT_LEVELS = ['no fit', 'weak fit', 'partial fit', 'good fit', 'excellent fit'];

    public function __construct(
        private readonly string $base_url,
        private readonly ?string $api_key = null,
        private readonly int $timeout = 20,
    ) {}

    /** The client for LAYA_URL and LAYA_API_KEY, or null when Laya isn't set up. */
    public static function from_env(): ?self {
        require_once __DIR__ . '/../llm/Llm.php';
        $url = Llm::env('LAYA_URL');
        return $url === null ? null : new self($url, Llm::env('LAYA_API_KEY'));
    }

    /** The questions, worded as in the bake-off (eval/laya/run_laya.py). */
    public static function questions(): array {
        return [
            'meets' => ['type' => 'noul', 'instructions' => 'Does the candidate meet every required qualification for the job?'],
            'fit' => ['type' => 'score', 'instructions' => 'How strong a fit is this candidate for the job?',
                'criteria' => self::FIT_LEVELS],
        ];
    }

    /** The request body for one state. */
    public static function request(string $state): array {
        return ['state' => $state, 'questions' => self::questions(), 'model' => self::MODEL];
    }

    /**
     * Laya's response as `meets` (probability of meeting every requirement),
     * `fit` (expected level, 0-4) and `fit_label` (the nearest level's name).
     */
    public static function read_answer(array $response): array {
        $meets = $response['answers']['meets']['noul'] ?? null;
        $fit = $response['answers']['fit']['score'] ?? null;
        if (!is_numeric($meets) || !is_numeric($fit)) {
            throw new RuntimeException('Laya answered without the meets and fit scores.');
        }
        $fit = max(0.0, min(4.0, (float) $fit));
        return [
            'meets' => round((float) $meets, 4),
            'fit' => round($fit, 3),
            'fit_label' => self::FIT_LEVELS[(int) round($fit)],
        ];
    }

    /**
     * Laya's answer for a state.
     *
     * @throws RuntimeException when the service can't be reached or fails
     */
    public function decide(string $state): array {
        $curl = curl_init(rtrim($this->base_url, '/') . '/v1/systemone');
        $headers = ['Content-Type: application/json'];
        if ($this->api_key !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->api_key;
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode(self::request($state), JSON_THROW_ON_ERROR),
        ]);
        $raw = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($raw === false) {
            throw new RuntimeException("Laya could not be reached: $error");
        }
        $response = json_decode($raw, true);
        if ($status === 401) {
            throw new RuntimeException('Laya rejected the API key (LAYA_API_KEY).');
        }
        if ($status >= 400 || !is_array($response)) {
            $detail = is_string($response['detail'] ?? null) ? $response['detail'] : "HTTP $status";
            throw new RuntimeException("Laya failed: $detail");
        }
        return self::read_answer($response);
    }

}
