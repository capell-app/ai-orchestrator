---
name: capell-assistant-development
description: Use when editing Capell Assistant modules, providers, capabilities, or orchestration.
---

# Capell Assistant

Assistant module registry, provider contracts, capability execution, and Mosaic planning integration.

## Look

- `packages/assistant/src`
- `packages/assistant/docs`
- `packages/assistant/README.md`

## Rules

- Keep provider connectors behind contracts.
- Capability execution belongs in Actions, not UI glue.
- Assistant modules should expose previewable, bounded operations.
- Run `vendor/bin/pest packages/assistant/tests`.
