# 14. Project: Building a Blogging Platform

This chapter is a masterclass in the SPP Framework. We are going to build a complete blogging platform from scratch. 

However, we won't just tell you *what* to type. For every step of the development process, we will take you on a journey **from basic to advanced**:
1. **The Basic Concept**: An easy-to-understand analogy of what we are doing.
2. **The Advanced Architecture**: A deep dive into the underlying framework code, lifecycles, and pipelines.
3. **The Implementation**: The actual code to build our blog.

By the end, you will have a fully functional app and a masterful understanding of the SPP architecture.

---

## Step 1: Scaffolding the App

Before we write code, we need to generate the skeleton of our application.

### 🟢 The Basic Concept (The Construction Crew)
Imagine you want to build a restaurant. You don't build the bricks yourself; you hire a construction crew. In SPP, the **CLI (Command Line Interface)** is your construction crew. You tell it what you want, and it generates the boilerplate files for you, preventing human error. Even better, if you forget to tell the crew a detail, they will politely ask you for it!

### 🔴 The Advanced Architecture (The CLI Kernel & Interactive Console)
When you run `php spp/spp.php make:app`, the SPP Console Kernel boots up. It maps your input string to a specific command class (e.g., `MakeAppCommand.php`). 
If you omit required arguments (like the paradigm), the `InteractiveConsole` trait intercepts the execution. It renders beautiful interactive prompts (text inputs, multiple-choice menus, or hidden password fields) to collect the missing data before proceeding.
Once it has all the data, the CLI registers the new app in the global namespace, scaffolds the standard directory tree (`src/blog/Controllers`, `src/blog/Entities`, etc.), and wires up the configuration files.

### 🛠️ The Implementation
Open your terminal and run:

```bash
# Set up the core framework tables (users, roles, rights) in the XDB
php spp/spp.php admin:bootstrap
```

Now, scaffold the app. You can do this **interactively**. Just run the command without any flags:
```bash
php spp/spp.php make:app blog
```
The CLI will detect that missing information is needed and prompt you interactively:
```text
> Which view paradigm would you like to use?
  [0] sppview (Native PHP)
  [1] blade (Drishyam)
> 1
```

*(Alternatively, you can bypass the interactive prompts by passing flags directly: `php spp/spp.php make:app blog --paradigm=blade`)*

---

## Step 2: Data Modeling

Our blog needs to store "Posts" in a database.

### 🟢 The Basic Concept (The Kitchen & Recipes)
In the old days, developers wrote raw database queries (SQL) everywhere. SPP uses an **ORM** (Object-Relational Mapping). Think of it as a translator between your PHP code and the database. You define a "Recipe" (an Entity), and you can just say `$post->title = "Hello"`. The framework handles all the complicated database translation behind the scenes.

### 🔴 The Advanced Architecture (`SPPEntity` & `SPPDB`)
`SPPEntity` is the core of the ORM.
1. **Configuration Resolution**: When you instantiate `new Post()`, SPP searches for `post.yml` in `APP_ETC_DIR/{app}/entities`. The parsed YAML (table name, fields, relations) is cached in a static registry `$_metadata` for ultra-fast subsequent lookups.
2. **Magic Methods**: Property access is intercepted by `__get()` and `__set()`. If you request a related entity (e.g., `$post->author`), the magic `__get` delegates to `SPPEntityRelations::getRelated()`, executing a lazy-loaded SQL join.
3. **Lifecycle Hooks**: Entities fire hooks. For example, the `after_save()` hook automatically contacts `SPPCacheManager` to invalidate cache tags like `Post:{id}`, ensuring your app never serves stale data.
4. **SPPDB**: The underlying abstraction layer. It uses `sppTable()` to resolve physical table names (handling dynamic prefixes) and strictly uses prepared statements via `execute_query()` to prevent SQL injection.

### 🛠️ The Implementation
Let's define the `Post` entity. Just like before, we will use the interactive CLI. Run:

```bash
php spp/spp.php make:entity Post
```

The interactive console will ask you a few questions. Notice how it loops to let you add multiple attributes and relationships without writing any code:
```text
> Application/Context [default]: blog
> Database Table [posts]: blog_posts
> Extends (Parent Entity class, optional): 
> Enable Login Support? (y/n) [n]: n

Entity Attributes (Press Enter on empty Name to finish):
  Attribute Name: title
  Type [varchar(255)]: varchar(255)
  Attribute Name: content
  Type [varchar(255)]: longtext
  Attribute Name: status
  Type [varchar(255)]: varchar(20)
  Attribute Name: 

Entity Relationships (Press Enter on empty Target to finish):
  Target Entity: \SPPMod\SPPAuth\SPPUser
  Relation Type [OneToMany]: ManyToOne
  Foreign Key Field [post_id]: author_id
  Target Entity: 

Success: Entity Post saved and scaffolded in blog context.
```

This generates a complete YAML file at `src/blog/etc/entities/post.yml` and perfectly wires up the object-relational mapping without you having to write the file by hand!

*(Bonus: Need to change the database schema later? Don't edit the YAML manually! Run `php spp/spp.php ent:edit Post` to launch a powerful menu-driven wizard where you can safely (A)dd, (E)dit, or (R)emove fields and relationships on the fly.)*

Now, tell SPPDB to actually create this table in MySQL:
```bash
php spp/spp.php migrate --app=blog
```

---

## Step 3: Identity & Security

A blogging platform has different types of users: Readers, Authors, and Editors.

### 🟢 The Basic Concept (The Bouncer & VIP Keys)
HTTP is "stateless"—it has the memory of a goldfish. Every time you click a link, the server forgets who you are. When you log in, SPP gives your browser a "VIP Wristband" (a Session).
Then, we use **RBAC (Role-Based Access Control)**. We create specific "Keys" (Permissions like `publish_post`), group them into a keychain called a "Job Title" (Roles like `Editor`), and hand that keychain to an employee (a User). The Bouncer checks your keys before letting you act.

### 🔴 The Advanced Architecture (`SPPAuth` & PolicyRegistry)
SPP Auth abstracts identity via the Guard Pattern (`WebGuard` for sessions).
1. **The Login Pipeline**: When a user logs in, the request hits a `RateLimiter`. Then, it verifies the password. Next, it checks if an `MFA_REQUIRED` exception should be thrown. Finally, `WebGuard` establishes the session, storing a hashed fingerprint of the user's IP Address and User-Agent to aggressively prevent session hijacking.
2. **The 5-Layer RBAC Cascade**: When you call `SPPAuth::can('blog.post.publish')`, `WebGuard` checks:
   - *Anonymous Group* (universal public permissions)
   - *Authenticated Group* (universal logged-in permissions)
   - *Legacy Rights* (via the `roleright` and `userroles` pivot tables)
   - *Registry Overrides* (environmental permissions)
   - *Polymorphic Groups* (via `SPPGroupLoader`)
   The result is heavily cached in the session, invalidated instantly if `rights_updated_at` changes in the database.
3. **ABAC (Attribute-Based Access Control)**: For complex rules ("Authors can only edit their *own* posts"), `PolicyRegistry::evaluate()` parses a recursive JSON logic tree from the database, evaluating fields like `user.id == context.author_id`.

### 🛠️ The Implementation
Let's create our permissions using a Seeder:

```bash
php spp/spp.php make:seeder BlogPermissions --app=blog
```

Open `src/blog/Seeders/BlogPermissions.php`:

```php
namespace App\Blog\Seeders;
use SPPMod\SPPDB\SPPDB;
use SPPMod\SPPAuth\SPPRole;
use SPPMod\SPPAuth\SPPRight;

class BlogPermissions {
    public function run() {
        $db = new SPPDB();
        $permissions = ['blog.post.create', 'blog.post.edit', 'blog.post.publish'];
        $ids = [];
        
        // 1. Create Rights (Keys)
        foreach ($permissions as $name) {
            if (!SPPRight::rightExists($name)) {
                $db->insertValues('rights', ['name' => $name]);
            }
            $ids[$name] = SPPRight::getRightId($name);
        }

        // 2. Create Roles (Keychains)
        SPPRole::saveRoleInfo([
            'role_name'   => 'Blog Editor',
            'right_ids'   => [$ids['blog.post.create'], $ids['blog.post.edit'], $ids['blog.post.publish']],
        ]);

        SPPRole::saveRoleInfo([
            'role_name'   => 'Blog Author',
            'right_ids'   => [$ids['blog.post.create'], $ids['blog.post.edit']],
        ]);
    }
}
```

Run the seeder:
```bash
php spp/spp.php seed BlogPermissions --app=blog
```

To create our ABAC rule (Authors can only edit their *own* posts), we use the CLI:
```bash
php spp/spp.php iam:abac create "blog.post.edit_own" \
  '{"field": "user.id", "operator": "equals", "value": "context.author_id"}'
```

---

## Step 4: The Brain (Routing & Controllers)

The Controller is the core of our application logic.

### 🟢 The Basic Concept (The Waiter)
When a customer orders food, the Host (The Router) looks at the order and assigns it to a Waiter (The Controller). The Waiter asks the Kitchen (Database) for the data, and then hands it to the Presentation team (The View).

### 🔴 The Advanced Architecture (Multi-Engine Routing)
During application boot, the `RouteScanner` uses PHP Reflection to parse all controller files. It extracts the `#[Route]` attributes and caches them in an optimized routing table.
SPP uses an "Onion" Middleware pipeline via `array_reduce`. Requests enter global middleware (like CSRF protection), then route-specific middleware, before finally hitting the Controller method.

### 🛠️ The Implementation
```bash
php spp/spp.php make:controller BlogController --app=blog
```

Open `src/blog/Controllers/BlogController.php` and write the complete logic:

```php
namespace App\Blog\Controllers;

use App\Blog\Entities\Post;
use SPPMod\SPPAuth\SPPAuth;
use SPPMod\SPPView\Attributes\Route;
use SPPMod\SPPView\ViewPage;

class BlogController extends \SPP\SPPObject
{
    // 📰 HOME — Public
    #[Route(path: '/blog', method: 'GET')]
    public function index()
    {
        // Using the modern Query Builder
        $posts = Post::query()->where('status', 'published')->orderBy('published_at', 'desc')->get();
        ViewPage::showPage('blog/index.blade.php', ['posts' => $posts]);
    }

    // ✍️ CREATE — RBAC Guarded
    #[Route(path: '/blog/create', method: 'GET')]
    public function create()
    {
        if (!SPPAuth::check()) { header('Location: /login'); exit; }
        if (!SPPAuth::can('blog.post.create')) { http_response_code(403); exit; }

        ViewPage::showPage('blog/editor.blade.php', ['post' => null]);
    }

    // 💾 SAVE — Handles both new posts and edits
    #[Route(path: '/blog/save', method: 'POST')]
    public function save()
    {
        if (!SPPAuth::can('blog.post.create')) { http_response_code(403); exit; }
        
        $postId = $_POST['post_id'] ?? null;
        if ($postId) {
            $post = Post::find_one(['id' => $postId]);
            
            // 🛡️ Evaluate our ABAC rule (Can THIS user edit THIS post?)
            if (!SPPAuth::can('blog.post.edit_own', $post) && !SPPAuth::can('blog.post.edit_any')) {
                http_response_code(403); exit;
            }
            
            $post->title = $_POST['title'];
            $post->content = $_POST['content'];
            $post->save();
        } else {
            $post = new Post();
            $post->title = $_POST['title'];
            $post->content = $_POST['content'];
            $post->status = 'draft';
            $post->author_id = SPPAuth::user()->id;
            $post->save();
        }
        
        header("Location: /blog/drafts"); exit;
    }

    // 🚀 PUBLISH — Strict RBAC Guard
    #[Route(path: '/blog/publish/{id}', method: 'POST')]
    public function publish($id)
    {
        // Only Editors have this permission
        if (!SPPAuth::can('blog.post.publish')) { http_response_code(403); exit; }
        
        $post = Post::find_one(['id' => $id]);
        $post->status = 'published';
        $post->published_at = date('Y-m-d H:i:s');
        $post->save();
        
        header("Location: /blog"); exit;
    }
}
```

---

## Step 5: The Presentation & The Magic Editor

Finally, we need to show the data to the user using views, and embed our rich-text editor.

### 🟢 The Basic Concept (Plating & Magic Islands)
Writing PHP logic inside HTML is messy, so we use a Templating Engine (Blade) to safely inject data into our HTML. 
For the editor, traditional websites had to load massive Javascript files that slowed down the whole page. SPP uses **Islands Architecture**. It sends the HTML instantly, and then "wakes up" just the tiny isolated island where the editor lives. 

### 🔴 The Advanced Architecture (Dual Paradigm & SPPUX)
1. **The View System**: SPP supports both `SPPView` (an AST compiler for maximum native performance) and `Drishyam` (a Blade-compatible engine that parses directives like `@foreach` using Regex and caches the resulting PHP in `tmp/views/`).
2. **SPPUX**: Components extend `BaseComponent` and strictly follow a lifecycle: `onInit()` &rarr; `onMount()` &rarr; `update()` &rarr; `onDestroy()`.
3. **Lekhni Internals**: The WYSIWYG editor module is a masterpiece of SPPUX. It features a dual engine (`contenteditable` for document mode, Monaco IDE for code mode). It uses the browser's `IndexedDB` (`LekhniEnterpriseStore`) to silently auto-save drafts offline, completely bypassing the network.

### 🛠️ The Implementation
First, enable the built-in editor module:
```bash
php spp/spp.php module:enable lekhni
```

*(Note: While we are using the pre-built Lekhni component, you can easily scaffold your own custom SPPUX components interactively by running `php spp/spp.php make:component MyCustomButton`. The CLI will prompt you for the app name and whether to include a CSS file, before generating the boilerplate in `src/blog/ux/`.)*

Create `src/blog/resources/views/blog/index.blade.php`:

```html
<!DOCTYPE html>
<html>
<body>
    <h1>My Blog</h1>
    
    <!-- Only show the Create button if the user has the RBAC key -->
    @if(\SPPMod\SPPAuth\SPPAuth::can('blog.post.create'))
        <a href="/blog/create">Write a Post</a>
    @endif

    @foreach ($posts as $post)
        <article>
            <h2>{{ $post->title }}</h2>
            <!-- Note: the magical __get lazy loads the author! -->
            <p>By {{ $post->author->username }}</p>
            <div>{!! $post->content !!}</div>
        </article>
    @endforeach
</body>
</html>
```

Create the editor page at `src/blog/resources/views/blog/editor.blade.php`. This is where we embed the SPPUX Island:

```html
<!DOCTYPE html>
<html>
<body>
    <h1>Write Post</h1>

    <form action="/blog/save" method="POST" id="blog-form">
        <input type="text" name="title" placeholder="Title">
        <input type="hidden" name="content" id="hidden-content">

        <!-- Boot the SPPUX engine -->
        {!! \SPPMod\Drishyam\SPPUX::boot() !!}
        
        <!-- Mount the Lekhni Island -->
        {!! \SPPMod\Drishyam\SPPUX::component('LekhniEditor', [
            'mode' => 'document',
            'embedded' => true
        ], 'lekhni') !!}

        <button type="submit">Save</button>
    </form>

    <script>
        // Copy the editor's rich HTML into the hidden field on submit
        document.getElementById('blog-form').addEventListener('submit', function() {
            var editorHtml = document.querySelector('.lekhni-body-editable').innerHTML;
            document.getElementById('hidden-content').value = editorHtml;
        });
    </script>
</body>
</html>
```

---

## Conclusion
You have just built a complete, highly-secure application. 
By understanding the architectural phenomena—the ORM metadata registries, the RouteScanner, the 5-layer WebGuard cascade, and SPPUX Islands—you are now equipped to build any application on the SPP Framework.

[**Previous: Security Hardening**](13_security_hardening.md) | [**Next: Middleware Pipeline**](15_middleware.md)
