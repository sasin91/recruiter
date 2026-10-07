# Taxonomy (Phase 0)

One canonical Danish/English vocabulary of skills, roles and job titles, and
the PHP lookup that maps a phrase from a job post or an application onto it. The merged taxonomy is `db/taxonomy.sql`, a seed for the `terms` /
`term_labels` / `term_relations` tables in `db/schema.sql`.

| Path | What |
| --- | --- |
| `../db/taxonomy.sql` | The merged taxonomy as a seed (5.4 MB) |
| `php/Taxonomy.php` | The lookup: lexical + typo matching, `search()`, `rank()`, `match_method()`. Plain PHP classes, no framework dependency. |
| `php/Static_model.php`, `php/Unigram.php` | The static embedding model and its SentencePiece Unigram tokenizer |
| `php/tests.php` | `php taxonomy/php/tests.php` |
| `php/build-index.php` | Rebuilds `data/index.json` and `index.q8.bin` from the seed |
| `models/potion-multilingual-da/` | The embedding model (7 MB) and its tokenizer fixture |
| `data/review.csv` | Automatic decisions worth a human look, with a confidence |
| `data/index.json`, `index.q8.bin` | The static index the lookup ranks against (2 MB + 1.4 MB) |

## What's in it

| | Count |
| --- | --- |
| Skills | 9,926 (647 with a parent) |
| Skill categories | 13 (from the legacy parser API's `skills_cons.json`) |
| Roles | 401 (jobrole.csv, with Danish definitions) |
| Titles | 620 (each linked to its nearest role) |
| Labels | 23,532 (da + en, preferred + synonyms) |
| Role → skill links | 80,631 (the legacy app's, mapped onto canonical ids) |
| Without an English label | 222 skills, 17 roles, 132 titles |

Sources: the legacy app's `data/roles_and_skills.sql` (15k Danish skills each
with an English row at id + 15010, its skill clusters, 1.6k role rows in da and
en, 260k role-skill links) and the legacy parser API's `skills.txt` (99% the
same skills),
`jobrole.csv` and `skills_cons.json`. Neither old repo was modified.

How the merge decided (a one-off Node script, `taxonomy/tools/merge.mjs`,
removed once the seed was committed; it is in this PR's history):

- **Cleaning.** Bullets, numbering, quotes, zero-width characters and trailing
  punctuation go. Sentences ("God til at fasthold og udvikle eksisterende og
  nye kunder"), LLM refusals ("No job roles are relevant to…") and fragments
  are rejected: 360 rows.
- **Synonyms.** Two skills become one term when their labels match after
  dropping case, spaces, hyphens and dots ("IT-support" / "IT support"), use
  the same words in another order ("Kørekort B" / "B kørekort"), are a typo of
  each other (within one or two edits, close in meaning, and the rarer spelling
  seen at most twice in 14k real job ads: "Projetledelse"), share an English
  translation and mean the same in Danish ("Marketingstrategi" /
  "Markedsføringsstrategi"), or one is the English row of the other
  ("Strategic marketing" filed as Danish). Every such merge is in `review.csv`.
- **Hierarchy.** The legacy app's skill clusters were LLM groupings and most members
  are unrelated to their parent (most sit below cosine 0.2), so a member keeps
  its parent only when the model agrees (cosine ≥ 0.7): 287 of 5,201 links
  kept. `skills_cons.json`'s categories and groups are kept as given.
- **Roles and titles.** jobrole.csv is the curated role list. The legacy app's other
  role rows that name a person are titles; activities ("Kundeservice") and
  truncated rows ("Kranfø", "Salgs") are rejected. English names are paired by
  overlapping skill links, since the legacy da and en role ids don't line up.

## Lookup

```php
require_once 'taxonomy/php/Taxonomy.php';

$taxonomy = Taxonomy::load();                      // index + model, about 0.2 s
$results = $taxonomy->search('Erfaring med SAP', 'skill');
$results[0]['term']['id'];                         // terms.id
Taxonomy::match_method($results[0], 'Erfaring med SAP'); // 'exact'
```

`search()` scores every term as cosine + 0.5 × lexical, as `retrieval.mjs`
did, and takes about 0.1 s per phrase without the JIT. Load once per request
and search all of a job post's phrases with the same object. It is a port of
the earlier `taxonomy.mjs` and gives the same top 10, scores and match
methods on 324 test queries, and the same tokens on all 23,533 labels.
Changes for Danish:

- **Multilingual model.** potion-multilingual-128M (Model2Vec distilled from
  BAAI/bge-m3, MIT) replaces the English potion-base-4M, so "Project manager"
  finds Projektleder. Its tokenizer is SentencePiece Unigram, so `Unigram.php`
  is a Unigram Viterbi tokenizer; it matches Hugging Face's on 37,594 Danish
  texts (skills, titles and 3,000 ads) token for token.
- **æ, ø and å are kept.** The old BERT normaliser stripped accents, which
  turned "å" into "a". Text is also NFC-normalised, so a decomposed "å" from a
  PDF matches.
- **Case folding.** The model is cased: "Regnskab" and "regnskab" sat at
  cosine 0.25. Capitalised words are lowercased before embedding; acronyms and
  mixed case ("SAP", "iOS", "JavaScript") are left alone. This alone raised
  every eval score by 3 to 11 points.
- **Danish stopwords** join the English ones, and job-ad qualifiers
  ("erfaring", "kendskab", "stærke") are dropped from queries. "it" is not a
  stopword, because of "IT-support".
- **Typo tolerance.** Query words match index words within one edit (5+
  letters) or two (9+ letters), at 0.8 weight.
- **`match_method()`** reports `exact`, `synonym`, `fuzzy` or `embedding`, the
  codes `job_post_terms.match_method` stores.

## Model choice

Meaning only, no lexical half, measured with a one-off script that is no
longer in the repo:

| Model | Size | Synonyms (da) top-1 / 5 | da → en top-1 / 5 | en → da top-1 / 5 | Definition → role top-1 / 5 |
| --- | --- | --- | --- | --- | --- |
| **potion-multilingual, pruned, 128d (shipped)** | 7 MB | 27.6% / 52.5% | 65.2% / 82.2% | 56.1% / 77.0% | 73.1% / 91.8% |
| potion-multilingual, pruned, 256d | 13 MB | 29.6% / 55.5% | 68.1% / 84.6% | 60.2% / 80.3% | 68.3% / 89.4% |
| Danish BERT (BotXO) static, 256d | 16 MB | 11.6% / 18.1% | 8.6% / 12.3% | 9.1% / 13.3% | 19.9% / 34.5% |

The Danish BERT static model loses everywhere: it was distilled from a
masked language model, not a sentence encoder. Pruning keeps the 45k of 500k
vocabulary pieces that Danish/English job text uses, which cannot change how
that text tokenizes. 128 dimensions (PCA) halve the download for about three
points on cross-language lookups.

A static model has no context, so the lexical half carries exact names. If
the Phase 0 eval set shows paraphrases the static model misses, the next
step is a small contextual model (multilingual-e5-small), which would need
a Python or ONNX service next to PHP.

## Loading the seed

Load `db/taxonomy.sql` after `db/schema.sql`, into the same database. It is
re-runnable like the schema (`INSERT IGNORE`), and turns foreign key checks
off while it loads, since a term's parent can have a higher id.

- Term ids are fixed (1..10,960, by kind, then by Danish label), because
  `data/index.q8.bin` is keyed by them through `index.json`.
- `term_labels.normalised` is NFC, lowercased, trimmed, single spaces, æøå
  kept. Intake must normalise the same way or exact lookups miss. In PHP:
  `mb_strtolower(trim(preg_replace('/\s+/u', ' ', Normalizer::normalize($s, Normalizer::FORM_C))))`.
- Relations: `title_role` (confidence = cosine, 1.000 when the title is the
  role's own name) and `role_skill` (confidence NULL; an LLM in the legacy app made them).
- After seeding, the database is the source of truth. When the review queue
  adds labels, rebuild the static index from the tables.

## Rebuilding

When labels change, rebuild the static index and run the tests:

```bash
php taxonomy/php/build-index.php     # reads db/taxonomy.sql
php taxonomy/php/tests.php
```

The PHP builder reproduces the committed index exactly in `index.json` and
to within one int8 step in 4 of 1.4M vector cells (float rounding).

The seed and the pruned model were made once by Node scripts that read the
old legacy repos and the Hugging Face download; they were removed after their
output was committed and are in this PR's history.

Model source: `minishlab/potion-multilingual-128M`, revision
`73908c3438cf03b6a01bcb9611d62b23d0726f08` (`model.safetensors`,
`tokenizer.json`, `config.json`); hashes are in `model.json`.

## Known gaps

- The legacy parser API's job titles table lived in its Neon database and
  isn't in either repo, so titles come from the legacy app's role rows only.
- jobrole.csv's `category` column holds 89 Directus ids whose names aren't in
  the repo, so roles have no category yet.
- English labels are the legacy app's machine translations and are uneven
  ("banking" became "knocking").
- The role → skill links were generated by an LLM in the legacy app (about 200 per
  role) and are kept as they are, without confidence.
