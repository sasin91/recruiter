<h1>Manage User AI Keys</h1>
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
                <th class="text-left">Trongate User Id</th>
            <th class="text-left">Provider</th>
            <th class="text-left">Model</th>
            <th class="text-left">Key Hint</th>
            <th class="text-left">Updated At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($rows as $row): 
                $link_attr = [
                    'mx-get' => 'user_llm_keys/show/'.$row->id,
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
                <td><?= anchor('#', out($row->trongate_user_id), $link_attr) ?></td>
                <td><?= out($row->provider) ?></td>
                <td><?= out($row->model) ?></td>
                <td><?= out($row->key_hint) ?></td>
                <td><?= out($row->updated_at) ?></td>                    <td>
                        <div class="actions">
                            <a href="user_llm_keys/show/<?= $row->id ?>" class="button alt button-round"><i class="tg tg-eye"></i></a>
                            <a href="user_llm_keys/delete_conf/<?= $row->id ?>" class="button alt button-round"><i class="tg tg-trash"></i></a>
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
    window.location.href = '<?= BASE_URL ?>user_llm_keys/set_per_page/' + selectedIndex;
}
</script>
