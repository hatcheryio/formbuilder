    <input type="hidden" name="<?= $fld->form_field_name() ?>" id="<?= $fld->form_field_name() ?>"<?php if($fld->form_field_class()->isNotEmpty()): ?> class="<?= $fld->form_field_class() ?>"<?php endif; ?> value="<?= $fld->form_field_hidden_value()->html() ?>">