<?php
/**
 * Search modal form for filtering unmatched terms.
 *
 * Loaded via MX into a modal when the Search button is clicked.
 */
$searchable_columns = [
    'normalised' => 'Normalised',
    'example_raw_text' => 'Example Raw Text',
];

$form_attr = ['id' => 'search-form'];
echo form_open('unmatched_terms/submit_search', $form_attr);
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
