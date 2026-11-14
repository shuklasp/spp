# 06. Forms & Validation

SPP provides an enterprise-grade form and validation system designed around a single core invariant: **write your validation logic once, and validate everywhere**. 

In traditional frameworks, developers often write validation rules in PHP for backend controllers, and then write duplicate JavaScript regex or HTML attributes for frontend client checking. SPP eliminates this duality through a unified 4-way validation pipeline that bridges **Server-Side PHP**, **Browser Native HTML5**, **SPP-UX Client JS**, and **Drishyam LiveComponents**.

---

## 1. Quickstart: The Fluent Builder API

The most direct way to build validated forms programmatically in SPP is using the Fluent Builder API via `Form` (`SPPMod\SPPView\Form`):

```php
use SPPMod\SPPView\Form;

$form = Form::create('registration_form', '/register')
    ->addText('username', 'Username', ['required' => true, 'min' => 3])
    ->addEmail('email', 'Email Address', ['required' => true])
    ->addPassword('password', 'Password', ['required' => true, 'min' => 8])
    ->addSubmit('submit_btn', 'Create Account')
    ->build();

// In your view or template:
echo $form->render();
```

When rendered, the `<input>` tags automatically receive native HTML5 constraints (`required="required"`, `minlength="3"`, `type="email"`), ensuring instant client-side validation before the form is even submitted.

---

## 2. Declarative Form Definitions in YAML

In enterprise SPP applications, forms are typically defined declaratively in YAML under `etc/apps/<AppName>/forms/<name>.yml` (or `src/<AppName>/etc/forms/<name>.yml`).

```yaml
# etc/apps/school1/forms/student_enrollment.yml
form:
  name: student_enrollment_form
  title: "Student Enrollment"
  action: "/students/enroll"
  method: post
  id: "student_enrollment_form"

fields:
  student_name:
    type: text
    label: "Full Name"
    placeholder: "e.g. Aarav Sharma"
    rules: "required|min:3|max:100"
    col: 6

  email:
    type: email
    label: "Parent Email"
    placeholder: "parent@example.com"
    rules: "required|email"
    col: 6

  admission_code:
    type: text
    label: "Admission Code"
    placeholder: "SCH-2026-01"
    rules: "required|admission_no"
    col: 6

  parent_pan:
    type: text
    label: "Parent PAN Card"
    placeholder: "ABCDE1234F"
    rules: "required|pan"
    col: 6

  submit_btn:
    type: submit
    value: "Enroll Student"
    class: "btn btn-primary"
```

To instantiate and render the YAML form in your controller:

```php
use SPPMod\SPPView\ViewFormBuilder;

// Automatically compiles fields, HTML5 attributes, and validators:
$form = ViewFormBuilder::fromYaml('forms/student_enrollment.yml');

echo $form->render();
```

---

## 3. Universal Custom Validators (`Rule`)

SPP allows you to create custom validators by extending `\SPPMod\SPPView\Validation\Rule`. A single `Rule` class defines both the PHP backend validation and the client-side HTML5 / SPP-UX constraints:

```php
namespace App\School1\Rules;

use SPPMod\SPPView\Validation\Rule;

class AdmissionNoRule extends Rule
{
    public function __construct(
        protected string $pattern = '^[A-Z]{3}-[0-9]{4}-[A-Z]{2}$',
        ?string $customMessage = null
    ) {
        if ($customMessage !== null) {
            $this->withMessage($customMessage);
        }
    }

    /**
     * 1. Server-Side Validation (PHP)
     */
    public function passes(string $attribute, mixed $value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Optional by default; combine with 'required' if mandatory
        }
        return (bool)preg_match('/' . $this->pattern . '/', (string)$value);
    }

    /**
     * 2. Custom Error Message
     */
    public function message(string $attribute): string
    {
        $label = ucfirst(str_replace(['_', '-'], ' ', $attribute));
        return "{$label} must follow the format ABC-YYYY-XX.";
    }

    /**
     * 3. Client-Side Constraints (HTML5 & SPP-UX)
     */
    public function clientRule(string $attribute): array
    {
        return [
            'pattern' => $this->pattern,
            'minlength' => 11,
            'maxlength' => 11,
            'data-rule' => 'admission_no',
            'data-msg' => $this->message($attribute),
        ];
    }

    /**
     * 4. (Optional) Real-Time Client JavaScript Logic
     */
    public function clientJs(): ?string
    {
        return "return new RegExp('^' + '{$this->pattern}' + '$').test(value);";
    }
}
```

---

## 4. Declarative YAML Validator Registration (`validators.yml`)

Instead of writing PHP bootstrapping code to register rules, SPP auto-discovers custom validators declared in `validators.yml`:
- **App-Specific**: `etc/apps/<AppName>/validators.yml` or `src/<AppName>/etc/validators.yml`
- **Global Project**: `etc/validators.yml`

```yaml
# etc/apps/school1/validators.yml
validators:
  # 1. Regex Pattern Validator (auto-generates HTML5 pattern & lengths)
  admission_no:
    pattern: '^[A-Z]{3}-[0-9]{4}-[A-Z]{2}$'
    message: 'Admission number must follow format ABC-YYYY-XX.'
    minlength: 11
    maxlength: 11
    data-rule: 'admission_no'

  # 2. Enum / In-List Validator
  curriculum_track:
    in: ['cbse', 'icse', 'state', 'ib']
    message: 'Curriculum track must be cbse, icse, state, or ib.'

  # 3. Numeric Range Validator
  exam_score:
    range: [0, 100]
    message: 'Score must be between 0 and 100.'

  # 4. Class-Based Custom Rule with Parameters
  strong_pass:
    class: 'App\School1\Rules\StrongPasswordRule'
    params: [10]
    message: 'Password must be at least 10 characters with mixed cases and symbols.'

  # 5. Inline JavaScript Validator
  subdomain:
    pattern: '^[a-z0-9-]+$'
    message: 'Subdomain can only contain lowercase letters, numbers, and hyphens.'
    js: 'return /^[a-z0-9-]+$/.test(value) && !value.startsWith("-") && !value.endsWith("-");'
```

### Applying in Form YAML
Once defined in `validators.yml`, simply reference the rule alias anywhere in your form YAML:

```yaml
fields:
  track:
    type: select
    rules: "required|curriculum_track"

  score:
    type: number
    rules: "required|exam_score"
```

You can also define **form-local embedded validators** directly in any form's YAML header:

```yaml
# forms/teacher.yml
form:
  name: teacher_form
validators:
  emp_id:
    pattern: '^TCH-[0-9]{4}$'
    message: 'Teacher ID must be TCH-XXXX.'
fields:
  code:
    type: text
    rules: "required|emp_id"
```

---

## 5. Built-in Universal & Country-Specific ID Validators

SPP comes pre-equipped with high-performance, pre-bridged validators for common identity and banking formats. These work out-of-the-box in both PHP and HTML5 without any configuration:

| Alias | Target Format | HTML5 Pattern | Client Attributes |
| :--- | :--- | :--- | :--- |
| `pan` | Indian PAN Card | `[A-Z]{5}[0-9]{4}[A-Z]{1}` | `minlength="10" maxlength="10"` |
| `aadhaar` | 12-Digit UIDAI Aadhaar | `\d{12}` | `minlength="12" maxlength="12"` |
| `gstin` | 15-Digit GSTIN Number | `[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}` | `minlength="15" maxlength="15"` |
| `ifsc` | Bank IFSC Code | `^[A-Z]{4}0[A-Z0-9]{6}$` | `minlength="11" maxlength="11"` |
| `pincode` | 6-Digit Indian PIN Code | `^[1-9][0-9]{5}$` | `minlength="6" maxlength="6"` |
| `indmobile` | 10-Digit Indian Mobile | `^[6-9]\d{9}$` | `type="tel" minlength="10"` |
| `passwordstrength` | Enterprise Password | `^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$` | `minlength="8"` |
| `creditcard` | Luhn Check Card | `^[0-9]{13,19}$` | `pattern="^[0-9]{13,19}$"` |
| `email` | RFC 5322 Email | Browser email parser | `type="email"` |
| `url` | Web URL | Browser URL parser | `type="url"` |

---

## 6. The 4-Way Validation Sync Engine

When a form is compiled via `ViewFormBuilder` or rendered in a template:

```
                  Form YAML / validators.yml
                              │
                              ▼
        ViewFormBuilder & CustomRuleRegistry Auto-Resolution
                              │
          ┌───────────────────┴───────────────────┐
          ▼                                       ▼
1. HTML5 Tag Attributes                 2. SPP-UX Synthetic Events
   - pattern="^[1-9][0-9]{5}$"             - onblur="validateCustomRule(...)"
   - minlength="6" maxlength="6"           - window.SPPValidator registry
   - data-rule="..." data-msg="..."        - Instant red border & error span
          │                                       │
          └───────────────────┬───────────────────┘
                              │
                              ▼
                3. Server-Side PHP Validation
                   - $form->bind($_POST)
                   - $form->isValid() executes Rule::passes()
                   - Guaranteed bypass prevention & $form->getErrors()
```

### In Blade & LiveComponents
Use the `@clientRules` directive in Blade to attach client constraints to raw HTML elements:

```blade
<input type="text" name="pan_number" @clientRules('pan_number', ['required', 'pan']) />
@error('pan_number')
    <span class="text-danger">{{ $message }}</span>
@enderror
```

Output generated in HTML:
```html
<input type="text" name="pan_number" required="required" pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" minlength="10" maxlength="10" data-rule="pan" data-msg="Invalid PAN number." onblur="validateCustomRule('pan_number_error', ...)" />
```

---

## 7. Direct Entity Data Binding (`SPPEntity`)

Forms seamlessly bind to database entities (`SPPEntity`) for bidirectional hydration:

### Hydrating Forms (Read)
Use `bind()` to automatically pre-fill inputs from an existing entity or array:

```php
$student = Student::find(42);
$form->bind($student); 

// All inputs (<input value="...">) are automatically hydrated!
echo $form->render();
```

### Hydrating Entities & Validating (Write)
When processing submissions, use `fill()` to copy request parameters into the entity:

```php
$student = new Student();
$form->fill($student);

if ($form->isValid()) {
    $student->save(); // Persisted safely
    return $this->redirect('/students');
}

// Validation errors are available for display:
$errors = $form->getErrors(); // ['admission_code' => 'Invalid format']
```

---

## 8. Conditional Validation (`when` & `unless`)

Rules support fluent conditional execution so checks only execute when specific form criteria are met:

```php
$rule = (new StrongPasswordRule(12))
    ->when(fn($data) => !empty($data['require_high_security']));

$taxRule = (new TaxIdRule())
    ->unless(fn($data) => ($data['citizenship'] ?? '') === 'foreign');
```

In YAML forms, use conditional rules such as `required_if`:

```yaml
fields:
  gstin:
    type: text
    label: "GST Number"
    rules: "required_if:is_business,1|gstin"
```

---

## 9. Developer CLI Tooling

SPP provides first-class CLI commands to scaffold forms and custom validation rules:

### Scaffold a New Form
```bash
# Scaffold a YAML form definition
php spp.php make:form ContactForm --app=school1

# Scaffold with fluent PHP builder
php spp.php make:form ContactForm --app=school1 --php
```

### Scaffold a Universal Custom Validator Rule
```bash
# Scaffold a custom rule and auto-register in validators.yml:
php spp.php make:rule AdmissionCode --app=school1 --pattern="^[A-Z]{3}-[0-9]{4}$" --min=8 --max=8
```

This single command generates:
1. `src/school1/Rules/AdmissionCodeRule.php` extending `Rule`.
2. Registers `'admission_code'` in `etc/apps/school1/validators.yml`.
3. Displays copy-pasteable usage examples for Form YAML, LiveComponents, Blade, and Controllers.

---

## 10. SPP Developer Studio (`sppdev`) Form & Validation Studio

For developers who prefer visual tooling, SPP integrates a complete **Form & Validation Studio** directly inside `sppdev` (`/sppdev` or `spp/dev/`):

### 1. Visual Drag & Drop Form Builder
- **New Enterprise & Regional Element Types**: Directly drag & drop dedicated fields for **PAN Card** (`pan`), **Aadhaar Card** (`aadhaar`), **GSTIN** (`gstin`), **IFSC Code** (`ifsc`), and **PIN Code** (`pincode`) from the palette into your forms.
- **Enhanced Field Inspector (Tab 4: Validation Rules)**:
  - **Standard Rules**: One-click checkboxes for `required`, `email`, `numeric`, `url`, `alpha`, `alphanumeric`.
  - **Enterprise & Identity IDs**: One-click toggles for `pan`, `aadhaar`, `gstin`, `ifsc`, `pincode`, `indmobile`, `passwordstrength`, `creditcard`.
  - **Dynamic App Custom Rules**: Automatically enumerates all custom rules defined in your application's `validators.yml` as selectable checkboxes.
  - **Numeric & String Constraints**: Dedicated inputs for `min` and `max` limits.
  - **Pipeline String & Quick Generator**: Direct text editing of custom pipeline strings with a **"⚡ + New Custom Rule"** button to scaffold rules without leaving the inspector.

### 2. Custom Validators & Rule Studio (`sppdev` Suite Tab)
Navigate to **Form Designer & Manifests** in `sppdev` and select the **"🛡️ Custom Validators & Rule Studio"** tab:
- **Rule Catalog & Metrics**: Visual overview of all built-in rules and app-specific custom rules with instant counts and category filtering (Identity, Financial, Security, App Rules).
- **Interactive Live Rule Tester**: Test regular expressions and format validation live in the browser on any rule card. As you type, real-time feedback indicates valid format (✔) or error message (❌).
- **Visual Rule Scaffolding**: Click **"+ New Custom Rule"** to scaffold rules via GUI:
  - Configure alias, regex pattern, min/max length, and custom failure message.
  - Check **"Automatically scaffold PHP Rule class"** to generate `src/<app>/Rules/<Name>Rule.php` and update `etc/apps/<app>/validators.yml` simultaneously.
- **1-Click Code Studio Deep-Linking**:
  - Saving a rule with a PHP class instantly opens **Code Studio** (`#coder?file=...`) with the newly scaffolded class loaded and focused.
  - Every custom rule card includes a **`💻 Code`** button to open the underlying PHP file directly in Code Studio at any time.
- **Manage & Delete**: Edit patterns or delete obsolete rules from `validators.yml` with a single click.

---

## 11. Plain HTML Forms with YAML ID Binding (`@sppform_bind`)

In many real-world scenarios, developers want to write pure, bespoke HTML markup (e.g. customized Tailwind CSS, Bootstrap, or hand-coded templates) rather than generating forms programmatically, while still enjoying all the benefits of declarative YAML validation rules.

SPP makes this effortless with the `@sppform_bind('form_id')` directive.

### 1. In Your Blade Template:
Write standard semantic HTML and bind the form by its YAML identifier:

```html
@extends('layouts.base')

@section('content')
<div class="container py-4">
    <h2>Student Admission Form</h2>

    <!-- Plain HTML form bound directly to student_enrollment YAML schema -->
    <form id="student_enrollment_form" method="POST" action="/students/enroll" @sppform_bind('student_enrollment')>
        <div class="mb-3">
            <label for="first_name" class="form-label">First Name</label>
            <input type="text" id="first_name" name="first_name" class="form-control" placeholder="Aarav">
            <!-- SPP Validator automatically injects invalid-feedback here -->
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Parent Email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="parent@example.com">
        </div>

        <div class="mb-3">
            <label for="pan" class="form-label">Parent PAN Card</label>
            <input type="text" id="pan" name="parent_pan" class="form-control" placeholder="ABCDE1234F">
        </div>

        <button type="submit" class="btn btn-primary">Submit Application</button>
    </form>
</div>
@endsection
```

### 2. Client-Side Magic (`sppvalidations.js`):
When the page loads:
1. `@sppform_bind` outputs `data-spp-form="student_enrollment"` and embeds an isolated `<script type="application/json" id="spp-form-schema-student_enrollment">`.
2. `SPPValidator.autoBindAllForms()` runs automatically, attaching live listeners to each input matching a field in the YAML definition.
3. As the user types:
   - Built-in and custom regular expressions (such as Indian PAN, Aadhaar, email, or custom patterns) are tested instantly.
   - Standard validation classes (`is-valid`, `is-invalid`) are toggled on the elements.
   - Dynamic error spans (`<div class="spp-field-error text-danger">`) display failure messages directly below invalid fields.
   - Form submission is automatically intercepted and prevented until all fields are valid.

### 3. Server-Side Controller Validation (`validateForm`):
In your controller action extending `ViewController`:

```php
namespace App\School1\Controllers;

use SPPMod\SPPView\ViewController;
use SPPMod\SPPView\Attributes\Route;
use App\School1\Entities\Student;

class StudentController extends ViewController
{
    #[Route('/students/enroll', method: 'POST')]
    public function enroll()
    {
        // 1. Zero-Parameter Form Validation!
        // SPP resolves 'student_enrollment' automatically from the request's hidden `_spp_form` input:
        $validation = $this->validateForm();

        // 2. Ultra-Clean Error Handling with Smart Content Negotiation
        if ($validation->fails()) {
            return $this->backWithErrors($validation);
        }

        // 3. Mass-Assignment Safe Validated Data & Magic Property Access!
        $student = new Student();
        $student->first_name = $validation->first_name;
        $student->email      = $validation->email;
        $student->parent_pan = $validation->parent_pan;
        $student->save();

        $this->flash('success', 'Student enrolled successfully!');
        return $this->redirect('/students');
    }
}
```

### Modern Ergonomics: `validated()`, `only()`, `except()`, and Magic Access
- `$validation->validated()`: Returns strictly the sanitized, validated fields declared in the form schema.
- `$validation->validated('email')`: Extracts a single validated field with fallback support.
- `$validation->only(['first_name', 'email'])`: Slices a subset of keys for active record models.
- `$validation->except(['confirm_password'])`: Excludes specific parameters.
- `$validation->field_name`: Magic getter reading directly from the safe validated data slice.

### Automatic Entity Hydration with `@sppform_bind('form_id', $entity)`
When building update or edit views, simply provide the model or array as the second argument:
```blade
{{-- Injects schema, _spp_form, and auto-populates all inputs from $student --}}
@sppform_bind('student_enrollment', $student)
```
SPP automatically pre-populates `<input>`, `<select>`, and `<textarea>` elements, preserves `old` input on failed submissions, and attaches inline error messages.

### Fast CLI Form Scaffolding with `--fields`
Scaffold complete forms with fields, select dropdown options, and validation rules in one command:
```bash
php spp.php make:form StudentEnrollment \
    --fields="first_name:text:required|min:3,email:email:required,role:select[student|alumni],tax_pan:pan"
```

---

## 12. Visual Forms & Rule Studio in Drishyam Studio (`#pages`)

Within **Drishyam Studio** (`/sppdev/#pages`), developers can design, bind, and test forms visually on the live WYSIWYG canvas:

1. **Dedicated 📝 Forms Palette Tab**:
   - Located directly adjacent to `📦 Palette`, `⚡ Live`, `🌲 Tree`, and `📚 Layers`.
   - Lists all YAML Form Manifests discovered across `etc/apps/<AppName>/forms/` and `src/<AppName>/etc/forms/`.
   - Lists all Registered Validator Rules (both built-in and app-specific).

2. **1-Click ➕ Insert Form**:
   - Clicking `➕ Insert Form` next to any manifest synthesizes a complete semantic HTML form onto the live canvas with matching input elements, labels, and the `@sppform_bind(...)` directive pre-attached.

3. **1-Click 🔗 Bind**:
   - Selecting any existing `<form>` tag on the canvas and clicking `🔗 Bind` on a manifest card synchronizes the form `id`, `data-spp-form` attribute, and embedded schema without manual typing.

4. **1-Click 🧪 Test (Live Form Sandbox)**:
   - Clicking `🧪 Test` opens an interactive sandbox modal where developers can enter test data, view live JSON payloads, and trigger instant server-side validation against the YAML schema with real-time pass/fail feedback and validated data inspection!

5. **WYSIWYG Inspector Validation Controls**:
   - When a `<form>` is selected, the inspector provides a **Form YAML Validation Binder** dropdown to switch schemas on the fly.
   - When an `<input>`, `<select>`, or `<textarea>` is selected, the inspector provides a **Field Validation Rule** dropdown with all registered custom rules (e.g. `pan`, `aadhaar`, `admission_no`) and an instant **`+ Attach`** button to append rules to the field.

---

## 13. Built-in Database Validation: `unique` & `exists`

SPP includes native database validation rules with automatic identifier escaping via `SchemaValidator`:

- **`unique:table,column[,excludeId]`**:
  Ensures value does not exist in table. Excludes `excludeId` if editing an existing entity.
- **`exists:table,column`**:
  Ensures foreign reference or key exists in the referenced table.

```yaml
fields:
  email:
    type: email
    rules: "required|email|unique:students,email"
  department_id:
    type: select
    rules: "required|exists:departments,id"
```

In controllers:
```php
$validation = $this->validate([
    'email' => 'required|email|unique:students,email,' . $student->id,
    'department_id' => 'required|exists:departments,id'
]);
```

---

## 14. 1-Click Form Synthesis from `SPPEntity` & Tables

Eliminate manual YAML authoring by generating complete schemas directly from domain models:

```bash
# Generate declarative YAML form from entity in 1 command:
php spp.php make:form StudentEnrollment --entity=Student --app=school1
```

Programmatic generation via PHP:
```php
$schema = \SPPMod\SPPView\Forms::generateSchemaFromEntity('Student', 'school1');
```

Visual synthesis in Drishyam Studio:
Click **`⚡ From Entity`** in `#forms` to select a model and generate an editable manifest instantly.

---

## 15. Debounced Client-Server Remote Validation

Fields with `unique:`, `exists:`, or `data-spp-remote="true"` automatically trigger debounced background checks in `sppvalidations.js`:

- Triggers a 400ms debounced asynchronous call to `live_validate_field`.
- Validates the field in background against the schema.
- Displays inline `.is-invalid` visual feedback and error messaging before the user submits the form.

---

## 16. Blade `@error` and `@old` Directives

Author custom Blade forms with zero boilerplate error feedback and input preservation:

```blade
<div class="mb-3">
    <label for="email" class="form-label">Email address</label>
    <input type="email" name="email" id="email" 
           value="@old('email', $student->email ?? '')" 
           class="form-control @error('email') is-invalid @enderror">
    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
```

- `@error('field')` / `@spperror`: Automatically checks `$errors`, `$_SESSION['_spp_errors']`, and `SPP_ViewErrors`, setting `$message`.
- `@old('field', $default)` / `@sppold`: Fetches flashed input from `ViewController::getOldInput()`.

---

[**Next: The Event System**](07_events.md)
