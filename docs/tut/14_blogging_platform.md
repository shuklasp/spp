# 14. Project: Blogging Platform (Dual Paradigm)

In this chapter, we will build a simple, fully functional blogging platform using two different frontend paradigms supported by the SPP Framework: the native **SPPView Paradigm** (AST Pre-Compiled Components) and the modern **Drishyam Paradigm** (Blade/Twig).

This tutorial assumes you have already set up a database and have basic familiarity with `sppdb` and `spprouter`.

---

## The Database Schema

First, let's create a migration for our `posts` table.
Run the command:
`php spp.php make:migration create_posts_table`

In the generated migration file, define the schema:

```php
public function up()
{
    $db = new \SPPMod\SPPDB\SPPDB();
    $db->exec_squery('CREATE TABLE %tab% (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )', 'posts');
}
```

Run the migration: `php spp.php migrate`.

---

## Part 1: The SPPView Paradigm

The **SPPView Paradigm** uses native HTML files enhanced with pre-compiled XML-style tags (`<php-comp>`, `<php-include>`). It compiles into pure PHP at runtime, resulting in raw execution speed with zero external dependencies.

### 1. Creating the Controller

Let's build an attribute-routed controller to handle fetching and saving posts. Create `App\Controllers\BlogController`.

```php
namespace App\Controllers;

use SPPMod\SPPDB\SPPDB;

class BlogController extends \SPP\SPPObject
{
    #[Route(path: '/blog', method: 'GET')]
    public function index()
    {
        $db = new SPPDB();
        $posts = $db->querySQL("SELECT * FROM " . SPPDB::sppTable('posts') . " ORDER BY created_at DESC");
        
        // Render the view directly using SPPView rendering
        \SPPMod\SPPView\ViewPage::showPage('blog/index.html', ['posts' => $posts]);
    }

    #[Route(path: '/blog/create', method: 'POST')]
    public function store()
    {
        $db = new SPPDB();
        $db->insertArray('posts', [
            'title' => $_POST['title'],
            'content' => $_POST['content']
        ]);
        
        header("Location: /blog");
        exit;
    }
}
```

### 2. Creating the Component

In SPPView, you can encapsulate logic into `ViewComponent` classes. Let's create a `PostCard` component.

**`App/Components/PostCard.php`**:
```php
namespace App\Components;

class PostCard extends \SPPMod\SPPView\ViewComponent
{
    public $title;
    public $content;
    public $date;

    public function render(): string
    {
        // Notice we are returning raw HTML with embedded PHP interpolation.
        return <<<HTML
        <div class="post-card">
            <h2>{$this->title}</h2>
            <p>{$this->content}</p>
            <small>Posted on: {$this->date}</small>
        </div>
        HTML;
    }
}
```

### 3. Creating the View

Create `resources/views/blog/index.html`. In the SPPView paradigm, we write native HTML and invoke our component using `<php-comp>`.

```html
<!DOCTYPE html>
<html>
<head><title>My SPPView Blog</title></head>
<body>
    <h1>Latest Posts</h1>
    
    <form action="/blog/create" method="POST">
        <input type="text" name="title" placeholder="Title" required>
        <textarea name="content" placeholder="Content..." required></textarea>
        <button type="submit">Publish</button>
    </form>

    <hr>

    <div class="posts-list">
        <?php foreach ($posts as $post): ?>
            <!-- SPPView AST Compiler translates this into native PHP at runtime -->
            <php-comp name="\App\Components\PostCard" 
                      title="<?php echo $post['title']; ?>" 
                      content="<?php echo $post['content']; ?>"
                      date="<?php echo $post['created_at']; ?>">
            </php-comp>
        <?php endforeach; ?>
    </div>
</body>
</html>
```

That's it! When you visit `/blog`, the `ViewCompiler` translates `<php-comp>` into highly optimized native PHP.

---

## Part 2: The Drishyam Paradigm

The **Drishyam Paradigm** leverages modern, industry-standard templating engines like **Blade** or **Twig**. It provides powerful directives (like `@foreach` and `@extends`) and integrates seamlessly with SPP.

To use this, ensure the `drishyam` module is enabled and your template engine (e.g., Blade) is active.

### 1. The Controller remains exactly the same!

The beauty of the SPP Paradigm Router is that `ViewPage::showPage('blog/index.html', $data)` intelligently looks for `blog/index.blade.php` or `blog/index.twig` before falling back to native HTML!

### 2. Creating the Blade Layout

**`resources/views/layouts/app.blade.php`**:
```html
<!DOCTYPE html>
<html>
<head><title>@yield('title', 'My Drishyam Blog')</title></head>
<body>
    <header>
        <h1>My Awesome Blog</h1>
    </header>

    <main>
        @yield('content')
    </main>
</body>
</html>
```

### 3. Creating the Blade View

Instead of `<php-comp>`, we will use Blade's native component syntax or simply write clean directives.

**`resources/views/blog/index.blade.php`**:
```html
@extends('layouts.app')

@section('content')
    <h2>Create a Post</h2>
    <form action="/blog/create" method="POST">
        <!-- Drishyam integrates SPP Security natively! -->
        @csrf 
        <input type="text" name="title" placeholder="Title" required>
        <textarea name="content" placeholder="Content..." required></textarea>
        <button type="submit">Publish</button>
    </form>

    <hr>

    <div class="posts-list">
        @foreach ($posts as $post)
            <div class="post-card">
                <h2>{{ $post['title'] }}</h2>
                <p>{{ $post['content'] }}</p>
                <small>Posted on: {{ $post['created_at'] }}</small>
            </div>
        @endforeach
    </div>
@endsection
```

---

## Part 3: The SPPUX Paradigm (Islands Architecture)

What if you want rich interactivity on the client side (like a React or Vue component), but you want to keep the initial page load incredibly fast using Server-Side Rendering (SSR)? Enter the **SPPUX Paradigm**.

SPPUX provides an Islands Architecture out of the box, allowing you to mount isolated Javascript UI components directly from PHP!

### 1. The Javascript Component

Create a native Javascript module at `src/App/comp/PostCard.js`:

```javascript
// SPPUX UI Components are standard ES Modules that export an init() function
export async function init(el, props) {
    // el is the native DOM element
    // props are the JSON serialized variables passed from PHP

    el.innerHTML = `
        <div class="post-card ux-island">
            <h2>${props.title}</h2>
            <p>${props.content}</p>
            <small>Posted on: ${props.date}</small>
            <button class="like-btn">❤️ Like</button>
        </div>
    `;

    // Add pure frontend interactivity
    el.querySelector('.like-btn').addEventListener('click', () => {
        alert(`You liked: ${props.title}`);
    });
}
```

### 2. Rendering the Island in PHP

Back in your Blade template or standard ViewPage, you can invoke the Island using the `SPPUX` helper class.

**`resources/views/blog/index.blade.php`**:
```html
@extends('layouts.app')

@section('content')
    <!-- Remember to boot the SPPUX Javascript runtime! -->
    {!! \SPPMod\Drishyam\SPPUX::boot() !!}

    <h2>Latest Posts (UX Islands)</h2>
    <div class="posts-list">
        @foreach ($posts as $post)
            <!-- The Javascript will only load when the element is visible! -->
            {!! \SPPMod\Drishyam\SPPUX::component('PostCard', [
                'title' => $post['title'],
                'content' => $post['content'],
                'date' => $post['created_at'],
                '__island' => 'visible' // Partial Hydration!
            ]) !!}
        @endforeach
    </div>
@endsection
```

### Conclusion

- **SPPView** is perfect for projects where you want absolute maximum performance, native PHP strictness, and zero external templating libraries.
- **Drishyam** is perfect for developer experience (DX), enabling powerful template inheritance and familiar syntax.
- **SPPUX** is perfect for Islands Architecture, hydrating rich, interactive Javascript components only when they enter the viewport!

Because the Framework operates a Multi-Engine Router, you can safely mix all three paradigms in the exact same application!
