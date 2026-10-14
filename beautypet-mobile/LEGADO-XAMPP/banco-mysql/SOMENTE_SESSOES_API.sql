USE beauty_pet;
-- ============================================================================
-- SESSÕES DA API MOBILE
-- A API armazena somente o HASH do token de sessão; o token original fica no app.
-- ============================================================================
CREATE TABLE sessoes_mobile (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conta_mobile_id     INT UNSIGNED NOT NULL,
    token_hash          CHAR(64) NOT NULL,
    expira_em           DATETIME NOT NULL,
    ultimo_uso_em       DATETIME NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_sessoes_mobile_token
        UNIQUE (token_hash),

    CONSTRAINT fk_sessoes_mobile_conta
        FOREIGN KEY (conta_mobile_id)
        REFERENCES cliente_contas_mobile (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE INDEX ix_sessoes_mobile_conta
    ON sessoes_mobile (conta_mobile_id);

CREATE INDEX ix_sessoes_mobile_expira
    ON sessoes_mobile (expira_em);
