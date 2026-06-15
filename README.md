# ASK BATCH IMPORTER

Batch import of product data from **Microsoft Business Central** into configurable targets.
TYPO3 13.4 extension, CLI-driven, designed for large data volumes.

---

## Overview

The extension fetches product data quarterly from Microsoft Business Central (BC) and
writes it to a configurable target. It is built as a **two-phase process**:
fetch everything first, then process and write. Both phases can be run independently
and resumed after a failure.

The import runs exclusively via CLI — no backend module.

The concrete target is controlled via `--target` and its corresponding configuration file.
The processing core has **no knowledge** of the target — writing is handled through an
exchangeable writer layer (see Architecture).

---

## Two Phases

### Phase 1 — Fetch

- Data is fetched in **batches of 500** via the BC OData API.
- Each batch is stored raw in a **staging table**.
- Progress is tracked in a **state table** (last fetched batch).
- If a run is interrupted (timeout, network), the next start resumes from the last batch.

### Phase 2 — Process

- The staging table is processed **batch by batch**, not in a single pass.
- Only batches with `status != done` are picked up — completed batches are skipped,
  which makes the phase **resumable** after a crash (resume granularity = one batch).
- Per record: **validation → mapping → write**.
- Writes are **idempotent (upsert)**: records are matched on `external_id` (the BC
  `artnr`) — update if present, insert otherwise. Re-processing a partially written
  batch therefore produces **no duplicates**.
- Writing is handled by the writer layer. On the direct-write path each batch runs in a
  **DB transaction**; the batch is only flagged `done` after commit (atomic per batch).
  The DataHandler path skips the explicit transaction — the upsert reconciles instead.
- After a successful run, the staging data for that run is cleaned up.

Phases can be run individually (`--phase=1` / `--phase=2`) or together (`--phase=all`).

---

## Architecture

```
Command (--target=exampleproject --phase=all)
   │
   ├─ Phase 1 ──> BatchFetcher
   │               ├─ BcApiClient            (OAuth, OData, batches of 500)
   │               ├─ BatchRepository        (→ staging table)
   │               └─ ImportStateRepository  (resume point)
   │
   └─ Phase 2 ──> BatchProcessor
                   ├─ BatchRepository         (read staging)
                   ├─ Validator               (required fields, types)
                   ├─ ProductDataMapper       (BC field → target field, config-driven)
                   └─ WriterInterface         (no knowledge of concrete target)
                        └─ Typo3DataHandlerWriter   (exampleproject)
                           OxidWriter               (other project)
```

Core idea: `BatchProcessor` only depends on `WriterInterface`. The concrete write target
is resolved via dependency injection and `--target`. Table names like `tx_products` or
`oxarticles` appear exclusively in the respective writer implementation — never in the
processing core. New targets are added as additional writers without touching the processor
(Strategy + DI).

---

## Directory Structure

```
ask_batch_importer/
│
├── composer.json
├── ext_emconf.php
├── ext_tables.sql                           # needed, because there won`t be a TCA
├── README.md
├── .gitignore
│
├── Classes/
│   ├── Command/
│   │   └── ImportProductsCommand.php        # CLI entry: --target, --phase
│   │
│   ├── Fetcher/
│   │   ├── ProductSourceInterface.php       # Method fetchItems()
│   │   ├── BcApiClient.php                  # BC: OAuth, OData
│   │   ├── JsonFileSource.php               # Test source: read batches from JSON files (tests, dev)
│   │   └── BatchFetcher.php                 # Phase 1: fetch → staging, set state
│   │   └── Dto/
│   │       ├── BcConnectionConfig.php   
│   │       └── BcConnectionConfigProvider.php
│   │
│   ├── Processor/
│   │   ├── BatchProcessor.php               # Phase 2: staging → map → write
│   │   ├── ProcessingResult.php             # Phase 2: staging → map → write
│   │   ├── ProductDataMapper.php            # BC field → target field (config-driven)
│   │   └── Validator.php                    # required fields, types
│   │
│   ├── Writer/
│   │   ├── WriterInterface.php              # contract (e.g. persist(array $records))
│   │   └── Typo3DataHandlerWriter.php       # DataHandler → tx_products
│   │
│   ├── State/
│   │   ├── ImportRun.php                    # DTO: run-id, phase, status
│   │   └── ImportStateRepository.php        # read/write state (resume)
│   │
│   └── Domain/
│       └── Repository/
│           └── BatchRepository.php          # staging table CRUD
│
├── Configuration/
│   ├── Services.yaml                        # DI
│   └── Imports/
│       └── exampleproject.yaml              # mapping + target
│
└── Tests/
    ├── Unit/
    │   ├── ProductDataMapperTest.php
    │   └── ValidatorTest.php
    └── Functional/
        └── BatchProcessorTest.php
```

---

## Internal Tables

Two lean custom tables without TCA (defined in `ext_tables.sql`):

| Table                          | Purpose | Key fields                                                    |
|--------------------------------|---------|---------------------------------------------------------------|
| `tx_askbatchimporter_batch`    | Staging | `run_id`, `batch_number`, `raw_data` (JSON), `status`         |
| `tx_askbatchimporter_run`      | State   | `run_id`, `target`, `phase`, `last_batch`, `status`, `created`|

---

## Configuration

One file per target under `Configuration/Imports/`. It defines:

- `writer` — which writer class to use
- `pid` — TYPO3 page UID under which records are stored
- `mapping` — field mapping BC→target, with optional type

### Mapping Types

| Type     | Description                                                              |
|----------|--------------------------------------------------------------------------|
| `scalar` | Direct value copy (default, `type` can be omitted)                       |
| `static` | Fixed value with no BC source field; for required fields with a default  |

BC delivers FK fields (e.g. `color`, `surface1`, `category`) as integer UIDs directly —
no lookup mechanism needed, everything is scalar.

### Example: `exampleproject.yaml`

The target `exampleproject` writes exclusively to `tx_products_domain_model_products`.
All values — including FK integer fields — come directly from BC.

```yaml
writer: Ask\AskBatchImporter\Writer\Typo3DataHandlerWriter
pid: 42

mapping:

  # --- Product master data ---
  number:               { field: artnr }
  displayName:          { field: title }
  number2:              { field: modelnr }
  itemCategoryCode:     { field: wg }
  unitPrice:            { field: price }
  unitPriceEur:         { field: price_eur }
  priceRangeFrom:       { field: pricerange_from }
  priceRangeFromEur:    { field: pricerange_from_eur }
  ringWidth:            { field: ring_width }
  stone:                { field: stone }
  stone1:               { field: stone1 }
  stone2:               { field: stone2 }
  stone3:               { field: stone3 }
  width:                { field: width }
  family:               { field: family }
  parentId:             { field: parent_id }
  isParent:             { field: parent }
  variants:             { field: variants }
  variantsMediaNr:      { field: variants_media_nr }
  partnerLevel:         { field: partnerlevel }
  partnerId:            { field: partner_id }
  partner2Id:           { field: partner2_id }

  # --- Multilingual fields ---
  description:          { field: description }
  descriptionEn:        { field: description_en }
  descriptionFr:        { field: description_fr }
  stoneDescription:     { field: stone_description }
  stoneDescriptionEn:   { field: stone_description_en }
  stoneDescriptionFr:   { field: stone_description_fr }

  # --- FK integer fields (UIDs delivered directly by BC) ---
  colorUid:             { field: color }
  surface1Uid:          { field: surface1 }
  surfacePosition1Uid:  { field: surface_position1 }
  surface2Uid:          { field: surface2 }
  surfacePosition2Uid:  { field: surface_position2 }
  surface3Uid:          { field: surface3 }
  surfacePosition3Uid:  { field: surface_position3 }
  categoryUid:          { field: category }
  priceGroupUid:        { field: price_group }

  # --- Static defaults ---
  _hidden:
    field: hidden
    type: static
    value: 0
```

> Unmapped BC fields are ignored. Unmapped target fields retain their database default.

---

## Usage (CLI)

```bash
# Both phases in one run
ddev typo3 ask:import:products --target=exampleproject --phase=all

# Fetch only (Phase 1)
ddev typo3 ask:import:products --target=exampleproject --phase=1

# Process only (Phase 2, from existing staging data)
ddev typo3 ask:import:products --target=exampleproject --phase=2

# Reset run / start fresh
ddev typo3 ask:import:products --target=exampleproject --force-restart
```


```bash
ddev composer require ask/ask_batch_importer:dev-main
```

The folder is excluded in the project's `.gitignore` so the extension keeps its own repository:

```
/packages/ask_batch_importer/
```

---

## Requirements

- TYPO3 13.4
- PHP 8.2+
- Access to a Microsoft Business Central OData API (Azure AD / OAuth 2.0)

---

*Author: Axel Seemann-Kahne · seemann-kahne.de*
