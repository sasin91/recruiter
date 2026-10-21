--TEST--
the review page names the status and reader, and offers the post's buttons
--FILE--
<?php
require_once __DIR__ . '/../Job_post_review.php';
$label = fn(array $b) => $b['label'] . ' → ' . $b['url'] . ($b['fields'] ? ' ' . http_build_query($b['fields']) : '');
$draft = Job_post_review::from(['id' => '4', 'status' => 'draft', 'extractor' => 'free_reader']);
echo $draft['status_name'], ' / ', $draft['read_by'], "\n", implode("\n", array_map($label, $draft['buttons'])), "\n";
$live = Job_post_review::from(['id' => '4', 'status' => 'active', 'extractor' => 'gpt-5.4-mini']);
echo $live['status_name'], ' / ', $live['read_by'], "\n", implode("\n", array_map($label, $live['buttons'])), "\n";
?>
--EXPECT--
Draft / the free reader
Duplicate → job_posts/submit_duplicate/4
Delete draft → job_posts/submit_delete/4
Live / AI (gpt-5.4-mini)
Pause → job_posts/submit_status/4 action=pause
Close → job_posts/submit_status/4 action=close
Duplicate → job_posts/submit_duplicate/4
