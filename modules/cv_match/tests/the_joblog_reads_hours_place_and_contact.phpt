--TEST--
the joblog reads working hours, the workplace and the contact person by rule
--FILE--
<?php
require __DIR__ . '/../Joblog.php';
foreach (['Fuldtid, 37 timer om ugen', 'Deltid 25 timer/uge', 'Part-time (20 hours per week)', 'Deltid', 'Fuldtid eller deltid'] as $text) {
    echo implode(' ', Joblog::working_hours($text)), "\n";
}
foreach ([
    "Backend-udvikler\nAcme ApS\nVestergade 12, 2. th., 8000 Aarhus C",
    'Adresse: Lyngbyvej 2, 2100 København Ø',
    'Acme A/S, DK-2800 Kgs. Lyngby',
    "Arbejdssted: Odense",
    'Pr. 1. januar 2027 Vi tilbyder 5000 kr. om måneden',
] as $text) {
    echo json_encode(Joblog::place($text), JSON_UNESCAPED_UNICODE), "\n";
}
foreach ([
    'Har du spørgsmål til stillingen, er du velkommen til at kontakte afdelingsleder Mette Hansen på tlf. 12 34 56 78 eller mette.hansen@acme.dk.',
    "Vil du vide mere?\nKontakt CTO Peter Olsen\n+45 22 33 44 55",
    'CVR 12345678. Send din ansøgning til Acme ApS.',
] as $text) {
    echo json_encode(Joblog::contact($text, 'Acme ApS', ['email' => 'job@acme.dk']), JSON_UNESCAPED_UNICODE), "\n";
}
?>
--EXPECT--
Fuldtid 
Deltid 25
Deltid 20
Deltid 
Fuldtid 
["Vestergade 12, 2. th.","8000 Aarhus C"]
["Lyngbyvej 2","2100 København Ø"]
["","2800 Kongens Lyngby"]
["","5000 Odense C"]
["",""]
{"name":"Mette Hansen","phone":"12345678","email":"mette.hansen@acme.dk"}
{"name":"Peter Olsen","phone":"22334455","email":"job@acme.dk"}
{"name":"","phone":"","email":"job@acme.dk"}
