# SPP Framework: Fresher's Guide

Welcome to your first day as an SPP Framework developer! This guide is written for junior developers (freshers) who are completely new to enterprise framework architecture. 

We will break everything down so it makes absolute sense.

---

## 1. What exactly *is* SPP?

SPP is an **Enterprise Web Framework**. 
Think of it like a massive kitchen. If you were building a website from scratch using plain PHP, you would have to build the oven, the sink, and the fridge yourself. SPP gives you the fully-equipped kitchen. All you have to do is write the "recipes" (your business logic).

But SPP is a special kind of kitchen—it's a **Polyglot Monolith**.
* **Monolith**: It has everything built-in. It manages the database, the URL routing, the user interface forms, and the user authentication all by itself.
* **Polyglot**: It speaks multiple languages! Most PHP frameworks only run PHP. SPP has a special "Bridge" that lets it talk to Python, Java, or C++ instantly.

## 2. The Core Folders You Need to Know

When you open the SPP codebase, you will see a lot of folders. Do not panic! You only need to care about a few of them at first:

* `src/` (Source) - This is where **YOUR** code goes. If you are building a school app, it goes in `src/school/`.
* `spp/` - This is the **Core Framework Engine**. Never edit files in here unless you are modifying the framework itself!
* `docs/` or `documentation/` - The manuals. Read them!
* `var/` - Temporary files, cache, and logs. SPP uses this to store things quickly in the background.

## 3. How does a Web Page actually load in SPP?

Let's say a user goes to `www.yourapp.com/login`. What happens?

1. **The Request**: The user's browser sends a request to the server.
2. **The Router**: SPP's internal Router sees `/login` and says, *"Aha! I know where to send this."*
3. **The Controller/Service**: The router points to a PHP function you wrote. This function might check if the user is already logged in, or ask the database for data.
4. **The View (Drishyam)**: Your PHP function passes the data to a View (an HTML template). SPP renders the beautiful HTML and sends it back to the user's browser.

## 4. XDB: SPP's Database Magic

In SPP, you **rarely write raw SQL queries** (like `SELECT * FROM users`). 
Instead, SPP uses **XDB** (XML Database). 

You write a simple XML file defining what a "User" looks like (Name, Email, Age). SPP reads that XML file and automatically builds the SQL tables and columns for you! When you want to fetch a user, you just use simple PHP objects, and SPP handles the database talk behind the scenes.

## 5. The CLI (Command Line Interface)

As a fresher, the command line is your best friend. SPP comes with a magical tool called `spp.php`.
Open your terminal and type:
```bash
php spp.php list
```
This will show you all the commands you can run. 
Need to create a new module? You don't have to code it by hand. Just run:
```bash
php spp.php make-module "MyNewModule"
```
And SPP will create all the folders and starter files for you!

## Next Steps
Now that you know the basics, try making your first App! Ask your senior for the "Rosetta Stone" guide or use the `php spp.php ask` command if you ever get stuck!
