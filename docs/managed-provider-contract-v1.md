# Managed Provider Contract v1

Schema version: `1`

AI Orchestrator exposes a provider-neutral boundary for one authorised managed provider call. The consuming application remains the sole financial authority. It creates and owns reservations, wallets, journals, price policies, entitlements, refunds, balances, and every related mutation.

## Contract inventory

- `ProviderCallAuthorizationData` carries the opaque reservation reference, operation and stage identities, fencing token, exact provider-call idempotency key, request digest, expected validated-output identity, validator policy identity and digest, allowance, and UTC deadline.
- `ProviderCallAllowanceData` bounds request count and input/output tokens without carrying money or customer credit values.
- `ProviderIdempotencyHeaderData` derives a safe provider header identity from the caller's exact idempotency key without changing the canonical call identity.
- `ProviderExecutionResultData` returns the provider-call identity, terminal validity, retry classification, refusal/error classification, validated-output identity and digest, exact aggregate usage, replay state, and ordered attempt evidence.
- `MeasuredUsageData` carries exact provider-reported request, input-token, and output-token counts with provider, model, provider-run, and metering-source identities. Missing or estimated usage is refused.
- `ValidatedProviderOutputData` binds an output identity to content and its verified SHA-256 digest.
- `ManagedProviderExecutionReceiptData` immutably binds the exact request, validator policy, validated output, caller authorization, terminal execution, measured usage, provider-call identity, and replay state. The receipt is evidence only and cannot mutate caller-owned finance.
- `UsableVersionAttachmentData` binds the validated-output identity and digest to one immutable Website Version identity and publishing operation identity.
- `TerminalSettlementResultData` reports settled and released caller-owned amounts, terminal transition identity, replay status, and refusal/error classification. It is evidence returned by the caller's settlement adapter, not authority for AI Orchestrator to write financial state.

All canonical contracts reject unsupported schema versions and provide stable canonical JSON and SHA-256 identities. Existing v1 execution payloads without attempt evidence remain readable.

## Admission and execution sequence

1. The consuming application authorises a call after its own admission and reservation checks.
2. AI Orchestrator validates the operation, stage, fence, allowance, deadline, request digest, validator policy, and idempotency identity before dispatch.
3. Provider execution records ordered attempts and exact provider-reported usage. Retry classification describes execution only; it does not authorize another customer charge.
4. Output is accepted only after the declared validator returns matching identity, content, and digest evidence.
5. The producer may build a canonical execution receipt after terminal validation. Receipt construction refuses any changed call or output identity.
6. The consuming application may attach validated output to an immutable usable Website Version.
7. The consuming application settles or releases its reservation and may return `TerminalSettlementResultData` as canonical transition evidence.

## Financial boundary

The managed-provider contract accepts no wallet, journal, allocation, entitlement, pricing-policy, refund, chargeback, or balance model. AI Orchestrator does not reserve, settle, release, price, refund, or adjust customer funds. Opaque reservation and terminal-transition references exist only to correlate caller-owned evidence.
