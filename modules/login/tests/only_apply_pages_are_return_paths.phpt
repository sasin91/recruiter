--TEST--
after sign-in a candidate goes back to an apply page, never anywhere else
--FILE--
<?php
require __DIR__ . '/../Return_path.php';
$_SESSION = [];
foreach (['jobs/MrP2T3YM4ntQ/apply', 'https://evil.example/', '//evil.example', 'jobs/short/apply', 'jobs/MrP2T3YM4ntQ/apply/../../x'] as $path) {
    Return_path::remember($path);
    echo $path, ' -> ', Return_path::take('cv_match'), "\n";
}
Return_path::remember('jobs/MrP2T3YM4ntQ/apply');
Return_path::take('cv_match');
echo 'taken once -> ', Return_path::take('cv_match'), "\n";
$_SESSION['after_sign_in'] = 'https://evil.example/';
echo 'tampered -> ', Return_path::take('cv_match'), "\n";
?>
--EXPECT--
jobs/MrP2T3YM4ntQ/apply -> jobs/MrP2T3YM4ntQ/apply
https://evil.example/ -> cv_match
//evil.example -> cv_match
jobs/short/apply -> cv_match
jobs/MrP2T3YM4ntQ/apply/../../x -> cv_match
taken once -> cv_match
tampered -> cv_match
