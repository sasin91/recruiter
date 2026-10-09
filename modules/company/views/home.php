<?php
$title = $member['company_name'];
require __DIR__ . '/header.php';
$active = array_filter($members, fn($m) => (int) $m['active'] === 1 && !$m['invite_pending']);
$invited = array_filter($members, fn($m) => $m['invite_pending']);
$tab_names = ['active' => 'Live', 'drafts' => 'Drafts', 'closed' => 'Closed'];
$status_names = ['draft' => 'Draft', 'active' => 'Live', 'paused' => 'Paused', 'closed' => 'Closed', 'archived' => 'Archived'];
?>
<main>
    <div class="page-head">
        <div>
            <h1>Hi <?= out(explode(' ', $member['name'])[0]) ?></h1>
            <p class="sub muted"><?= out($member['company_name']) ?>'s job posts and applicants.</p>
        </div>
        <a class="button primary" href="job_posts/create">New job post</a>
    </div>

    <?= flashdata('<p class="notice">', '</p>') ?>

    <div class="kpis">
        <div class="kpi"><span class="value"><?= $totals['live'] ?></span><span class="label">Live posts</span></div>
        <div class="kpi"><span class="value"><?= $totals['applications'] ?></span><span class="label">Applications on live posts</span></div>
        <div class="kpi<?= $totals['new'] ? ' highlight' : '' ?>"><span class="value"><?= $totals['new'] ?></span><span class="label">New since you last looked</span></div>
        <div class="kpi"><span class="value"><?= $totals['shortlisted'] ?></span><span class="label">Shortlisted</span></div>
    </div>

    <nav class="tabs" aria-label="Job posts">
        <?php foreach ($tab_names as $key => $name): ?>
            <a href="company?tab=<?= $key ?>" class="<?= $tab === $key ? 'current' : '' ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= $name ?><span class="count"><?= $counts[$key] ?></span></a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$posts): ?>
        <div class="empty">
            <?php if ($tab === 'active'): ?>
                <p>No live job posts.</p>
                <p><a class="button primary" href="job_posts/create">Post a job</a></p>
            <?php elseif ($tab === 'drafts'): ?>
                <p>No drafts.</p>
            <?php else: ?>
                <p>No closed posts.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list post-list">
                <thead>
                    <tr><th>Job post</th><th>Status</th><th class="n">Applicants</th><th class="n">New</th><th class="n">Shortlisted</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($posts as $p): ?>
                    <tr>
                        <td class="title">
                            <a href="job_posts/review/<?= (int) $p['id'] ?>"><?= out($p['title']) ?></a><br>
                            <span class="muted small"><?= $p['published_at'] ? 'Published ' . date('j M Y', (int) $p['published_at']) : 'Created ' . date('j M Y', (int) $p['created_at']) ?></span>
                        </td>
                        <td><span class="status <?= out($p['status']) ?>"><?= $status_names[$p['status']] ?? out($p['status']) ?></span></td>
                        <td class="n" data-label="Applicants"><?= (int) $p['applications'] ?></td>
                        <td class="n" data-label="New"><?= (int) $p['new'] ? '<span class="new-dot">' . (int) $p['new'] . '</span>' : '0' ?></td>
                        <td class="n" data-label="Shortlisted"><?= (int) $p['shortlisted'] ?></td>
                        <td class="actions">
                            <?php if ($p['status'] !== 'draft'): ?>
                                <a class="button small primary" href="matchmaker/post/<?= (int) $p['id'] ?>">Candidates</a>
                            <?php endif; ?>
                            <a class="button small" href="job_posts/review/<?= (int) $p['id'] ?>"><?= $p['status'] === 'draft' ? 'Review' : 'Edit' ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="side-grid">
        <section class="card">
            <h2>Members</h2>
            <p><?= count($active) ?> active<?= $invited ? ', ' . count($invited) . ' invited' : '' ?>.</p>
            <a class="button" href="company/members"><?= $member['role'] === 'owner' ? 'Manage members' : 'See members' ?></a>
        </section>

        <?php if ($member['role'] === 'owner'): ?>
            <section class="card">
                <h2>AI key</h2>
                <?php if ($has_key): ?>
                    <p>Saved. Job posts are read and applications judged on it.</p>
                <?php else: ?>
                    <p class="muted">Not set. Posts are read and applications matched with the free keyword reader only.</p>
                <?php endif; ?>
                <a class="button" href="company/settings">Settings</a>
            </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
