<?php
$form['color']['color_tab'] = [
  '#type'  => 'vertical_tabs',
];
// Color -> Theme Base Colors
$form['color']['color_theme'] = [
  '#type'        => 'details',
  '#title'       => t('Theme Base'),
  '#group' => 'color_tab',
];
$form['color']['color_theme']['color_theme_default'] = [
  '#type'        => 'details',
  '#title'       => t('Use Color Scheme'),
  '#attributes' => array('class' => array('set-default-fieldset')),
  '#open' => TRUE,
];
$form['color']['color_theme']['color_theme_default']['color_theme_theme'] = [
  '#type'          => 'checkbox',
  '#title'         => t('Use a color scheme.'),
  '#default_value' => theme_get_setting('color_theme_theme', 'eduxpro'),
  '#description'   => t("Check this option to use a pre-defined color scheme or default colors. You can select a color scheme below. Uncheck to customize theme base colors below."),
];
$form['color']['color_theme']['color_scheme_section'] = [
  '#type'        => 'details',
  '#title'       => t('Pre Defined Color Schemes'),
  '#open' => TRUE,
];
$form['color']['color_theme']['color_scheme_section']['color_scheme'] = [
  '#type'          => 'select',
  '#title'       => t('You can select a Pre Defined Color Schemes'),
  '#options' => array(
    'color_default' => t('Default'),
    'color_red' => t('Imperial Red'),
    'color_orange' => t('Orange Gigas'),
    ),
  '#default_value' => theme_get_setting('color_scheme', 'eduxpro'),
];
$form['color']['color_theme']['color_base'] = [
  '#type'        => 'details',
  '#title'       => t('Customize Theme Base Colors'),
  '#open' => TRUE,
];
$form['color']['color_theme']['color_base']['theme_color_one'] = [
  '#type'        => 'color',
  '#field_suffix' => theme_get_setting('theme_color_one', 'eduxpro'),
  '#title'       => t('Primary Theme Color'),
  '#default_value' => theme_get_setting('theme_color_one', 'eduxpro'),
  '#description' => t('<p>Default value is <strong>#FFCC18</strong></p><p><hr /></p>'),
];
$form['color']['color_theme']['color_base']['theme_color_two'] = [
  '#type'        => 'color',
  '#field_suffix' => theme_get_setting('theme_color_two', 'eduxpro'),
  '#title'       => t('Secondary Theme Color'),
  '#default_value' => theme_get_setting('theme_color_two', 'eduxpro'),
  '#description' => t('<p>Default value is <strong>#383a68</strong></p><p><hr /></p>'),
];
$form['color']['color_theme']['color_base']['theme_color_dark'] = [
  '#type'        => 'color',
  '#field_suffix' => theme_get_setting('theme_color_dark', 'eduxpro'),
  '#title'       => t('Dark Color'),
  '#default_value' => theme_get_setting('theme_color_dark', 'eduxpro'),
  '#description' => t('<p>Default value is <strong>#1a1b31</strong></p><p><hr /></p>'),
];
$form['color']['color_theme']['color_base']['theme_color_light'] = [
  '#type'        => 'color',
  '#field_suffix' => theme_get_setting('theme_color_light', 'eduxpro'),
  '#title'       => t('Light Color'),
  '#default_value' => theme_get_setting('theme_color_light', 'eduxpro'),
  '#description' => t('<p>Default value is <strong>#d1d8e0</strong></p><p><hr /></p>'),
];
$form['color']['color_theme']['color_base']['theme_color_border'] = [
  '#type'        => 'color',
  '#field_suffix' => theme_get_setting('theme_color_border', 'eduxpro'),
  '#title'       => t('Border Color'),
  '#default_value' => theme_get_setting('theme_color_border', 'eduxpro'),
  '#description' => t('<p>Default value is <strong>#cccccc</strong></p><p><hr /></p>'),
];
// Color -> Body
$form['color']['color_body'] = [
  '#type'        => 'details',
  '#title'       => t('Body'),
  '#group' => 'color_tab',
];
$form['color']['color_body']['color_body_default'] = [
  '#type'        => 'details',
  '#title'       => t('Body - Set According To Theme Color'),
  '#attributes' => array('class' => array('set-default-fieldset')),
  '#open' => TRUE,
];
$form['color']['color_body']['color_body_default']['color_body_theme'] = [
  '#type'          => 'checkbox',
  '#title'         => t('Set According To Theme Color'),
  '#default_value' => theme_get_setting('color_body_theme', 'eduxpro'),
  '#description'   => t("Check this option to use theme default colors. Uncheck to customize below."),
];
$form['color']['color_body']['color_body_section'] = [
  '#type'        => 'details',
  '#title'       => t('Body'),
  '#open' => TRUE,
];
$form['color']['color_body']['color_body_section']['color_bodybg'] = [
  '#type'        => 'color',
  '#field_suffix' => theme_get_setting('color_bodybg', 'eduxpro'),
  '#title'       => t('Background Color'),
  '#default_value' => theme_get_setting('color_bodybg', 'eduxpro'),
  '#description' => t('<p>Default value is <strong>#ffffff</strong></p><p><hr /></p>'),
];
$form['color']['color_body']['color_body_section']['color_bodytext'] = [
  '#type'        => 'color',
  '#field_suffix' => theme_get_setting('color_bodytext', 'eduxpro'),
  '#title'       => t('Text Color'),
  '#default_value' => theme_get_setting('color_bodytext', 'eduxpro'),
  '#description' => t('<p>Default value is <strong>#222222</strong></p><p><hr /></p>'),
];
// Color -> Typography
$form['color']['color_typography'] = [
  '#type'        => 'details',
  '#title'       => t('Typography'),
  '#group' => 'color_tab',
];
$form['color']['color_typography']['color_typography_default'] = [
  '#type'        => 'details',
  '#title'       => t('According To Theme Color'),
  '#attributes' => array('class' => array('set-default-fieldset')),
  '#open' => TRUE,
];
$form['color']['color_typography']['color_typography_default']['color_typography_theme'] = [
  '#type'          => 'checkbox',
  '#title'         => t('Set According To Theme Color'),
  '#default_value' => theme_get_setting('color_typography_theme', 'eduxpro'),
  '#description'   => t("Check this option to set color according to the theme colors. Uncheck to customize below."),
];
$form['color']['color_typography']['color_typography_color'] = [
  '#type'        => 'details',
  '#title'       => t('Typography'),
  '#open' => TRUE,
];
$form['color']['color_typography']['color_typography_color']['color_bold'] = [
  '#type'        => 'color',
  '#field_suffix' => theme_get_setting('color_bold', 'eduxpro'),
  '#title'       => t('Bold and Heading Color'),
  '#default_value' => theme_get_setting('color_bold', 'eduxpro'),
  '#description' => t('<p>Default value is <strong>#111111</strong></p><p><hr /></p>'),
];
// Color -> header colors
$form['color']['color_header'] = [
  '#type'        => 'details',
  '#title'       => t('Header'),
  '#group' => 'color_tab',
];
// Color -> Sidebar colors
$form['color']['color_sidebar'] = [
  '#type'        => 'details',
  '#title'       => t('Sidebar'),
  '#group' => 'color_tab',
];
// Color -> footer colors
$form['color']['color_footer'] = [
  '#type'        => 'details',
  '#title'       => t('Footer'),
  '#group' => 'color_tab',
];
$form['color']['color_footer']['color_footer_default'] = [
  '#type'        => 'details',
  '#attributes' => array('class' => array('set-default-fieldset')),
  '#title'       => t('According To Theme Color'),
  '#open' => TRUE,
];
$form['color']['color_footer']['color_footer_default']['color_footer_theme'] = [
  '#type'          => 'checkbox',
  '#title'         => t('Set According To Theme Color'),
  '#default_value' => theme_get_setting('color_footer_theme', 'eduxpro'),
  '#description'   => t("Check this option to set footer color according to the theme colors. Uncheck this to customize below."),
];
$form['color']['color_footer']['color_footer_top'] = [
  '#type'        => 'details',
  '#title'       => t('Footer Top'),
  '#open' => TRUE,
];
$form['color']['color_footer']['color_footer_top']['color_footer_topbg'] = [
  '#type'        => 'color',
  '#field_suffix' => theme_get_setting('color_footer_topbg', 'eduxpro'),
  '#title'       => t('Background Color'),
  '#default_value' => theme_get_setting('color_footer_topbg', 'eduxpro'),
  '#description' => t('<p>Default value is <strong>#383a68</strong></p><p><hr /></p>'),
];
$form['color']['color_footer']['color_footer_blocks'] = [
  '#type'        => 'details',
  '#title'       => t('Footer Four Column Blocks'),
  '#open' => TRUE,
];
$form['color']['color_footer']['color_footer_bottom'] = [
  '#type'        => 'details',
  '#title'       => t('Footer Bottom'),
  '#open' => TRUE,
];

// Color -> comment
$form['color']['color_comment'] = [
  '#type'        => 'details',
  '#title'       => t('Comment'),
  '#group' => 'color_tab',
];
$form['color']['color_comment']['color_comment_default'] = [
  '#type'        => 'details',
  '#title'       => t('According To Theme Color'),
  '#attributes' => array('class' => array('set-default-fieldset')),
  '#open' => TRUE,
];
$form['color']['color_comment']['color_comment_default']['color_comment_theme'] = [
  '#type'          => 'checkbox',
  '#title'         => t('Set According To Theme Color'),
  '#default_value' => theme_get_setting('color_comment_theme', 'eduxpro'),
  '#description'   => t("Check this option to set color according to the theme colors. Uncheck to customize below."),
];
$form['color']['color_comment']['color_comment_section'] = [
  '#type'        => 'details',
  '#title'       => t('Individual Comments'),
  '#open' => TRUE,
];
$form['color']['color_comment']['color_comment_section']['color_comment_bg'] = [
  '#type'        => 'color',
  '#field_suffix' => theme_get_setting('color_comment_bg', 'eduxpro'),
  '#title'       => t('Comments Background Color'),
  '#default_value' => theme_get_setting('color_comment_bg', 'eduxpro'),
  '#description' => t('<p>Default value is <strong>#dfe6e9</strong></p><p><hr /></p>'),
];
$form['color']['color_comment']['color_comment_section']['color_comment_head'] = [
  '#type'        => 'color',
  '#field_suffix' => theme_get_setting('color_comment_head', 'eduxpro'),
  '#title'       => t('Comments Background Color'),
  '#default_value' => theme_get_setting('color_comment_head', 'eduxpro'),
  '#description' => t('<p>Default value is <strong>#dcdee2</strong></p><p><hr /></p>'),
];
$form['color']['color_comment']['color_comment_author_section'] = [
  '#type'        => 'details',
  '#title'       => t('Author Comment'),
  '#open' => TRUE,
];
$form['color']['color_comment']['color_comment_author_section']['color_comment_authorbg'] = [
  '#type'        => 'color',
  '#field_suffix' => theme_get_setting('color_comment_authorbg', 'eduxpro'),
  '#title'       => t('Background Color of Author Comment'),
  '#default_value' => theme_get_setting('color_comment_authorbg', 'eduxpro'),
  '#description' => t('<p>Default value is <strong>#dfe6e9</strong></p><p>This will work only if you have enabled <strong>Highlight Author Comments</strong></p>'),
];
