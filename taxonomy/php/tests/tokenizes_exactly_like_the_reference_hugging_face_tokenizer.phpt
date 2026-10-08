--TEST--
tokenizes exactly like the reference Hugging Face tokenizer
--FILE--
<?php
require __DIR__ . '/setup.inc';
// Produced by @huggingface/tokenizers from the unpruned model's tokenizer.json.
$cases = json_decode(file_get_contents("$model_dir/tokenizer-fixture.json"), true);
var_dump(count($cases) >= 20);
foreach ($cases as $case) {
    echo json_encode($case['text'], JSON_UNESCAPED_UNICODE), ': ';
    var_dump($model->tokenize($case['text']) === $case['tokens']);
}
?>
--EXPECT--
bool(true)
"Kørekort B": bool(true)
"Ærlighed og ÅBENHED": bool(true)
"Projektleder i byggeriet": bool(true)
"årsag": bool(true)
"C# \/ .NET-udvikler": bool(true)
"SOSU-assistent (30-37 t.)": bool(true)
"  dobbelt   mellemrum ": bool(true)
"naïve café": bool(true)
"ﬁnansiel rådgivning": bool(true)
"Erfaring med SAP S\/4HANA": bool(true)
"Social media manager": bool(true)
"Sygeplejerske, 32 timer": bool(true)
"IT-supporter": bool(true)
"Kundeservice & salg": bool(true)
"Python\/Django-udvikler": bool(true)
"Medarbejder til lager — 37 t.\/uge": bool(true)
"Grafisk designer!!": bool(true)
"Bogholder (barsel)": bool(true)
"React.js, Node.js og TypeScript": bool(true)
"Pædagogmedhjælper søges": bool(true)
"Machine learning engineer": bool(true)
"Økonomiassistent": bool(true)
"Håndværker": bool(true)
"Gaffeltruckcertifikat": bool(true)
"Stærke kommunikationsevner": bool(true)
"ERP-systemer (Navision, Dynamics 365)": bool(true)
"Lærling som elektriker": bool(true)
"UX\/UI designer": bool(true)
"Kvalitetssikring i medicinalindustrien": bool(true)
"Truckkørsel 🚜": bool(true)
