<?php

namespace SPP\CLI\Commands;

use SPP\CLI\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Class MakeFormCommand
 * Creates a new SPP form definition (YAML or XML).
 */
class MakeFormCommand extends BaseMakeCommand
{
    protected string $name = 'make:form';
    protected string $description = 'Create a new SPP form definition';

    
    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args): void
    {
        $formName = $this->getArgument($args, 0) ?? null;
        if (!$formName) {
            echo "Usage: php spp.php make:form <name> [--app=appname] [--yaml] [--php]\n";
            return;
        }

        $app = $this->getContext($args);
        $safeName = strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', str_replace('Form', '', $formName)));
        $className = ucfirst($safeName);
        if (strpos(strtolower($className), 'form') === false) {
             $className .= 'Form';
        }

        $isPhpOnly = ($this->hasFlag($args, 'php') || isset($args['php'])) && !($this->hasFlag($args, 'yaml') || isset($args['yaml']));
        $isYamlOnly = ($this->hasFlag($args, 'yaml') || isset($args['yaml'])) && !($this->hasFlag($args, 'php') || isset($args['php']));
        $makeYaml = !$isPhpOnly;
        $makePhp = $isPhpOnly || ($this->hasFlag($args, 'php') || isset($args['php']));

        $title = ucwords(str_replace(['_', '-'], ' ', $safeName));
        $formRoute = strtolower($safeName);

        // 1. Scaffold Declarative Zero-Code YAML Form
        if ($makeYaml) {
            $baseDir = defined('SPP_APP_DIR') ? SPP_APP_DIR : (defined('SPP_BASE_DIR') ? dirname(SPP_BASE_DIR) : dirname(__DIR__, 2));
            $yamlDir = defined('APP_ETC_DIR') ? APP_ETC_DIR . "/{$app}/forms" : "{$baseDir}/etc/apps/{$app}/forms";
            if (!is_dir($yamlDir)) {
                mkdir($yamlDir, 0755, true);
            }
            $yamlPath = "{$yamlDir}/{$safeName}.yml";

            $entityArg = $this->getOption($args, 'entity') ?? ($this->getOption($args, 'from-entity') ?? ($this->getOption($args, 'e') ?? ($args['entity'] ?? null)));
            $fieldsArg = $this->getOption($args, 'fields') ?? ($this->getOption($args, 'f') ?? ($args['fields'] ?? ($args['f'] ?? null)));

            if (!empty($entityArg)) {
                $generated = \SPPMod\SPPView\Forms::generateSchemaFromEntity($entityArg, $app);
                $elements = $generated['elements'] ?? [];
                $formData = [
                    'name' => $safeName,
                    'title' => $title,
                    'action' => "/{$formRoute}",
                    'method' => 'post',
                    'id' => "{$safeName}_form",
                    'entity' => $entityArg,
                    'elements' => $elements
                ];
                $yamlContent = Yaml::dump($formData, 6, 2);
            } elseif (!empty($fieldsArg)) {
                $elements = [];
                $fieldItems = explode(',', (string)$fieldsArg);
                foreach ($fieldItems as $item) {
                    $item = trim($item);
                    if (empty($item)) continue;
                    $parts = explode(':', $item, 3);
                    $fName = trim($parts[0]);
                    $fType = isset($parts[1]) ? trim($parts[1]) : 'text';
                    $fRules = isset($parts[2]) ? trim($parts[2]) : '';

                    $fieldDef = [
                        'type' => 'text',
                        'label' => ucwords(str_replace(['_', '-'], ' ', $fName)),
                        'col' => 6
                    ];

                    if (preg_match('/^select\[(.*)\]$/i', $fType, $m)) {
                        $fieldDef['type'] = 'select';
                        $opts = explode('|', $m[1]);
                        if (count($opts) === 1 && str_contains($m[1], ';')) {
                            $opts = explode(';', $m[1]);
                        }
                        $optionsMap = [];
                        foreach ($opts as $o) {
                            $o = trim($o);
                            $optionsMap[$o] = ucwords(str_replace(['_', '-'], ' ', $o));
                        }
                        $fieldDef['options'] = $optionsMap;
                    } elseif (in_array(strtolower($fType), ['pan', 'aadhaar', 'gstin', 'ifsc', 'pincode', 'indmobile'])) {
                        $fieldDef['type'] = 'text';
                        $fieldDef['validation'] = strtolower($fType);
                    } else {
                        $fieldDef['type'] = $fType;
                    }

                    if ($fRules) {
                        $fieldDef['validation'] = isset($fieldDef['validation']) ? $fieldDef['validation'] . '|' . $fRules : $fRules;
                    }

                    $elements[$fName] = $fieldDef;
                }

                $elements['submit_btn'] = [
                    'type' => 'submit',
                    'label' => 'Submit',
                    'class' => 'btn btn-primary'
                ];

                $formData = [
                    'name' => $safeName,
                    'title' => $title,
                    'action' => "/{$formRoute}",
                    'method' => 'post',
                    'id' => "{$safeName}_form",
                    'elements' => $elements
                ];

                $yamlContent = Yaml::dump($formData, 6, 2);
            } else {
                $yamlContent = <<<YAML
name: {$safeName}
title: "{$title}"
action: "/{$formRoute}"
method: "post"
id: "{$safeName}_form"

elements:
  name:
    type: text
    label: "Full Name"
    placeholder: "e.g. Jane Doe"
    required: true
    min: 3
    max: 100
    col: 6

  email:
    type: email
    label: "Email Address"
    placeholder: "jane@example.com"
    required: true
    col: 6

  category:
    type: select
    label: "Inquiry Category"
    options:
      general: "General Inquiry"
      support: "Support Request"
      billing: "Billing & Accounts"
    col: 6

  account_number:
    type: text
    label: "Account ID"
    placeholder: "e.g. ACC-12345"
    depends_on:
      field: category
      op: "="
      value: billing
    col: 6

  quantity:
    type: number
    label: "Quantity"
    default: 1
    col: 3

  unit_price:
    type: number
    label: "Unit Price ($)"
    default: 25
    col: 3

  total_amount:
    type: number
    label: "Total ($)"
    computed: "quantity * unit_price"
    readonly: true
    col: 6

  topics:
    type: tags
    label: "Tags / Topics"
    placeholder: "Add topic..."
    col: 6

  rating:
    type: rating
    label: "Experience Rating"
    max: 5
    col: 6

  opt_in_updates:
    type: toggle
    label: "Subscribe to notifications"
    col: 6

  message:
    type: textarea
    label: "Message / Description"
    rows: 4
    required: true
    max: 500
    col: 12

  signature:
    type: signature
    label: "Authorized Signature"
    required: true
    col: 6

  verification_otp:
    type: otp
    label: "Security OTP Code"
    digits: 6
    col: 6

  emergency_contacts:
    type: repeater
    label: "Contact Line Items"
    schema:
      contact_name:
        type: text
        label: "Contact Name"
        required: true
      contact_phone:
        type: tel
        label: "Phone Number"
        required: true

  # Zero-JS anti-spam bot trap (auto-validated by SPPHoneypotValidator)
  website_trap:
    type: honeypot

  # Dynamic workflow state machine action buttons and audit comment
  workflow_bar:
    type: workflow_actions

  submit_btn:
    type: submit
    label: "Submit Form"
    class: "btn btn-primary"
YAML;
            }

            file_put_contents($yamlPath, $yamlContent);
            echo "Success: Declarative YAML Form '{$safeName}' created at {$yamlPath}\n";
        }

        // 2. Scaffold Modern PHP Fluent Form Class
        if ($makePhp) {
            $namespace = $this->getNamespace('Forms', $app);
            $targetDir = $this->getTargetDir('forms', $app);
            $targetPath = "{$targetDir}/class." . strtolower($className) . ".php";

            $success = $this->buildFromStub('form', $targetPath, [
                'namespace' => $namespace,
                'className' => $className,
                'formName' => strtolower($className),
                'formRoute' => $formRoute
            ], [
                'description' => "Modern PHP form definition: {$className}",
                'usage' => "Dispatched for form rendering and validation at /{$formRoute}",
                'stability' => 'stable',
                'author' => 'SPP Developer',
                'review_date' => date('Y-m-d'),
                'attributes' => ['tags' => ['form', 'validation', strtolower($className)]]
            ]);

            if ($success) {
                echo "Success: Modern PHP Form {$className} created at {$targetPath}\n";
            }
        }
    }

    public function renderAdminUI(): string
    {
        $name = htmlspecialchars($this->getName());
        $desc = htmlspecialchars($this->getDescription());

        $html = '<div class="command-ui-container">';
        $html .= '  <h3><span class="view-icon">📝</span> <code>' . $name . '</code></h3>';
        $html .= '  <p>' . $desc . '</p>';
        $html .= '  <hr>';
        $html .= '  <div class="form-group">';
        $html .= '    <label>Form Name (e.g. UserForm, ContactForm):</label>';
        $html .= '    <input type="text" id="arg-form-name" class="spp-input" placeholder="FormName">';
        $html .= '  </div>';
        $html .= '  <div class="form-group">';
        $html .= '    <label>Application Context (optional):</label>';
        $html .= '    <input type="text" id="arg-app" class="spp-input" placeholder="e.g. school">';
        $html .= '  </div>';
        $html .= '  <button class="spp-btn" onclick="executeMakeForm()">Generate Form</button>';
        $html .= '  <script>';
        $html .= '    function executeMakeForm() {';
        $html .= '      const formName = document.getElementById("arg-form-name").value.trim();';
        $html .= '      const app = document.getElementById("arg-app").value.trim();';
        $html .= '      if (!formName) { alert("Form Name is required!"); return; }';
        $html .= '      let args = formName;';
        $html .= '      if (app) args += " --app=" + app;';
        $html .= '      window.executeCommand("' . $name . '", args);';
        $html .= '    }';
        $html .= '  </script>';
        $html .= '</div>';

        return $html;
    }
}
