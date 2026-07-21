<?php

return [
    'nav' => 'Custom fields',
    'title' => 'Custom fields',
    'intro' => 'Define your own task fields. Each field has a stable key (its API name), labels (DE/EN), a data type and an optional Laravel validation rule. Values are filled per task via the API (field :field inside the custom_fields object).',
    'field_placeholder' => 'custom_fields',

    'col_key' => 'Key',
    'col_label' => 'Label (DE)',
    'col_label_en' => 'Label (EN)',
    'col_type' => 'Type',
    'col_validation' => 'Validation (Laravel)',
    'col_actions' => '',

    'validation_placeholder' => 'e.g. required|max:100',
    'save' => 'Save',
    'saved_all' => 'Fields saved.',
    'add_field' => 'Add field',
    'presets_label' => 'Presets:',
    'preset_exists' => 'Field “:label” already exists.',
    'created' => 'Field “:label” created.',
    'delete' => 'Delete',
    'delete_confirm' => 'Really delete this field? Values already set on tasks remain.',
    'deleted' => 'Field deleted.',
    'no_fields' => 'No custom fields yet.',

    // Type labels
    'type_string' => 'Text (short)',
    'type_text' => 'Text (long)',
    'type_integer' => 'Integer',
    'type_decimal' => 'Decimal',
    'type_boolean' => 'Yes/No',
    'type_date' => 'Date',
    'type_datetime' => 'Date + time',
    'type_url' => 'URL',
    'type_email' => 'Email',
];
