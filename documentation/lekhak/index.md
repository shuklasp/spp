# Lekhak CMS: Technical Wiki

Welcome to the internal technical documentation for Lekhak CMS. Lekhak is designed as a portable, self-contained application architecture.

## 🏗️ [Core Architecture](index.md)
*   [The Self-Contained App Model](index.md#self-contained-architecture)
*   [Portability Principles](index.md#portability)

## 🧩 Modules & Systems
*   [**Lekhni Editor**](lekhni-editor.md): The professional WYSIWYG publishing engine.
*   [**Drishyam Theme Engine**](theme-engine.md): The advanced CMS-style layout and region system.
*   [**Mobile Studio Pro**](../mobile_studio_pro.md): The elite visual IDE for high-fidelity Flutter development.
*   [**Media & Data**](media-management.md): Path resolution, local storage, and the media API.
*   [**Registry & Configuration**](application-registry.md): Application-local settings and routing.

---

## Architecture Overview

### Self-Contained Architecture
Lekhak is 100% encapsulated. Everything needed to run the CMS (logic, assets, data) is located within the `src/lekhak/` directory.

### Portability
Because Lekhak resolves its own paths relative to its source directory, it can be "dropped in" to any project running the SPP core. It does not require global configuration changes or centralized database registrations to function.
