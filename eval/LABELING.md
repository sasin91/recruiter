# Gold labelling rules

Input per ad: the `Titel`, `Virksomhed`, `Adresse` lines and the ad text, exactly as the extractors see them (`prompt.mjs` `adInput`).
Output: one JSON object matching `schema.json`. Only use what the ad says. These rules resolve the ambiguous cases so labels are hand-checkable.

- **jobtitle**: the job title as written, without company name, location or "søges"/"(m/k)" noise. `"Dækmontør, Super Dæk Service"` -> `"Dækmontør"`.
- **joblevel**: `Medarbejder` by default. `Leder med personaleansvar` if the role manages people (afdelingsleder, teamleder med medarbejdere). `Leder uden personaleansvar` for project/product lead roles without reports. `Direktion` for direktør/CEO/CFO. `Elev/praktikant` for elev, lærling, trainee-uddannelse. `Praktikant` for praktik/internship. `Graduate` for graduate programmes.
- **skills**: concrete competences the candidate should have (qualifications, "vi forventer", "du har", "krav"). Short names (1-4 words), in the ad's wording. Include languages (`Dansk`, `Engelsk`, category `Sprog`), software/tools (`Excel`, `SAP`, category `Software`), soft skills (`Selvstændig`, `Samarbejdsevner`, category `Soft skill`). Programming languages and technical methods are `Hard skill`. `is_required` false only when the ad marks it "en fordel", "gerne", "ønskeligt", "nice to have". Do not list tasks of the job here (those are responsibilities) or benefits.
- **certifications**: licences and certificates: `Kørekort` (with class if stated, e.g. `Kørekort kategori C`), `Truckcertifikat`, `Straffeattest`/`Børneattest` only if they say it is required, autorisation (e.g. `Autorisation som sygeplejerske`). Educations are not certifications.
- **minimum_experience_years**: explicit number of years. "et par år" = 2, "nogle års" = 2, "flere års" = 3, "mange års" = 5. "Erfaring" with no amount = 1. Not mentioned = 0.
- **workplace_flexibility**: `partremote` if the ad mentions hjemmearbejde/hybrid/fleksibel arbejdsplads, `fullremote` if fully remote, otherwise `noremote`.
- **workhours**: `Part time` for deltid/timer per week below 30, `Mini job` for very few hours (under ~15 h/week, studiejob a few hours, weekend-only), otherwise `Full time`. If both full and part time are offered, `Full time`.
- **education**: named educations required or preferred (`Social- og sundhedsassistent`, `Cand.merc.`, `Elektriker`). Empty if none.
- **education_level**: the level implied by the required education: Grundskole, Erhvervsfaglig uddannelse (faglært, svend, SOSU, elev in a trade), Gymnasiel uddannelse (STX/HHX/HTX), Kort videregående (akademiøkonom, datamatiker, 2-3 år), Længere videregående (bachelor/kandidat/professionsbachelor such as sygeplejerske, lærer, pædagog, ingeniør). Professionsbachelor counts as `Længere videregående uddannelse` here. Empty string if no education is mentioned.
- **responsibilities**: up to 6 main tasks, max 4 words each, in Danish, from the job description.
- **workplace_postal_code**: 4-digit Danish postcode from the address line or text. Empty string if none or if outside Denmark.

Scoring (see `score.mjs`): exact match on the enum and number fields and postcode; normalised fuzzy match on jobtitle; fuzzy set F1 on skills, certifications, education and responsibilities.
