<?php
$title = 'Members';
require __DIR__ . '/header.php';
?>
<main>
    <h1>Members</h1>

    <?= flashdata('<p class="notice">', '</p>') ?>

    <?php if ($invite_link): ?>
        <div class="notice">
            <p><strong>Send this link to <?= out($invite_link['email']) ?></strong> to let them choose a password and join. Anyone with the link can join as them, so send it only to them.</p>
            <p><input type="text" class="copy-field" readonly value="<?= out($invite_link['url']) ?>" onclick="this.select()"></p>
        </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table class="list">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><?php if ($is_owner): ?><th></th><?php endif; ?></tr>
            </thead>
            <tbody>
            <?php foreach ($members as $m): ?>
                <tr class="<?= $m['active'] ? '' : 'muted' ?>">
                    <td><?= out($m['name']) ?><?php if ($m['is_me']): ?> <span class="badge">you</span><?php endif; ?></td>
                    <td><?= out($m['email']) ?></td>
                    <td><?= out($m['role_name']) ?></td>
                    <td><?= out($m['status']) ?></td>
                    <?php if ($is_owner): ?>
                        <td class="actions">
                            <?php if ($m['invite_url'] !== null): ?>
                                <input type="text" class="copy-field small" readonly value="<?= out($m['invite_url']) ?>" onclick="this.select()" aria-label="Invite link for <?= out($m['name']) ?>">
                            <?php endif; ?>
                            <?php foreach ($m['buttons'] as $button): ?>
                                <?= form_open('company/submit_member_change', ['class' => 'inline']) ?><?php foreach ($button['fields'] as $name => $value): ?><?= form_hidden($name, $value) ?><?php endforeach; ?><?= form_submit('submit', $button['label'], ['class' => 'button small']) ?><?= form_close() ?>
                            <?php endforeach; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="muted small">Owners manage members, settings and the company's AI key. Recruiters work with job posts and applications.</p>

    <?php if ($is_owner): ?>
        <section class="narrow-section">
            <h2>Invite a colleague</h2>
            <?= validation_errors() ?>
            <?= form_open('company/submit_invite', ['class' => 'stack']) ?>
                <?= form_label('Name', ['for' => 'name']) ?>
                <?= form_input('name', $name, ['id' => 'name', 'required' => true]) ?>

                <?= form_label('Work email', ['for' => 'email']) ?>
                <?= form_email('email', $email, ['id' => 'email', 'required' => true]) ?>

                <?= form_label('Role', ['for' => 'role']) ?>
                <?= form_dropdown('role', $roles, $role, ['id' => 'role']) ?>

                <?= form_submit('submit', 'Create invite link', ['class' => 'button primary']) ?>
            <?= form_close() ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
