# ASK BATCH IMPORTER

Batch import of product data from pluggable sources into pluggable targets.
TYPO3 13.4 extension, CLI-driven, designed for large data volumes.


---

## Context
A customer is transitioning to a new ERP system and provides product data for three different projects—two TYPO3 installations and one OXID eShop—based on a shared, identical dataset.
The goal of this module is to provide a unified processing layer that can reliably distribute and transform this product data for all three target systems.

---

## Overview

The extension fetches product data from a pluggable source (BC OData API, CSV, JSON fixture)
and writes it into a pluggable target (TYPO3, OXID). It is built as a **two-phase process**:
fetch everything first, then process and write. Both phases can be run independently
and resumed after a failure.

The import runs exclusively via CLI — no backend module.

Source, writer, and field mapping are controlled per `--target` via a YAML configuration file.

---

## Two Phases

### Phase 1 — Fetch

- Data is fetched page by page from the configured source (BC API, CSV, or JSON fixture).
- Each page/chunk is stored raw as a batch in a **staging table**.
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
Command (--target=exampleproject)
   │
   ├─ Phase 1 ──> BatchFetcher
   │               ├─ SourceFactory → ProductSourceInterface (BcApiSource | CsvFileSource | JsonFileSource)
   │               ├─ BatchRepository        (→ staging table)
   │               └─ ImportStateRepository  (resume point)
   │
   └─ Phase 2 ──> BatchProcessor
                   ├─ BatchRepository         (read staging)
                   ├─ Validator               (required fields, types)
                   ├─ ProductDataMapper       (source field → target field, config-driven)
                   └─ WriterFactory → WriterInterface (Typo3Writer | OxidWriter)

```

---

## Directory Structure

```
ask_batch_importer/
│
├── composer.json
├── ext_emconf.php
├── ext_tables.sql                           # staging + state tables (no TCA)
├── README.md
│
├── Classes/
│   ├── Command/
│   │   ├── ImportProductsCommand.php        # CLI entry: --target
│   │   └── FlushStagingCommand.php          # CLI: truncate staging + state tables
│   │
│   ├── Fetcher/
│   │   ├── ProductSourceInterface.php       # Interface: fetchPages(): iterable
│   │   ├── SourceFactory.php                # creates source based on fetcher.type
│   │   ├── BcApiSource.php                  # BC OData API (OAuth2, server-driven paging)
│   │   ├── CsvFileSource.php                # streamed CSV (chunked, BOM-safe)
│   │   ├── JsonFileSource.php               # JSON fixture for testing
│   │   ├── BatchFetcher.php                 # Phase 1: fetch → staging, set state
│   │   └── Dto/
│   │       ├── BcConnectionConfig.php       # DTO: BC credentials
│   │       └── BcConnectionConfigProvider.php
│   │
│   ├── Processor/
│   │   ├── BatchProcessor.php               # Phase 2: staging → map → write
│   │   ├── ProcessingResult.php             # counters: batches, inserted, updated
│   │   ├── ProductDataMapper.php            # source field → target field (config-driven)
│   │   └── Validator.php                    # required fields
│   │
│   ├── Writer/
│   │   ├── WriterInterface.php              # Interface: persist(array $records): array
│   │   ├── WriterFactory.php                # creates writer based on writer.type (FQCN)
│   │   ├── Typo3Writer.php                  # upsert into TYPO3 DB (ConnectionPool)
│   │   └── OxidWriter.php                   # (not yet implemented)
│   │
│   ├── Config/
│   │   ├── ProjectConfig.php                # DTO: fetcher, writer, mapping, …
│   │   └── ProjectConfigLoader.php          # loads + normalizes target YAML
│   │
│   ├── State/
│   │   └── ImportRun.php                    # DTO: run-id, phase, status
│   │
│   └── Domain/
│       └── Repository/
│           ├── BatchRepository.php          # staging table CRUD
│           └── ImportStateRepository.php    # read/write state (resume)
│
├── Configuration/
│   ├── Services.yaml                        # DI
│   └── Imports/
│       └── *.yaml                           # one file per target
│
└── Resources/
    └── Private/
        └── Fixtures/
            ├── test_items.csv               # CSV demo data
            └── test_items.json              # JSON demo data
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

One file per target under `Configuration/Imports/`. It defines the source, writer, and field mapping.

### Example: `exampleproject.yaml`

```yaml
fetcher:
  type: Ask\AskBatchImporter\Fetcher\CsvFileSource
  file: 'EXT:.../products.csv'
  chunkSize: 500
  delimiter: ';'

writer:
  type: Ask\AskBatchImporter\Writer\Typo3Writer
  connection: Default
  table: tx_products_domain_model_products
  upsertKey: artnr
  pid: 42

mapping:

  # target field:
  #   source: source field name
  #   type: string | int | float | bool | static
  #   required: true   (optional, validated in Phase 2)

  artnr:
    source: ID
    type: string
    required: true

  title:
    source: BezeichnungD
    type: string

  price:
    source: AUKurs1
    type: float

  # static: fixed value, no source field
  hidden:
    type: static
    value: 0
```

> Unmapped source fields are ignored. Unmapped target fields retain their database default.

---

## Installation

```bash
ddev composer require ask/ask_batch_importer:dev-main
```

---

## Usage (CLI)

Run a full import (fetch + process) for the given target:

```bash
ddev typo3 ask:import:products --target=exampleproject
```

Flush all staged batches and run records (clean slate for testing):

```bash
ddev typo3 ask:import:flush
```

---

## Requirements

- TYPO3 13.4
- PHP 8.2+
- Access to a Microsoft Business Central OData API (Azure AD / OAuth 2.0) — only when using `fetcher.type: bc`

---

*Author: Axel Seemann-Kahne · seemann-kahne.de*