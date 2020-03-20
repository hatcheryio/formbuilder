<?php

    // FLAGS and VARIABLES that make our code easier to read:
    $name = $fld->field_name();

    if($data != false and isset($data[$name->value()])) {
        // this is a return to a previously entered form -
        // we need to populate the field with the previously entered value:
        $value = $data[$name->value()];
    } else {
        // this is a brand new form - enter the default value from the panel:
        $value = '';
    }

?>

<div style="display: none;" aria-hidden="true">
    <label for="<?= $name ?>"><?= $name ?></label>
    <input type="text" name="<?= $name ?>" id="<?= $name ?>" autocomplete="off" value="<?= $value ?>">
</div>
