USE mithra;

ALTER TABLE moderator_candidates
    ADD COLUMN declaration_reference VARCHAR(255) NULL,
    ADD COLUMN capability_note VARCHAR(255) NULL,
    ADD COLUMN reviewed_by INT UNSIGNED NULL,
    ADD COLUMN reviewed_at DATETIME NULL,
    ADD CONSTRAINT fk_candidate_evidence_reviewer FOREIGN KEY (reviewed_by)
        REFERENCES users (id) ON DELETE SET NULL;
