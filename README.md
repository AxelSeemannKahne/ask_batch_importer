# ASK BATCH IMPORTER

Batch import of product data from pluggable sources into pluggable targets.
TYPO3 13.4 extension, CLI-driven, designed for large data volumes.


---

## Context
A customer is transitioning to a new ERP system and provides product data for three different projects—two TYPO3 installations and one OXID eShop—based on a shared, identical dataset.
The goal of this module is to provide a unified processing layer that can reliably distribute and transform this product data for all three target systems.

---

## Overview

The extension fetches product data quarterly from Microsoft Business Central (BC) and
writes it to a configurable target. It is built as a **two-phase process**:
fetch everything first, then process and write. Both phases can be run independently
and resumed after a failure.

The import runs exclusively via CLI — no backend module.

The concrete target is controlled via `--target` and its corresponding configuration file.

---

## Two Phases

### Phase 1 — Fetch

- Data is fetched page by page via the BC OData API (server-driven paging).
- Each page is stored raw as a batch in a **staging table**.
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
- Each batch runs in a **DB transaction**; the batch is only flagged `done` after commit (atomic per batch).

---

## Architecture

```
Command (--target=exampleproject --phase=all)
   │
   ├─ Phase 1 ──> BatchFetcher
   │               ├─ ProductSourceInterface (BC OData API or JSON files for testing)
   │               ├─ BatchRepository        (→ staging table)
   │               └─ ImportStateRepository  (resume point)
   │
   └─ Phase 2 ──> BatchProcessor
                   ├─ BatchRepository         (read staging)
                   ├─ Validator               (required fields, types)
                   ├─ ProductDataMapper       (BC field → target field, config-driven)
                   └─ WriterInterface         (switchable DB writer)

```

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
│   │   ├── ProductSourceInterface.php       # Interface: fetchPages()
│   │   ├── BcApiClient.php                  # BC: OAuth, OData
│   │   ├── JsonFileSource.php               # Test source: read from local JSON fixture
│   │   ├── BatchFetcher.php                 # Phase 1: fetch → staging, set state
│   │   └── Dto/
│   │       ├── BcConnectionConfig.php   
│   │       └── BcConnectionConfigProvider.php
│   │
│   ├── Processor/
│   │   ├── BatchProcessor.php               # Phase 2: staging → map → write
│   │   ├── ProcessingResult.php             # Counters for batches and records
│   │   ├── ProductDataMapper.php            # BC field → target field (config-driven)
│   │   └── Validator.php                    # required fields
│   │
│   ├── Writer/
│   │   ├── WriterInterface.php              # Interface for writing records
│   │   ├── OxidWriter.php                   # Implementation for OXID
│   │   ├── Typo3Writer.php                  # Implementation for TYPO3 DB
│   │   └── WriterFactory.php                # Factory to create writer based on target config
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

- `connection` — TYPO3 database connection name
- `table` — target database table
- `upsertKey` — field used to match existing records (update vs. insert)
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

```yaml
connection: Default
table: tx_products_domain_model_products
upsertKey: artnr
pid: 42

mapping:

  # target field:
  #   source: BC field name
  #   type: string | int | float | bool | static
  #   required: true   (optional, validation in Phase 2)

  artnr:
    source: number
    type: string
    required: true

  title:
    source: displayName
    type: string

  price:
    source: unitPrice
    type: float

  # static: fixed value, no BC source field
  hidden:
    type: static
    value: 0
```

> Unmapped BC fields are ignored. Unmapped target fields retain their database default.

---

## Usage (CLI)

```bash
ddev typo3 ask:import:products --target=exampleproject
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