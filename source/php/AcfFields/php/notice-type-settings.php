<?php 

if (function_exists('acf_add_local_field_group')) {
    acf_add_local_field_group(array(
    'key' => 'group_696798e2dc065',
    'title' => __('Noticeboard: Notice type', 'modularity-toc'),
    'fields' => array(
        0 => array(
            'key' => 'field_696798e397f46',
            'label' => __('Automatic archiving?', 'modularity-toc'),
            'name' => 'automatic_archiving',
            'aria-label' => '',
            'type' => 'true_false',
            'instructions' => __('Requires the cron job for archiving to be set up', 'modularity-toc'),
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'message' => '',
            'default_value' => 0,
            'allow_in_bindings' => 0,
            'ui_on_text' => '',
            'ui_off_text' => '',
            'ui' => 1,
        ),
        1 => array(
            'key' => 'field_6967992197f47',
            'label' => __('Archive after X amount of days', 'modularity-toc'),
            'name' => 'archiving_days',
            'aria-label' => '',
            'type' => 'number',
            'instructions' => '',
            'required' => 1,
            'conditional_logic' => array(
                0 => array(
                    0 => array(
                        'field' => 'field_696798e397f46',
                        'operator' => '==',
                        'value' => '1',
                    ),
                ),
            ),
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'default_value' => 21,
            'min' => '',
            'max' => '',
            'allow_in_bindings' => 0,
            'placeholder' => '',
            'step' => 1,
            'prepend' => '',
            'append' => __('days', 'modularity-toc'),
        ),
    ),
    'location' => array(
        0 => array(
            0 => array(
                'param' => 'taxonomy',
                'operator' => '==',
                'value' => 'noticeboard_notice_type',
            ),
        ),
    ),
    'menu_order' => 0,
    'position' => 'normal',
    'style' => 'default',
    'label_placement' => 'left',
    'instruction_placement' => 'label',
    'hide_on_screen' => '',
    'active' => true,
    'description' => '',
    'show_in_rest' => 0,
    'display_title' => '',
    'acfe_autosync' => array(
        0 => 'json',
    ),
    'acfe_form' => 0,
    'acfe_meta' => '',
    'acfe_note' => '',
));
}