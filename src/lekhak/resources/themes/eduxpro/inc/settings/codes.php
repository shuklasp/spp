<?php
$form['insert_codes']['insert_codes_tab'] = [
  '#type'  => 'vertical_tabs',
];
// Insert Codes -> CSS
$form['insert_codes']['css'] = [
  '#type'        => 'details',
  '#title'       => t('CSS Codes'),
  '#group'       => 'insert_codes_tab',
];
// Insert Codes -> Head
$form['insert_codes']['head'] = [
  '#type'        => 'details',
  '#title'       => t('Head'),
  '#description' => t('<h3>Insert Codes Before &lt;/HEAD&gt;</h3><hr />'),
  '#group' => 'insert_codes_tab',
];
// Insert Codes -> Body
$form['insert_codes']['body'] = [
  '#type'        => 'details',
  '#title'       => t('Body'),
  '#group' => 'insert_codes_tab',
];
// Insert Codes -> css
$form['insert_codes']['css']['css_section'] = [
  '#type'        => 'fieldset',
  '#title'       => t('Custom Styling'),
];

$form['insert_codes']['css']['css_section']['styling'] = [
  '#type'          => 'checkbox',
  '#title'         => t('Enable Additional CSS'),
  '#default_value' => theme_get_setting('styling', 'eduxpro'),
  '#description'   => t("Check this option to enable custom styling. Uncheck to disable this feature.<br />Please refer to this tutorial page. <a href='https://www.drupar.com/doc/eduxpro/custom-css' target='_blank'>How To Use Custom Styling</a>"),
];

$form['insert_codes']['css']['css_section']['styling_code'] = [
  '#type'          => 'textarea',
  '#rows'          => 20,
  '#title'         => t('Custom CSS Codes'),
  '#default_value' => theme_get_setting('styling_code', 'eduxpro'),
  '#description'   => t('Please enter your custom css codes in this text box. You can use it to customize the appearance of your site.<br />Please refer to this tutorial for detail: <a href="https://www.drupar.com/doc/eduxpro/custom-css" target="_blank">Custom CSS</a>'),
];
// Insert Codes -> Head -> Head codes
$form['insert_codes']['head']['insert_head'] = [
  '#type'          => 'checkbox',
  '#title'         => t('Enable custom codes in &lt;head&gt; section'),
  '#default_value' => theme_get_setting('insert_head', 'eduxpro'),
  '#description'   => t("Check this option to enable custom codes in &lt;head&gt; section. Uncheck to disable this feature."),
];
$form['insert_codes']['head']['head_code'] = [
  '#type'          => 'textarea',
  '#title'         => t('&lt;head&gt; Codes'),
  '#default_value' => theme_get_setting('head_code', 'eduxpro'),
  '#description'   => t("Please enter your custom codes for &lt;head&gt; section. These codes will be inserted just before <strong>&lt;/head&gt;</strong>."),
];
// Insert Codes -> Body -> Body start codes
$form['insert_codes']['body']['insert_body_start_section'] = [
  '#type'        => 'fieldset',
  '#title'       => t('Insert code after &lt;BODY&gt; tag'),
];
$form['insert_codes']['body']['insert_body_start_section']['insert_body_start'] = [
  '#type'          => 'checkbox',
  '#title'         => t('Enable custom codes after &lt;body&gt; tag'),
  '#default_value' => theme_get_setting('insert_body_start', 'eduxpro'),
  '#description'   => t("Check this option to enable custom codes after &lt;body&gt; tag. Uncheck to disable this feature."),
];
$form['insert_codes']['body']['insert_body_start_section']['body_start_code'] = [
  '#type'          => 'textarea',
  '#title'         => t('Codes'),
  '#default_value' => theme_get_setting('body_start_code', 'eduxpro'),
  '#description'   => t("Please enter your custom codes after &lt;body&gt; tag. These codes will be inserted just after <strong>&lt;body&gt;</strong> tag."),
];
// Insert Codes -> Body -> Body end codes
$form['insert_codes']['body']['insert_body_end_section'] = [
  '#type'        => 'fieldset',
  '#title'       => t('Insert code before &lt;/BODY&gt; tag'),
];
$form['insert_codes']['body']['insert_body_end_section']['insert_body_end'] = [
  '#type'          => 'checkbox',
  '#title'         => t('Enable custom codes before &lt;/body&gt; tag.'),
  '#default_value' => theme_get_setting('insert_body_end', 'eduxpro'),
  '#description'   => t("Check this option to enable custom codes before &lt;/body&gt; tag. Uncheck to disable this feature."),
];
$form['insert_codes']['body']['insert_body_end_section']['body_end_code'] = [
  '#type'          => 'textarea',
  '#title'         => t('Codes'),
  '#default_value' => theme_get_setting('body_end_code', 'eduxpro'),
  '#description'   => t("Please enter your custom codes before &lt;/body&gt; tag. These codes will be inserted just before <strong>&lt;/body&gt;</strong>."),
];
