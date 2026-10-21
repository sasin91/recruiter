<?php
$title = $member['company_name'];
require __DIR__ . '/header.php';
?>
<main>
    <div class="page-head">
        <div>
            <h1><?= out($dashboard['greeting']) ?></h1>
            <p class="sub muted"><?= out($member['company_name']) ?>'s job posts and applicants.</p>
        </div>
        <a class="button primary" href="job_posts/create">New job post</a>
    </div>

    <?= flashdata('<p class="notice">', '</p>') ?>

    <div class="kpis">
        <div class="kpi"><span class="value"><?= $dashboard['totals']['live'] ?></span><span class="label">Live posts</span></div>
        <div class="kpi"><span class="value"><?= $dashboard['totals']['applications'] ?></span><span class="label">Applications on live posts</span></div>
        <div class="kpi<?= $dashboard['totals']['new'] ? ' highlight' : '' ?>"><span class="value"><?= $dashboard['totals']['new'] ?></span><span class="label">New since you last looked</span></div>
        <div class="kpi"><span class="value"><?= $dashboard['totals']['shortlisted'] ?></span><span class="label">Shortlisted</span></div>
    </div>

    <nav class="tabs" aria-label="Job posts">
        <?php foreach ($dashboard['tabs'] as $t): ?>
            <a href="company?tab=<?= $t['key'] ?>" class="<?= $t['current'] ? 'current' : '' ?>"<?= $t['current'] ? ' aria-current="page"' : '' ?>><?= $t['name'] ?><span class="count"><?= $t['count'] ?></span></a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$dashboard['posts']): ?>
        <div class="empty">
            <?php if ($dashboard['tab'] === 'active'): ?>
                <p>No live job posts.</p>
                <p><a class="button primary" href="job_posts/create">Post a job</a></p>
            <?php elseif ($dashboard['tab'] === 'drafts'): ?>
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
                <?php foreach ($dashboard['posts'] as $p): ?>
                    <tr>
                        <td class="title">
                            <a href="job_posts/review/<?= $p['id'] ?>"><?= out($p['title']) ?></a><br>
                            <span class="muted small"><?= out($p['dated']) ?></span>
                        </td>
                        <td><span class="status <?= out($p['status']) ?>"><?= out($p['status_name']) ?></span></td>
                        <td class="n" data-label="Applicants"><?= $p['applications'] ?></td>
                        <td class="n" data-label="New"><?php if ($p['new']): ?><span class="new-dot"><?= $p['new'] ?></span><?php else: ?>0<?php endif; ?></td>
                        <td class="n" data-label="Shortlisted"><?= $p['shortlisted'] ?></td>
                        <td class="actions">
                            <?php if ($p['has_candidates']): ?>
                                <a class="button small primary" href="matchmaker/post/<?= $p['id'] ?>">Candidates</a>
                            <?php endif; ?>
                            <a class="button small" href="job_posts/review/<?= $p['id'] ?>"><?= $p['review_label'] ?></a>
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
            <p><?= $dashboard['members_line'] ?></p>
            <a class="button" href="company/members"><?= $dashboard['is_owner'] ? 'Manage members' : 'See members' ?></a>
        </section>

        <?php if ($dashboard['is_owner']): ?>
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
