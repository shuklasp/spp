# SPP Core Modules: Overview

The SPP Framework is powered by a suite of framework and application modules. The handbook distinguishes **implemented**, **partially implemented / source-present**, and **planned or not yet verified** features instead of treating every module name as an equal runtime guarantee.

## Core module catalogue

*   [**Full SPP API Reference**](../spp-full-api-reference.md): Runtime APIs, utility APIs, module catalog, and usage patterns across core, contrib, and app modules.
*   [**SppView**](sppview.md): Rendering, resource management, page/view infrastructure, and asset orchestration.
*   [**SppAPI**](sppapi.md): Native API infrastructure, resources/responses, routing support, documentation, and API-oriented helpers.
*   [**SppEntity**](sppentity.md): Entity and database relationship infrastructure.
*   [**SppDb**](sppdb.md): Database abstraction and query execution infrastructure.
*   [**SppAjax**](sppajax.md): Asynchronous request and JSON/API helpers.
*   [**SppLogger**](spplogger.md): Diagnostic and logging infrastructure.
*   [**SppUx / SPPUX**](sppux.md): UI/client runtime and browser-side reactive capabilities.
*   [**SppAuth**](sppauth.md): Authentication/authorization and RBAC-related infrastructure.
*   [**SppQueue**](sppqueue.md): Queue/background-job infrastructure present in the repository.
*   [**Parikshak**](parikshak.md): Framework-aware testing and system-scanning infrastructure.
*   [**Identity & Profiles**](identity-profiles.md): Group/profile/user identity metadata.
*   [**State & Config**](state-config.md): `SppConfig`, `SppSetting`, and persistent runtime settings.
*   [**Orion Cache**](orion-cache.md): Cache/registry/bootstrapping infrastructure documented in the repository.
*   [**Lekhni Editor**](lekhni.md): Contributed editor/document-workspace functionality.
*   [**SppWorkflow**](sppworkflow.md): Workflow/state-machine/approval infrastructure documented in the repository.
*   [**SppAI**]: AI integration layer with provider-driver abstractions and multiple provider implementations present in the repository. See the handbook AI branch before treating provider support as a uniform production guarantee.
*   [**SppAudit**]: Audit/revision/delta infrastructure is present in the repository. See the handbook audit/revision/content-promotion material for verified capabilities.
*   [**SppWizard**]: Wizard-related implementation exists in the workflow/tutorial surface; exact capabilities should be taken from the current source before treating the module as a universal framework contract.
*   [**SppBlade**]: Blade integration is part of the broader rendering stack; exact module boundaries and status should be read from current source rather than relying on the older "Coming Soon" label.
*   [**SppDrupal**]: A Drupal bridge/data-migration implementation may exist in contributed/application areas; treat it as an integration branch rather than assuming it is part of every core installation.
*   [**SppPwa**]: Do not treat this as universally implemented core behavior without verifying the current module/source tree.

## Status-reading rule

The presence of a class, directory, or documentation page does **not** by itself prove a production-wide guarantee. For advanced claims, the handbook uses this order of evidence:

1. executable source;
2. tests and fixtures;
3. consumed manifests/configuration;
4. repository documentation;
5. architectural interpretation.

This is especially important for distributed behavior, consensus, transaction semantics, transport guarantees, provider availability, and deployment guarantees.

## What is a core module?

A core module is a framework-level component located under `spp/modules/spp/` or otherwise explicitly integrated into the framework runtime. Application and contributed modules may use the same infrastructure without being mandatory to every SPP installation.

---

[Back to Framework Wiki](../index.md)
