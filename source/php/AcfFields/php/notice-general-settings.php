<?php 

if (function_exists('acf_add_local_field_group')) {
    acf_add_local_field_group(array(
    'key' => 'group_69720e679fc2b',
    'title' => __('Noticeboard settings', 'modularity-toc'),
    'fields' => array(
        0 => array(
            'key' => 'field_697a06c6be319',
            'label' => __('Post type name', 'modularity-toc'),
            'name' => 'post_type_slug',
            'aria-label' => '',
            'type' => 'text',
            'instructions' => __('If you want a particular name for the notice post type, for instance notis, you can replace this value. Don\'t forget to save permalinks after changing this.', 'modularity-toc'),
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'default_value' => '',
            'maxlength' => '',
            'allow_in_bindings' => 0,
            'placeholder' => __('notice', 'modularity-toc'),
            'prepend' => '',
            'append' => '',
        ),
        1 => array(
            'key' => 'field_697221a4b9062',
            'label' => __('Use specific page for noticeboard', 'modularity-toc'),
            'name' => 'custom_archive_page',
            'aria-label' => '',
            'type' => 'true_false',
            'instructions' => __('Don\'t forget to save permalinks after changing this', 'modularity-toc'),
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
        2 => array(
            'key' => 'field_69720e67a61d4',
            'label' => __('Noticeboard archive page', 'modularity-toc'),
            'name' => 'noticeboard_main_page',
            'aria-label' => '',
            'type' => 'post_object',
            'instructions' => __('Will be used for module archive button and breadcrumbs', 'modularity-toc'),
            'required' => 1,
            'conditional_logic' => array(
                0 => array(
                    0 => array(
                        'field' => 'field_697221a4b9062',
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
            'post_type' => array(
                0 => 'page',
            ),
            'post_status' => array(
                0 => 'publish',
            ),
            'taxonomy' => '',
            'return_format' => 'id',
            'multiple' => 0,
            'save_custom' => 0,
            'save_post_status' => 'publish',
            'acfe_bidirectional' => array(
                'acfe_bidirectional_enabled' => '0',
            ),
            'allow_null' => 0,
            'allow_in_bindings' => 0,
            'bidirectional' => 0,
            'ui' => 1,
            'bidirectional_target' => array(
            ),
            'save_post_type' => '',
        ),
        3 => array(
            'key' => 'field_697a0cd481bf1',
            'label' => __('Breadcrumb title', 'modularity-toc'),
            'name' => 'breadcrumb_title',
            'aria-label' => '',
            'type' => 'text',
            'instructions' => '',
            'required' => 0,
            'conditional_logic' => 0,
            'wrapper' => array(
                'width' => '',
                'class' => '',
                'id' => '',
            ),
            'default_value' => '',
            'maxlength' => '',
            'allow_in_bindings' => 0,
            'placeholder' => __('Notices', 'modularity-toc'),
            'prepend' => '',
            'append' => '',
        ),
    ),
    'location' => array(
        0 => array(
            0 => array(
                'param' => 'options_page',
                'operator' => '==',
                'value' => 'acf-options-settings',
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