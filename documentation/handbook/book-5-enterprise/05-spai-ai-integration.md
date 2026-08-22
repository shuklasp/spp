# Book 5 Chapter 5 — SPPAI and AI Integration

## 1. Why AI belongs at an application boundary

AI calls are external, variable, and potentially expensive operations. They should not be scattered through controllers.

A useful boundary is:

```text
Application service
      ↓
AI integration boundary
      ↓
provider/driver
      ↓
model/service
```

## 2. Framework role

SPPAI provides framework-level integration concepts. The application should own business intent; the AI integration layer should own provider-specific details.

## 3. Lab

Add a classification feature to Task Desk that asks an AI service to suggest a priority, then require a normal server-side validation/authorization path before storing the result.

## 4. Failure lab

Test provider failure, malformed output, timeout, and unavailable credentials.

Do not describe automatic recovery or model quality guarantees unless the implementation proves them.

## Checkpoint

> **AI is an external capability behind an application boundary, not a substitute for business validation or authorization.**
