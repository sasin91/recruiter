--TEST--
a duplicate or a row still pointed at is said plainly, and values are shown escaped with refs linked
--FILE--
<?php
require __DIR__ . '/setup.inc';
function refusal(int $code, string $message): PDOException {
    $e = new PDOException($message);
    $e->errorInfo = ['23000', $code, $message];
    return $e;
}
$members = Resource_catalog::find('company_members');
echo Resource_refused::explain($members, refusal(1062, "Duplicate entry 'bo@acme.dk' for key 'email'")), "\n";
echo Resource_refused::explain($members, refusal(1062, "Duplicate entry 'x' for key 'company_members.something'")), "\n";
echo Resource_refused::explain(Resource_catalog::find('job_posts'), refusal(1451, 'Cannot delete or update a parent row: a foreign key constraint fails (`recruiter`.`job_applications`, CONSTRAINT `job_applications_job_post_fk` FOREIGN KEY (`job_post_id`) REFERENCES `job_posts` (`id`))')), "\n";
var_dump(Resource_refused::explain($members, refusal(1205, 'Lock wait timeout exceeded')));

$f = $members->fields;
echo $f['name']->html('<b>Bo</b> & co'), "\n";
echo $f['company_id']->html('7'), "\n";
echo $f['active']->html('0'), "\n";
echo $f['last_login']->html(null), "\n";
echo Resource_catalog::find('job_posts')->fields['raw_text']->html("Line 1\n<script>"), "\n";
?>
--EXPECT--
Another row of Company members already has that email.
Another row of Company members already has one of these values.
Other rows still point at it (Job applications). Delete those first.
NULL
&lt;b&gt;Bo&lt;/b&gt; &amp; co
<a href="resources/show/companies/7">#7</a>
No
<span class="none">–</span>
<div class="long-text">Line 1<br />
&lt;script&gt;</div>
