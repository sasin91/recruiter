<?php
$title = $member['company_name'];
require __DIR__ . '/header.php';
$active = array_filter($members, fn($m) => (int) $m['active'] === 1 && !$m['invite_pending']);
$invited = array_filter($members, fn($m) => $m['invite_pending']);
?>
<main>
    <h1>Hi <?= out(explode(' ', $member['name'])[0]) ?></h1>

    <?= flashdata('<p class="notice">', '</p>') ?>

    <div class="card-grid">
        <section class="card">
            <h2>Job posts</h2>
            <p class="muted">Posting jobs and reading ranked applications is coming next.</p>
        </section>

        <section class="card">
            <h2>Members</h2>
            <p><?= count($active) ?> active<?= $invited ? ', ' . count($invited) . ' invited' : '' ?>.</p>
            <a class="button" href="company/members"><?= $member['role'] === 'owner' ? 'Manage members' : 'See members' ?></a>
        </section>

        <?php if ($member['role'] === 'owner'): ?>
            <section class="card">
                <h2>AI key</h2>
                <?php if ($has_key): ?>
                    <p>Saved. Reading job posts and judging applications will run on it.</p>
                <?php else: ?>
                    <p class="muted">Not set. Without one, matching uses the free keyword match only.</p>
                <?php endif; ?>
                <a class="button" href="company/settings">Settings</a>
            </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
