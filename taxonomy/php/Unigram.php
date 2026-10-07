<?php
/**
 * SentencePiece Unigram tokenizer: the segmentation of the whole text into
 * vocabulary pieces with the highest total log-probability (Viterbi). A
 * character no piece covers becomes [UNK], and neighbouring [UNK]s fuse into
 * one, as in Hugging Face tokenizers.
 *
 * Pieces are looked up in a hash map by substring rather than walked through
 * a trie: the result is the same, and building a 45k-entry map is far cheaper
 * in PHP than building 45k nested arrays.
 */
class Unigram {

    /** @var string[] piece by id */
    public array $pieces = [];

    /** @var float[] log-probability by id */
    private array $scores = [];

    /** @var array<string, int> piece => id, without [UNK] */
    private array $ids = [];

    /** Longest piece, in characters. */
    private int $max_length = 1;

    /**
     * @param array<int, array{0: string, 1: float}> $pieces piece and log-probability, in id order
     * @param int $unk_id
     * @param float $unk_score the original vocabulary's minimum score minus 10;
     *   passed in because a pruned vocabulary has a different minimum.
     */
    public function __construct(array $pieces, public int $unk_id, public float $unk_score) {
        foreach ($pieces as $id => [$piece, $score]) {
            $this->pieces[$id] = $piece;
            $this->scores[$id] = $score;
            if ($id === $unk_id) {
                continue;
            }
            $this->ids[$piece] = $id;
            $this->max_length = max($this->max_length, mb_strlen($piece, 'UTF-8'));
        }
    }

    /**
     * Token ids of already-prepared text (see Static_model::prepare()).
     *
     * @return int[]
     */
    public function encode_ids(string $prepared): array {
        $chars = mb_str_split($prepared, 1, 'UTF-8');
        $n = count($chars);
        $best = array_fill(0, $n + 1, -INF);
        $from = array_fill(0, $n + 1, 0);
        $via = array_fill(0, $n + 1, 0);
        $best[0] = 0.0;

        for ($start = 0; $start < $n; $start++) {
            if ($best[$start] === -INF) {
                continue;
            }
            $single_char = false;
            $piece = '';
            $last = min($n, $start + $this->max_length);
            for ($end = $start; $end < $last; $end++) {
                $piece .= $chars[$end];
                $id = $this->ids[$piece] ?? null;
                if ($id === null) {
                    continue;
                }
                if ($end === $start) {
                    $single_char = true;
                }
                $score = $best[$start] + $this->scores[$id];
                if ($score > $best[$end + 1]) {
                    $best[$end + 1] = $score;
                    $from[$end + 1] = $start;
                    $via[$end + 1] = $id;
                }
            }
            if (!$single_char) {
                $score = $best[$start] + $this->unk_score;
                if ($score > $best[$start + 1]) {
                    $best[$start + 1] = $score;
                    $from[$start + 1] = $start;
                    $via[$start + 1] = $this->unk_id;
                }
            }
        }

        $ids = [];
        for ($end = $n; $end > 0; $end = $from[$end]) {
            $ids[] = $via[$end];
        }
        $ids = array_reverse($ids);

        $out = [];
        foreach ($ids as $i => $id) {
            if ($id === $this->unk_id && $i > 0 && $ids[$i - 1] === $this->unk_id) {
                continue;
            }
            $out[] = $id;
        }
        return $out;
    }

}
