<?php

    // // FLAGS and VARIABLES that make our code easier to read:
    $name = $fld->field_name();
    $class = $fld->field_class()->isEmpty() ? false : $fld->field_class()->html();
    $label = $fld->field_label()->isEmpty() ? false : $fld->field_label()->html();
    $value = $fld->default()->isNotEmpty() ? $fld->default()->html() : false;
    $req = $fld->req()->toBool();

    if($data != false){
        // this is a return to a previously entered form -
        // we need to set the checkbox to its previous checked state:
        if(isset($data[$name->value()])) {
            $checked = true;
        } else {
            $checked = false;
        }
    } else {
        // this is a brand new form - enter the default checked state from the panel:
        $checked = $fld->checked()->toBool();
    }

?>

<div<?php if($class): ?> class="<?= $class ?>"<?php endif; ?>>
    <input type="checkbox" name="<?= $name ?>" id="<?= $name ?>"<?php if($class): ?> class="<?= $class ?>"<?php endif; ?><?php if($value):?> value="<?= $value ?>"<?php endif; ?><?php if($checked):?> checked<?php endif; ?><?php if($req): ?> required<?php endif; ?>>
    <?php if($label):?><label for="<?= $name ?>"><?= $label ?></label><?php endif; ?>
</div>
