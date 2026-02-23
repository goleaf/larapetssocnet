---
description: Structured 7-step workflow for building features with AI coding agents — from scoping to test-driven execution
---

# Structured Feature-Building Workflow

Source: [ddewhurst.com](https://ddewhurst.com/blog/structured-workflow-for-building-features-with-ai-coding-agents/)

---

## Step 1: Gather Requirements

Talk to stakeholders, understand the problem, capture constraints. The AI workflow starts **after** you know what you're building.

Requirements don't need to be a formal PRD — a clear written summary of what the feature should do, who it's for, and what constraints exist is enough. The important thing is that it's **written down**.

---

## Step 2: High-Level Scoping with Parallel Research

Feed the requirements into the agent and instruct it to **research the codebase before proposing anything**. Use parallelism — investigate multiple angles simultaneously.

**Prompt template:**

```
Here are the requirements for [feature]. Before proposing an approach, I need you to research our codebase thoroughly. Launch parallel sub-agents to:

1. Find all existing code related to [domain area] — models, services, API endpoints, tests. Map out the current architecture.
2. Identify similar patterns we've used before for [comparable feature]. How did we handle [specific concern]?
3. Check for potential conflicts — what existing functionality might this feature affect? Are there shared components or database tables that would need changes?

Synthesise the findings into a high-level scoping document covering: proposed approach, files and systems affected, risks, and open questions.
```

**Output:** A markdown scoping document — not code, not a PR. Just a clear description of the proposed approach, the parts of the system it touches, and the questions that still need answering.

---

## Step 3: Review the Scope with Specialised Sub-Agents

Read the scope yourself first. Then run it through a second round of AI review with **specialised reviewers**:

**Prompt template:**

```
Read the scoping document at [path]. Dispatch parallel review agents:

1. Architecture review — does the proposed approach fit our existing patterns? Are there simpler alternatives?
2. Edge case analysis — what failure modes, race conditions, or data integrity issues could arise?
3. Dependency review — what existing tests, features, or integrations might break?

Compile the findings into a review summary with specific concerns and suggested changes.
```

This is a **feedback loop**. The review produces concerns, you update the scope, and run the review again. 2–3 rounds is usually enough.

> [!IMPORTANT]
> The writer/reviewer separation matters. A fresh agent reviewing a document it didn't write will catch things the original author missed.

---

## Step 4: Human Sign-Off

Share the scope with relevant team members. This is a **sanity check**, not a rubber stamp. Look for:

- "We tried something similar last year and it didn't work because…"
- "This would conflict with what Team X is building right now"
- "The data model assumption in section 3 is wrong"

Once the team is satisfied, **sign it off**. This is the commitment point.

---

## Step 5: Detailed Implementation Plan

Convert the approved scope into a step-by-step implementation plan.

**Prompt template:**

```
Read the approved scoping document at [path]. Convert it into a detailed implementation plan. For each step, specify:

- Which files to create or modify
- What functions, classes, or components to write
- What tests to add (unit, integration, or both)
- Dependencies on previous steps
- Verification criteria — how do we know this step is done?

Order the steps so each one builds on the last and can be tested independently.
```

Each step should be granular enough to be a **single commit**. "Add user authentication" is too vague. "Add `validateSession` middleware to `src/middleware/auth.ts` that checks JWT expiry and returns 401 for expired tokens" is about right.

---

## Step 6: Plan Review Feedback Loop

Same process as Step 3, applied to the implementation plan. The scope review asked "are we building the right thing?" — the plan review asks "**will this sequence of steps actually get us there?**"

Review agents should check:

- **Ordering** — are there hidden dependencies between steps?
- **Completeness** — does the plan cover everything in the scope?
- **Testability** — can each step be verified independently?
- **Migration safety** — are database changes backwards-compatible? Is there a rollback path?

---

## Step 7: Execute with Small, Testable Tasks

Hand the final plan to a **fresh context**. A clean context window is important — don't carry baggage from hours of scoping and review.

**Prompt template:**

```
Read the implementation plan at [path]. Break it down into the smallest possible testable tasks. Work through them one at a time:

- Before implementing each task, add it to your task list
- Write tests first where applicable
- After completing each task, verify it against the implementation plan
- Run the test suite before moving to the next task
- If a task reveals a problem with the plan, stop and flag it

Do not move to the next task until the current one passes verification.
```

// turbo-all

---

## Key Principles

| Principle | Detail |
|---|---|
| **Planning is the multiplier** | Quality of AI code is determined by how clearly you scope the task, not which model you use |
| **Separate writing from reviewing** | Fresh context catches things the original author misses |
| **Parallelism for research, not implementation** | Sub-agents research simultaneously; implementation is sequential and verified |
| **Humans at decision points** | AI handles volume; humans handle judgement |
| **Small tasks + tests** | Tighter feedback loops mean less damage from wrong turns |
