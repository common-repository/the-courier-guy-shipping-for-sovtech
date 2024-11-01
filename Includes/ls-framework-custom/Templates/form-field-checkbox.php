<?php

$checked = (! empty($value) ? ' checked' : '');
?>
<input type="checkbox" id="<?php echo esc_attr($identifier); ?>" name="<?php echo esc_attr($identifier); ?>"<?php echo esc_attr($checked); ?><?php echo esc_attr($readonly); ?> />
