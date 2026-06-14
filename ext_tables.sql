#
# Table structure for table 'tx_askbatchimporter_run'
# State / progress tracking per import run
#
CREATE TABLE tx_askbatchimporter_run (
    run_id varchar(36) DEFAULT '' NOT NULL,
    target varchar(64) DEFAULT '' NOT NULL,
    phase varchar(16) DEFAULT 'fetch' NOT NULL,
    last_batch int(11) unsigned DEFAULT 0 NOT NULL,
    status varchar(16) DEFAULT 'pending' NOT NULL,
    created int(11) unsigned DEFAULT 0 NOT NULL,

    KEY run (run_id),
    KEY target (target)
);

#
# Table structure for table 'tx_askbatchimporter_batch'
# Raw staging of fetched BC batches (500 records each)
# 'status' also carries the Phase 2 progress: pending -> processing -> done
#
CREATE TABLE tx_askbatchimporter_batch (
   run_id varchar(36) DEFAULT '' NOT NULL,
   batch_number int(11) unsigned DEFAULT 0 NOT NULL,
   raw_data mediumtext,
   status varchar(16) DEFAULT 'pending' NOT NULL,
   created int(11) unsigned DEFAULT 0 NOT NULL,

   KEY run_status (run_id, status),
   KEY run_batch (run_id, batch_number)
);