# 06. Forms & Validation

SPP provides a powerful form system that handles server-side processing, automated client-side validation mapping, and data binding.

---

## The Fluent Builder API (Modern Approach)

The recommended way to build forms programmatically in SPP is using the new Fluent Builder API via `class.form.php`. This allows you to construct complex, validated forms entirely in PHP with a clean, chainable syntax.

```php
use SPPMod\SPPView\Form;

$form = Form::create('login_form', '/login')
    ->addText('username', 'Username', ['required' => true, 'min' => 3])
    ->addPassword('password', 'Password', ['required' => true])
    ->addSubmit('login_btn', 'Login')
    ->build();

echo $form->render();
```

---

## Defining Forms in YAML

You can also define forms declaratively using YAML. The `ViewFormBuilder::fromArray` method can parse these definitions into complete `ViewForm` objects.

```yaml
# forms/login.yml
name: login_form
action: /login.php
method: POST
controls:
  - name: username
    type: text
    label: Username
    required: true
    min: 3
  - name: password
    type: password
    label: Password
    required: true
  - name: login_btn
    type: submit
    value: Login
```

To render this:
```php
$config = \SPPMod\SPPView\ViewFormBuilder::loadConfig('forms/login.yml');
$form = \SPPMod\SPPView\ViewFormBuilder::fromArray($config);
echo $form->render();
```

---

## Native HTML5 Validation Mapping

When you define validation rules (whether via Fluent Builder or YAML), SPP automatically translates them into native HTML5 attributes (`required`, `minlength`, `pattern`, etc.). 

For example, assigning a rule like `aadhaar` or `pan` will natively inject the correct regex into the `<input pattern="...">` attribute. This guarantees zero-JS, instant client-side validation while still enforcing the identical rules on the backend upon submission.

For complex logic that cannot be represented via simple attributes, validators dynamically inject lightweight JS (`getClientScript()`) to automatically execute on form submission.

---

## Direct Entity Data Binding

SPP Forms provide a direct data binding layer to map forms to your database entities using `SPPEntity`. 

### Hydrating Forms (Read)
Use `bind()` to automatically populate form inputs with values from an existing database entity:

```php
$user = new UserEntity(123); // Load user
$form->bind($user); 
// Now all <input> elements will have their "value" attributes pre-filled
```

### Hydrating Entities (Write)
When a form is submitted, use `fill()` to automatically pull `$_POST` data and populate the entity:

```php
$user = clone $userEntity;
$form->fill($user); // Fills properties from $_POST
if ($form->isValid()) {
    $user->save();
}
```

---

## Server-side Processing

To handle a form submission in your PHP controller:

```php
if (\SPPMod\SPPView\ViewPage::processForms()) {
    // This is called automatically when a form is submitted
}

function login_form_submitted() {
    $username = $_POST['username'];
    $password = $_POST['password'];
    // ... authentication logic
}
```

---

[**Next: The Event System**](07_events.md)
