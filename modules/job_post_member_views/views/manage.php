<h1>Manage Job Post Views</h1>
<?= flashdata() ?>
<?php
echo '<p class="flex-row justify-between">';
echo '</p>';
if (empty($rows)) {
    echo '<p>There are currently no records to display.</p>';
    return;
}
echo Modules::run('pagination/display', $pagination_data);
?>

<div class="table-container">
    <table class="records-table">
        <thead>
            <tr>
                <th colspan="3">
                    <div>
                        <div>&nbsp;</div>
                        <div>Records Per Page: <?php
                        $dropdown_attr['onchange'] = 'setPerPage()';
                        echo form_dropdown('per_page', $per_page_options, $selected_per_page, $dropdown_attr); 
                        ?></div>
                    </div>                    
                </th>
            </tr>
            <tr>
                <th class="text-left">Job Post Id</th>
            <th class="text-left">Company Member Id</th>
            <th class="text-left">Last Viewed At</th>
                            </tr>
        </thead>
        <tbody>
            <?php foreach($rows as $row): ?>
                <tr>
                <td><?= out($row->job_post_id) ?></td>
                <td><?= out($row->company_member_id) ?></td>
                <td><?= out($row->last_viewed_at) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php 
if(count($rows)>9) {
    unset($pagination_data['include_showing_statement']);
    echo Modules::run('pagination/display', $pagination_data);
}
?>

<script>
function setPerPage() {
    const selectedIndex = document.querySelector('select[name="per_page"]').value;
    window.location.href = '<?= BASE_URL ?>job_post_member_views/set_per_page/' + selectedIndex;
}
</script>
