<h1><?= $headline ?></h1>
<?= validation_errors() ?>
<div class="card">
    <div class="card-heading">
        Company Member Details
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

        echo form_label('Email');
        $email_attr = [
            'placeholder' => 'Enter Email',
            'required'  => true,
            'maxlength' => 255
        ];
        echo form_email('email', $email, $email_attr);

        echo form_label('Role');
        echo form_dropdown('role', $role_options, $role, ['required' => true]);

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
