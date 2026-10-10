<h1><?= $headline ?></h1>
<?= validation_errors() ?>
<div class="card">
    <div class="card-heading">
        Company Details
    </div>
    <div class="card-body">
        <?php
        echo form_open($form_location);

        echo form_label('Name');
        $name_attr = [
            'placeholder' => 'Enter Name',
            'required'  => true,
            'maxlength' => 255
        ];
        echo form_input('name', $name, $name_attr);

        echo form_label('Cvr Number');
        $cvr_number_attr = [
            'placeholder' => 'Enter Cvr Number',
            'maxlength' => 8
        ];
        echo form_input('cvr_number', $cvr_number, $cvr_number_attr);

        echo form_label('Contact Email');
        $contact_email_attr = [
            'placeholder' => 'Enter Contact Email',
            'maxlength' => 255
        ];
        echo form_email('contact_email', $contact_email, $contact_email_attr);

        echo '<label>';
        echo form_checkbox('active', 1, $active);
        echo ' Active';
        echo '</label>';

        echo '<div class="text-center">';
        echo anchor($cancel_url, 'Cancel', ['class' => 'button alt']);
        echo form_submit('submit', 'Submit');
        echo '</div>';
        
        echo form_close();
        ?>
    </div>
</div>
