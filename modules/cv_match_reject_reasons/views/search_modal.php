<?php
/**
 * Search modal form for filtering cv match reject reasons.
 *
 * Loaded via MX into a modal when the Search button is clicked.
 */
$searchable_columns = [
    'reason' => 'Reason',
];

$form_attr = ['id' => 'search-form'];
echo form_open('cv_match_reject_reasons/submit_search', $form_attr);
?>
    <div class="form-group">
        <?= form_label('Search Query') ?>
        <?= form_input('search_query', '', ['placeholder' => 'Enter search term...', 'autocomplete' => 'off']) ?>
    </div>
    <div class="form-group">
        <?= form_label('Search In') ?>
        <?= form_dropdown('search_column', $searchable_columns) ?>
    </div>
<?= form_close() ?>
