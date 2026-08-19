# Volume IV — Module Architecture

## Chapter 5 — Module Discovery, Manifests, and Compiled Registry

**Evidence:** `spp/core/class.module.php`, `spp/core/class.modulecompiler.php`, `spp/core/class.moduleinstaller.php`, `spp/etc/modules.yml`, `spp/etc/modules.xml`, application `modsconf` files, first-party `module.yml` files.

SPP's module system is a runtime composition system backed by module manifests and application-specific module registries. The source supports YAML and XML representations and compiles discovered module metadata into an application-scoped PHP cache.

## 5.1 Module manifest versus activation registry

There are two different concepts:

- **Module manifest:** lives with a module and describes that module, for example `spp/modules/spp/sppview/module.yml`.
- **Module registry/activation configuration:** tells a particular application which modules are active, including files under `etc/apps/*/modsconf/` and the global `etc/modules.*` registries.

Do not document these as the same file or responsibility.

## 5.2 A real manifest

The `spphtml` / SPP View module manifest contains fields including:

- module name and version;
- public name, group, category, and description;
- `includes` — framework files the module contributes;
- `deps` — module dependencies;
- configuration variables; and
- per-variable settings metadata such as type, label, options, and default.

This is different from a generic package-manager manifest. The manifest participates directly in SPP runtime loading and configuration.

## 5.3 Active-module discovery

The module compiler obtains registry files from `Module::getRegistryFiles($appContext)`. It reads XML or YAML activation registries and selects modules whose status is `active` or which are compulsory according to the module subsystem.

The compiler also discovers application-specific modules under directories returned by `Module::getAppModuleDirs($appContext)`. A user module can therefore be discovered from its local `module.yml` without first being inserted into a central registry file, subject to the surrounding module rules.

```text
Application context
       │
       ├── registry files
       │      ├── XML
       │      └── YAML
       │
       └── application module directories
              └── module.yml / module.yaml
                       │
                       ▼
                 active module map
```

## 5.4 Dependency resolution

`ModuleCompiler::topologicalSort()` performs a depth-first dependency traversal. Dependencies are read from the module manifest (`deps` or `dependencies`).

```text
module A
  └── depends on B
          └── depends on C
                  └── depends on D

Load order: D → C → B → A
```

The algorithm uses a temporary-set cycle guard. A module encountered while already in the temporary set produces a circular-dependency exception. A referenced dependency that is absent from the discovered module map produces `MissingDependencyException`.

## 5.5 Compiled registry cache

The module compiler writes an application-specific PHP cache named in the form:

```text
var/cache/modules_<appContext>.php
```

The cache contains normalized module metadata, including values such as:

- internal module name;
- module path;
- module type;
- version;
- dependencies;
- included files;
- services extracted from the module settings; and
- resolved configuration values.

The compiler also stores metadata about the manifests used to build the cache.

## 5.6 Why compilation exists

The compiled registry avoids reparsing every manifest and rebuilding dependency metadata on every runtime path. It also gives management commands a single normalized representation of active module state.

This is an **implemented cache layer**, not an assumption about how SPP might work.

## 5.7 Module configuration

The compiler resolves module configuration variables from the manifest and current module configuration through `Module::getConfig()`. This means a module's `config_variables` are not simply documentation—they participate in the configuration model used to materialize the compiled registry.

## 5.8 Installation and management

The repository contains dedicated commands for:

- module list;
- enable/disable;
- install/uninstall;
- update;
- module settings; and
- module tests.

There is also `ModuleInstaller`, so module installation is a first-class framework operation rather than a documentation convention.

## 5.9 YAML and XML compatibility

The repository contains both modern YAML module registries and legacy XML registries. The module compiler explicitly understands both formats when reading active module lists.

The handbook therefore documents **YAML-first project conventions where they are actually used**, while retaining the XML path as a supported compatibility mechanism.

## 5.10 Dependency graph illustration

```text
                 ┌──────────────┐
                 │     core     │
                 └──────┬───────┘
                        │
              ┌─────────┴─────────┐
              ▼                   ▼
        ┌──────────┐        ┌──────────┐
        │  dbconfig │        │   user   │
        └────┬─────┘        └────┬─────┘
             │                    │
             └─────────┬──────────┘
                       ▼
                ┌────────────┐
                │  sppview   │
                └────────────┘
```

The exact graph varies with application/module configuration; the diagram is illustrative of the dependency mechanism, not a claim about every SPP installation.

## 5.11 Architectural distinction

An SPP module is neither just a Composer package nor simply an application. It is a framework-recognized feature unit with a manifest, runtime metadata, optional code/asset contributions, configuration, and dependency relationships.

The module compiler gives that unit a runtime identity and a load order inside an application context.

## Kernel Hacker note

The topological sort is straightforward DFS with `visited` and `temp` sets, so its graph traversal is linear in the number of discovered modules and dependency edges, assuming normal map lookups. The more interesting optimization is the **compiled registry cache**, which moves expensive discovery and normalization work out of the steady-state runtime.
