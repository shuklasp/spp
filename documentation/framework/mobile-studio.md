# Mobile Studio: Visual Builder Architecture

The **Mobile Studio** is a visual development environment within the SPP Admin Panel designed for building cross-platform mobile applications (PWA, Flutter) using a reactive, drag-and-drop interface.

## Overview

Mobile Studio leverages the **SPPUX** framework to provide a real-time "What You See Is What You Get" (WYSIWYG) experience. It allows developers to define app screens, components, data bindings, and action flows without writing manual UI code.

## Core Components

### 1. Visual Designer (`mobile.js`)
The heart of the studio, built as a reactive `BaseComponent`.
- **Navigator**: Manage app screens and navigate between them.
- **Component Palette**: Drag-and-drop library of mobile-optimized components (Text, Buttons, Inputs, Switches, etc.).
- **Device Preview**: Interactive frame supporting multiple device types (iOS, Android) with real-time rendering.
- **Inspector**: Context-aware properties panel for configuring component attributes, event actions, and data bindings.

### 2. State Management
The application configuration is managed as a unified state object, which includes:
- **Screens**: Hierarchy of screens and their nested components.
- **Theme**: Primary, secondary, and surface color palettes.
- **Global State**: Application-level variables accessible across screens.
- **Data Mapping**: Integration with SPP Entities for dynamic content.

### 3. Backend Persistence (`Mobile.php`)
Configuration is persisted as a `mobile.yml` file in the application's configuration directory (`etc/apps/{appname}/mobile.yml`).
- **YAML Serialization**: Uses Symfony Yaml for high-fidelity persistence of complex nested structures.
- **Context Awareness**: Automatically switches context based on the active application being designed.

## Advanced Features

### Polyglot App Generation
Mobile Studio can trigger external builders via the **Polyglot Bridge**:
- **Flutter**: Invokes Python-based building scripts to generate native Flutter projects from the YAML schema.
- **PWA**: Synchronizes manifest and service worker assets for instant web-app deployment.

### Event Flows & Actions
Components support an "Event Flow" architecture where triggers (e.g., `onTap`, `onChange`) can be linked to actions:
- `Navigate`: Switch screens.
- `Notify`: Show toasts or alerts.
- `CallService`: Invoke a backend LiveService.
- `SetState`: Modify global app variables.

## Technical Structure

| File | Responsibility |
|------|----------------|
| `spp/sppmobile/index.php` | Main entry point and layout container. |
| `js/mobile-app.js` | IDE Core, navigation, and view switching logic. |
| `js/views/mobile.js` | Visual Studio implementation (Canvas + Inspector). |
| `css/mobile.css` | Device framing and IDE styling. |
| `services/Mobile.php` | Backend API for persistence and generation. |

## Development Modes
The studio supports three primary modes of interaction:
- **Studio (🏗️)**: The visual canvas and property inspector.
- **Assets (📁)**: Resource management for images and icons.
- **Code (💻)**: Direct YAML/JSON view of the underlying app configuration.
