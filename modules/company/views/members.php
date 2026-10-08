<?php
$title = 'Members';
require __DIR__ . '/header.php';
$is_owner = $member['role'] === 'owner';

/** A small POST form with one button that changes a member. */
$change_button = function (array $target, string $change, string $label) {
    return form_open('company/submit_member_change', ['class' => 'inline'])
        . form_hidden('member_id', (string) $target['id'])
        . form_hidden('change', $change)
        . form_submit('submit', $label, ['class' => 'button small'])
        . form_close();
};
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
                <?php $is_me = (int) $m['id'] === (int) $member['id']; ?>
                <tr class="<?= (int) $m['active'] === 1 ? '' : 'muted' ?>">
                    <td><?= out($m['name']) ?><?= $is_me ? ' <span class="badge">you</span>' : '' ?></td>
                    <td><?= out($m['email']) ?></td>
                    <td><?= out($roles[$m['role']] ?? $m['role']) ?></td>
                    <td>
                        <?php if ($m['invite_pending']): ?>
                            Invited <?= date('j M', (int) $m['invited_at']) ?>
                        <?php elseif ((int) $m['active'] !== 1): ?>
                            Deactivated
                        <?php else: ?>
                            <?= $m['last_login'] ? 'Last signed in ' . date('j M', (int) $m['last_login']) : 'Active' ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($is_owner): ?>
                        <td class="actions">
                            <?php if ($is_me): ?>
                            <?php elseif ($m['invite_pending']): ?>
                                <input type="text" class="copy-field small" readonly value="<?= out(BASE_URL . 'company/join/' . $m['invite_token']) ?>" onclick="this.select()" aria-label="Invite link for <?= out($m['name']) ?>">
                                <?= $change_button($m, 'revoke', 'Withdraw invite') ?>
                            <?php elseif ((int) $m['active'] !== 1): ?>
                                <?= $change_button($m, 'activate', 'Reactivate') ?>
                            <?php else: ?>
                                <?= $m['role'] === 'owner'
                                    ? $change_button($m, 'make_recruiter', 'Make recruiter')
                                    : $change_button($m, 'make_owner', 'Make owner') ?>
                                <?= $change_button($m, 'deactivate', 'Deactivate') ?>
                            <?php endif; ?>
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
