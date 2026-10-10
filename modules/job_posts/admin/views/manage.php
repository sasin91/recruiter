<h1>Manage Job Posts</h1>
<?= flashdata() ?>
<?php if (!empty($search_query)): ?>
    <p>Showing results for <strong><?= out($search_query) ?></strong></p>
<?php endif; ?>
<?php
echo '<p class="flex-row justify-between">';
if ((count($rows) > 9) || (!empty($search_query))) {
    $btn_attr = [
        'class' => 'alt',
        'mx-get' => 'job_posts-admin/search_modal',
        'mx-build-modal' => json_encode([
            'id' => 'search-modal',
            'modalHeading' => 'Search Job Posts',
            'modalFooter' => '<button class="alt" onclick="closeModal()">Cancel</button><button form="search-form">Search</button>'
        ])
    ];
    echo form_button('search_btn', 'Search <i class="tg tg-search"></i>', $btn_attr);
}
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
                <th colspan="6">
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
                <th class="text-left">Company Id</th>
            <th class="text-left">Title</th>
            <th class="text-left">Status</th>
            <th class="text-left">Version</th>
            <th class="text-left">Published At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($rows as $row): 
                $link_attr = [
                    'mx-get' => 'job_posts-admin/show/'.$row->id,
                    'mx-select' => '.detail-grid',
                    'mx-build-modal' => json_encode([
                        'id' => 'record-preview-modal',
                        'width' => '640px',
                        'modalHeading' => 'Record Preview',
                        'modalFooter' => '<button class="alt" onclick="closeModal()">Cancel</button><button onclick="window.location.href=\''.BASE_URL.segment(1).'/show/'.$row->id.'\'">View Record</button>'
                    ])
                ];
                ?>
                <tr>
                <td><?= anchor('#', out($row->company_id), $link_attr) ?></td>
                <td><?= out($row->title) ?></td>
                <td><?= out($row->status) ?></td>
                <td><?= out($row->version) ?></td>
                <td><?= out($row->published_at) ?></td>                    <td>
                        <div class="actions">
                            <a href="job_posts-admin/show/<?= $row->id ?>" class="button alt button-round"><i class="tg tg-eye"></i></a>
                            <a href="job_posts-admin/delete_conf/<?= $row->id ?>" class="button alt button-round"><i class="tg tg-trash"></i></a>
                        </div>
                    </td>
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
    window.location.href = '<?= BASE_URL ?>job_posts-admin/set_per_page/' + selectedIndex;
}
</script>
