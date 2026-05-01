# Core Modules: Identity & Profiles (Groups, Profiles, UserProfiles)

These modules work together to manage the complex relationships between users, their personal data, and their organizational groupings.

---

## 1. Basic Philosophy
The identity system follows a **"De-Normalized Identity"** model. It separates a user's core authentication data (managed by SppAuth) from their social profile, professional metadata, and group memberships.

---

## 2. Architecture & Modules

### SppGroup (`\SPP\Group`)
Manages hierarchical and flat groups (e.g., Departments, Teams, Roles). Supports nested groups and automated membership inheritance.

### SppProfile (`\SPP\Profile`)
A generic metadata engine used to attach specialized data to any entity (not just users).

### SppUserProfile (`\SPP\UserProfile`)
A specialized implementation of SppProfile focused specifically on user-related data like avatars, biographies, and social links.

---

## 3. API & Usage

### Working with Groups
```php
$group = \SPP\Group::findByName('Administrators');
$user->addToGroup($group);

if ($user->isInGroup('Moderators')) {
    // Perform restricted action
}
```

### Accessing User Profile Data
```php
$profile = $user->getProfile();
echo $profile->get('biography');
```

---

## 4. Integration
These modules are tightly integrated with the `SppEntity` ORM. Every user, group, and profile is a framework entity, allowing for complex queries like "Find all users in Group X who have updated their profile in the last 24 hours."

---
[Back to Modules Index](index.md)
