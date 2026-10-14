-- BEAUTY PET - SOMENTE ADIÇÕES MOBILE PARA XAMPP
-- Use este arquivo se o banco WEB atual já estiver criado e funcionando.
-- Ele NÃO recria as tabelas antigas.
USE beauty_pet;

-- ============================================================================
-- EXTENSÃO MOBILE - BEAUTY PET
-- MySQL / MariaDB (XAMPP)
--
-- Esta seção foi adicionada SEM remover, renomear ou alterar a lógica das
-- tabelas WEB já existentes acima.
--
-- IMPORTANTE:
-- No XAMPP não existe o Supabase Auth. Por isso, a autenticação do tutor no
-- aplicativo é armazenada separadamente em cliente_contas_mobile.
-- A senha deve ser salva SOMENTE como hash gerado no PHP com password_hash()
-- e validada com password_verify(). Nunca salve senha em texto puro.
-- ============================================================================


-- ============================================================================
-- 1. CONTA MOBILE DO TUTOR
-- ============================================================================
-- Mantém a ficha do tutor na tabela "clientes", já usada pelo WEB.
-- Esta tabela acrescenta somente as informações necessárias para o LOGIN mobile.
CREATE TABLE cliente_contas_mobile (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id          INT UNSIGNED NOT NULL,
    email_login         VARCHAR(150) NOT NULL,
    senha_hash          VARCHAR(255) NOT NULL,
    ativo               BOOLEAN      NOT NULL DEFAULT TRUE,
    ultimo_acesso_em    DATETIME     NULL,
    criado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_cliente_contas_mobile_cliente
        UNIQUE (cliente_id),

    CONSTRAINT uq_cliente_contas_mobile_email
        UNIQUE (email_login),

    CONSTRAINT fk_cliente_contas_mobile_cliente
        FOREIGN KEY (cliente_id)
        REFERENCES clientes (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE INDEX ix_cliente_contas_mobile_ativo
    ON cliente_contas_mobile (ativo);


-- ============================================================================
-- 2. CATEGORIAS DE GASTOS PESSOAIS DO MOBILE
-- ============================================================================
-- Categorias usadas no gráfico/telas do financeiro pessoal do tutor.
CREATE TABLE categorias_gasto_mobile (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(80) NOT NULL,
    ativo               BOOLEAN     NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_categorias_gasto_mobile_nome
        UNIQUE (nome)
) ENGINE = InnoDB;

INSERT INTO categorias_gasto_mobile (nome)
VALUES
    ('Higiene'),
    ('Alimentação'),
    ('Saúde'),
    ('Lazer'),
    ('Outros')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);


-- ============================================================================
-- 3. GASTOS PESSOAIS DO TUTOR
-- ============================================================================
-- Estes gastos NÃO fazem parte do caixa/fechamento do Beauty Pet.
-- São lançamentos pessoais feitos pelo tutor, como ração, medicamento,
-- produto de higiene ou outro gasto externo.
CREATE TABLE gastos_pessoais_mobile (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id          INT UNSIGNED NOT NULL,
    pet_id              INT UNSIGNED NULL,
    categoria_id        INT UNSIGNED NOT NULL,
    descricao           VARCHAR(180) NOT NULL,
    valor               DECIMAL(10,2) NOT NULL,
    data_gasto          DATE NOT NULL,
    observacoes         TEXT NULL,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT ck_gastos_pessoais_mobile_valor
        CHECK (valor > 0),

    CONSTRAINT fk_gastos_pessoais_mobile_cliente
        FOREIGN KEY (cliente_id)
        REFERENCES clientes (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_gastos_pessoais_mobile_pet
        FOREIGN KEY (pet_id)
        REFERENCES pets (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_gastos_pessoais_mobile_categoria
        FOREIGN KEY (categoria_id)
        REFERENCES categorias_gasto_mobile (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE = InnoDB;

CREATE INDEX ix_gastos_pessoais_mobile_cliente_data
    ON gastos_pessoais_mobile (cliente_id, data_gasto);

CREATE INDEX ix_gastos_pessoais_mobile_pet
    ON gastos_pessoais_mobile (pet_id);

CREATE INDEX ix_gastos_pessoais_mobile_categoria
    ON gastos_pessoais_mobile (categoria_id);


-- ============================================================================
-- 4. CATEGORIAS DA AGENDA PESSOAL
-- ============================================================================
CREATE TABLE categorias_evento_mobile (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(80) NOT NULL,
    ativo               BOOLEAN     NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_categorias_evento_mobile_nome
        UNIQUE (nome)
) ENGINE = InnoDB;

INSERT INTO categorias_evento_mobile (nome)
VALUES
    ('Higiene'),
    ('Alimentação'),
    ('Saúde'),
    ('Medicação'),
    ('Vacina'),
    ('Outros')
ON DUPLICATE KEY UPDATE nome = VALUES(nome);


-- ============================================================================
-- 5. AGENDA PESSOAL / ISOLADA DO TUTOR
-- ============================================================================
-- Não substitui a tabela "agendamentos".
-- "agendamentos" continua sendo usada para serviços do BEAUTY PET.
-- "eventos_agenda_mobile" guarda compromissos pessoais criados pelo tutor.
CREATE TABLE eventos_agenda_mobile (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id          INT UNSIGNED NOT NULL,
    pet_id              INT UNSIGNED NULL,
    categoria_id        INT UNSIGNED NULL,
    titulo              VARCHAR(150) NOT NULL,
    descricao           TEXT NULL,
    data_hora_inicio    DATETIME NOT NULL,
    data_hora_fim       DATETIME NULL,
    lembrete_em         DATETIME NULL,
    status              VARCHAR(20) NOT NULL DEFAULT 'ATIVO',
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT ck_eventos_agenda_mobile_periodo
        CHECK (
            data_hora_fim IS NULL
            OR data_hora_fim >= data_hora_inicio
        ),

    CONSTRAINT ck_eventos_agenda_mobile_status
        CHECK (status IN ('ATIVO', 'CONCLUIDO', 'CANCELADO')),

    CONSTRAINT fk_eventos_agenda_mobile_cliente
        FOREIGN KEY (cliente_id)
        REFERENCES clientes (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_eventos_agenda_mobile_pet
        FOREIGN KEY (pet_id)
        REFERENCES pets (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_eventos_agenda_mobile_categoria
        FOREIGN KEY (categoria_id)
        REFERENCES categorias_evento_mobile (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE INDEX ix_eventos_agenda_mobile_cliente_data
    ON eventos_agenda_mobile (cliente_id, data_hora_inicio);

CREATE INDEX ix_eventos_agenda_mobile_pet
    ON eventos_agenda_mobile (pet_id);


-- ============================================================================
-- 6. NOTIFICAÇÕES DO MOBILE
-- ============================================================================
CREATE TABLE notificacoes_mobile (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id          INT UNSIGNED NOT NULL,
    pet_id              INT UNSIGNED NULL,
    agendamento_id      INT UNSIGNED NULL,
    evento_agenda_id    INT UNSIGNED NULL,
    titulo              VARCHAR(150) NOT NULL,
    mensagem            TEXT NOT NULL,
    tipo                VARCHAR(30) NOT NULL DEFAULT 'GERAL',
    lida                BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lida_em             DATETIME NULL,

    CONSTRAINT ck_notificacoes_mobile_tipo
        CHECK (
            tipo IN (
                'GERAL',
                'AGENDAMENTO',
                'LEMBRETE',
                'FINANCEIRO'
            )
        ),

    CONSTRAINT fk_notificacoes_mobile_cliente
        FOREIGN KEY (cliente_id)
        REFERENCES clientes (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_notificacoes_mobile_pet
        FOREIGN KEY (pet_id)
        REFERENCES pets (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_notificacoes_mobile_agendamento
        FOREIGN KEY (agendamento_id)
        REFERENCES agendamentos (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_notificacoes_mobile_evento
        FOREIGN KEY (evento_agenda_id)
        REFERENCES eventos_agenda_mobile (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE INDEX ix_notificacoes_mobile_cliente_lida
    ON notificacoes_mobile (cliente_id, lida, criado_em);


-- ============================================================================
-- 7. VIEW - FINANCEIRO PESSOAL DO MOBILE
-- ============================================================================
-- Ajuda a montar a tela com descrição, categoria, pet, valor e mês.
CREATE OR REPLACE VIEW vw_mobile_gastos_pessoais AS
SELECT
    g.id                                                AS gasto_id,
    g.cliente_id                                       AS cliente_id,
    c.nome                                              AS cliente,
    g.pet_id                                            AS pet_id,
    p.nome                                              AS pet,
    g.categoria_id                                      AS categoria_id,
    cg.nome                                             AS categoria,
    g.descricao                                         AS descricao,
    g.valor                                             AS valor,
    g.data_gasto                                        AS data_gasto,
    YEAR(g.data_gasto)                                  AS ano,
    MONTH(g.data_gasto)                                 AS mes,
    g.observacoes                                       AS observacoes
FROM gastos_pessoais_mobile g
JOIN clientes c
    ON c.id = g.cliente_id
LEFT JOIN pets p
    ON p.id = g.pet_id
JOIN categorias_gasto_mobile cg
    ON cg.id = g.categoria_id;


-- ============================================================================
-- 8. VIEW - RESUMO DO GRÁFICO DE GASTOS PESSOAIS
-- ============================================================================
CREATE OR REPLACE VIEW vw_mobile_gastos_categoria_mes AS
SELECT
    g.cliente_id                                        AS cliente_id,
    YEAR(g.data_gasto)                                  AS ano,
    MONTH(g.data_gasto)                                 AS mes,
    cg.id                                               AS categoria_id,
    cg.nome                                             AS categoria,
    COUNT(g.id)                                         AS quantidade_lancamentos,
    SUM(g.valor)                                        AS total_categoria
FROM gastos_pessoais_mobile g
JOIN categorias_gasto_mobile cg
    ON cg.id = g.categoria_id
GROUP BY
    g.cliente_id,
    YEAR(g.data_gasto),
    MONTH(g.data_gasto),
    cg.id,
    cg.nome;


-- ============================================================================
-- 9. VIEW - TOTAL DO FINANCEIRO PESSOAL POR MÊS
-- ============================================================================
CREATE OR REPLACE VIEW vw_mobile_gastos_total_mes AS
SELECT
    g.cliente_id                                        AS cliente_id,
    YEAR(g.data_gasto)                                  AS ano,
    MONTH(g.data_gasto)                                 AS mes,
    COUNT(g.id)                                         AS quantidade_lancamentos,
    SUM(g.valor)                                        AS total_gasto
FROM gastos_pessoais_mobile g
GROUP BY
    g.cliente_id,
    YEAR(g.data_gasto),
    MONTH(g.data_gasto);


-- ============================================================================
-- 10. VIEW - BUDGET
-- ============================================================================
-- O botão BUDGET usa o financeiro COMERCIAL já existente no WEB.
-- Nenhum valor é duplicado.
-- A view mostra o tutor, o pet que recebeu o serviço, o serviço e o valor.
CREATE OR REPLACE VIEW vw_mobile_budget AS
SELECT
    h.cliente_id                                        AS cliente_id,
    h.cliente                                           AS cliente,
    h.pet_id                                            AS pet_id,
    h.pet                                               AS pet,
    h.data_fechamento                                   AS data_fechamento,
    YEAR(h.data_fechamento)                             AS ano,
    MONTH(h.data_fechamento)                            AS mes,
    h.lancamento_diario_id                              AS lancamento_diario_id,
    h.servico                                           AS servico,
    h.descricao                                         AS descricao,
    h.valor_servico                                     AS valor_servico,
    h.total_adicionais                                  AS total_adicionais,
    h.valor_total                                       AS valor_total
FROM vw_historico_cliente h;


-- ============================================================================
-- 11. VIEW - RESUMO DO BUDGET POR MÊS
-- ============================================================================
CREATE OR REPLACE VIEW vw_mobile_budget_resumo_mes AS
SELECT
    b.cliente_id                                        AS cliente_id,
    b.ano                                               AS ano,
    b.mes                                               AS mes,
    COUNT(b.lancamento_diario_id)                       AS quantidade_lancamentos,
    COUNT(DISTINCT b.pet_id)                            AS quantidade_pets,
    SUM(b.valor_total)                                  AS total_budget
FROM vw_mobile_budget b
GROUP BY
    b.cliente_id,
    b.ano,
    b.mes;


-- ============================================================================
-- 12. VIEW - AGENDA PESSOAL
-- ============================================================================
CREATE OR REPLACE VIEW vw_mobile_agenda_pessoal AS
SELECT
    e.id                                                AS evento_id,
    e.cliente_id                                        AS cliente_id,
    c.nome                                              AS cliente,
    e.pet_id                                            AS pet_id,
    p.nome                                              AS pet,
    e.categoria_id                                      AS categoria_id,
    ce.nome                                             AS categoria,
    e.titulo                                            AS titulo,
    e.descricao                                         AS descricao,
    e.data_hora_inicio                                  AS data_hora_inicio,
    e.data_hora_fim                                     AS data_hora_fim,
    e.lembrete_em                                       AS lembrete_em,
    e.status                                            AS status
FROM eventos_agenda_mobile e
JOIN clientes c
    ON c.id = e.cliente_id
LEFT JOIN pets p
    ON p.id = e.pet_id
LEFT JOIN categorias_evento_mobile ce
    ON ce.id = e.categoria_id;


-- ============================================================================
-- 13. VIEW - AGENDAMENTOS BEAUTY PET NO MOBILE
-- ============================================================================
-- Reutiliza as tabelas WEB já existentes.
-- As solicitações criadas pelo aplicativo devem entrar na tabela agendamentos
-- com origem = 'MOBILE' e status = 'SOLICITADO'.
CREATE OR REPLACE VIEW vw_mobile_agendamentos_beauty AS
SELECT
    a.id                                                AS agendamento_id,
    c.id                                                AS cliente_id,
    c.nome                                              AS cliente,
    p.id                                                AS pet_id,
    p.nome                                              AS pet,
    a.data_hora_inicio                                  AS data_hora_inicio,
    a.data_hora_fim                                     AS data_hora_fim,
    a.origem                                            AS origem,
    a.status                                            AS status,
    a.observacoes_cliente                               AS observacoes_cliente,
    GROUP_CONCAT(
        DISTINCT s.nome
        ORDER BY s.nome
        SEPARATOR ', '
    )                                                   AS servicos
FROM agendamentos a
JOIN pets p
    ON p.id = a.pet_id
JOIN clientes c
    ON c.id = p.cliente_id
LEFT JOIN agendamento_itens ai
    ON ai.agendamento_id = a.id
LEFT JOIN servicos s
    ON s.id = ai.servico_id
GROUP BY
    a.id,
    c.id,
    c.nome,
    p.id,
    p.nome,
    a.data_hora_inicio,
    a.data_hora_fim,
    a.origem,
    a.status,
    a.observacoes_cliente;


-- ============================================================================
-- 14. VIEW - PETS DO TUTOR PARA O MOBILE
-- ============================================================================
CREATE OR REPLACE VIEW vw_mobile_pets_tutor AS
SELECT
    p.id                                                AS pet_id,
    p.cliente_id                                        AS cliente_id,
    p.nome                                              AS pet,
    p.foto_url                                          AS foto_url,
    p.sexo                                              AS sexo,
    p.cor                                               AS cor,
    p.peso                                              AS peso,
    p.porte                                             AS porte,
    p.data_nascimento                                   AS data_nascimento,
    p.nascimento_aproximado                             AS nascimento_aproximado,
    p.castrado                                          AS castrado,
    p.vacinacao_em_dia                                  AS vacinacao_em_dia,
    p.medicamentos                                      AS medicamentos,
    p.observacoes_comportamentais                       AS observacoes_comportamentais,
    p.observacoes_medicas                               AS observacoes_medicas,
    e.nome                                              AS especie,
    r.nome                                              AS raca
FROM pets p
JOIN especies e
    ON e.id = p.especie_id
LEFT JOIN racas r
    ON r.id = p.raca_id
WHERE p.ativo = TRUE;


-- ============================================================================
-- 15. OBSERVAÇÃO DE INTEGRAÇÃO COM O APP
-- ============================================================================
-- No XAMPP, o React Native NÃO deve acessar o MySQL diretamente.
-- Fluxo recomendado:
--
-- React Native
--      ↓ HTTP / fetch / axios
-- API PHP no XAMPP
--      ↓ PDO
-- MySQL/MariaDB
--
-- O PHP deve:
-- - validar login do tutor usando cliente_contas_mobile;
-- - usar password_verify() para conferir senha;
-- - descobrir o cliente_id logado;
-- - sempre filtrar consultas por esse cliente_id;
-- - validar que o pet pertence ao tutor antes de INSERT/UPDATE;
-- - ao solicitar serviço do Beauty Pet:
--       INSERT em agendamentos com origem='MOBILE' e status='SOLICITADO';
--       INSERT dos serviços em agendamento_itens;
-- - usar vw_mobile_budget para o botão Budget;
-- - usar as views/tabelas de gastos_pessoais_mobile para o gráfico pessoal;
-- - usar eventos_agenda_mobile para a agenda isolada.
-- ============================================================================

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
