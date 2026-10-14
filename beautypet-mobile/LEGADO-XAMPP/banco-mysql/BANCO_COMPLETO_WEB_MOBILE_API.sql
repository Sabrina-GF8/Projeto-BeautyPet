-- ============================================================================
-- ARQUIVO: banco/banco.sql
-- RESPONSABILIDADE: criar a estrutura de dados necessária ao sistema web Beauty Pet.
-- A documentação abaixo explica a finalidade das principais tabelas, relações,
-- dados iniciais e views sem alterar nenhum comando SQL original.
-- ============================================================================

-- BEAUTY PET - BANCO COMPLETO
-- MySQL 8+
-- Arquivo único com a estrutura atual do projeto.
-- Para um banco novo. Se o seu banco atual já está funcionando, não precisa recriar.

CREATE DATABASE IF NOT EXISTS beauty_pet
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE beauty_pet;


-- Armazena as contas que podem acessar o sistema web e seus perfis de permissão.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE usuarios (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(120) NOT NULL,
    email               VARCHAR(150) NOT NULL,
    senha_hash          VARCHAR(255) NOT NULL,
    perfil              VARCHAR(20)  NOT NULL,
    ativo               BOOLEAN      NOT NULL DEFAULT TRUE,
    criado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_usuarios_email
        UNIQUE (email),

    CONSTRAINT ck_usuarios_perfil
        CHECK (perfil IN ('ADMINISTRADOR', 'FUNCIONARIO'))
) ENGINE = InnoDB;


-- Armazena os dados cadastrais e de contato dos tutores/clientes.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE clientes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(150) NOT NULL,
    cpf                 VARCHAR(11)  NULL,
    email               VARCHAR(150) NULL,
    telefone_principal  VARCHAR(15)  NOT NULL,
    telefone_reserva    VARCHAR(15)  NULL,

    cep                 VARCHAR(8)   NULL,
    logradouro          VARCHAR(180) NULL,
    numero              VARCHAR(20)  NULL,
    complemento         VARCHAR(100) NULL,
    bairro              VARCHAR(100) NULL,
    cidade              VARCHAR(100) NULL,
    estado              CHAR(2)      NULL,
    referencia          VARCHAR(180) NULL,

    observacoes         TEXT         NULL,
    ativo               BOOLEAN      NOT NULL DEFAULT TRUE,
    criado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_clientes_cpf
        UNIQUE (cpf),

    CONSTRAINT ck_clientes_cpf
        CHECK (cpf IS NULL OR CHAR_LENGTH(cpf) = 11),

    CONSTRAINT ck_clientes_cep
        CHECK (cep IS NULL OR CHAR_LENGTH(cep) = 8)
) ENGINE = InnoDB;

CREATE INDEX ix_clientes_nome
    ON clientes (nome);

CREATE INDEX ix_clientes_telefone
    ON clientes (telefone_principal);


-- Lista as espécies disponíveis para classificar os pets.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE especies (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(60) NOT NULL,
    ativo               BOOLEAN     NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_especies_nome
        UNIQUE (nome)
) ENGINE = InnoDB;


-- Cria a tabela "racas" utilizada por este módulo do sistema.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE racas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    especie_id          INT UNSIGNED NOT NULL,
    nome                VARCHAR(100) NOT NULL,
    ativo               BOOLEAN      NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_racas_especie_nome
        UNIQUE (especie_id, nome),

    CONSTRAINT fk_racas_especie
        FOREIGN KEY (especie_id)
        REFERENCES especies (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE = InnoDB;


-- Armazena os animais cadastrados e o vínculo com seus responsáveis.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE pets (
    id                              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id                      INT UNSIGNED NOT NULL,
    especie_id                      INT UNSIGNED NOT NULL,
    raca_id                         INT UNSIGNED NULL,

    nome                            VARCHAR(100) NOT NULL,
    foto_url                        VARCHAR(255) NULL,
    sexo                            VARCHAR(20)  NOT NULL DEFAULT 'NAO_INFORMADO',
    cor                             VARCHAR(100) NULL,
    peso                            DECIMAL(6,2) NULL,
    porte                           VARCHAR(20)  NULL,
    data_nascimento                 DATE         NULL,
    nascimento_aproximado           BOOLEAN      NOT NULL DEFAULT FALSE,

    castrado                        BOOLEAN      NULL,
    vacinacao_em_dia                BOOLEAN      NULL,
    medicamentos                    TEXT         NULL,
    observacoes_comportamentais     TEXT         NULL,
    observacoes_medicas             TEXT         NULL,

    ativo                           BOOLEAN      NOT NULL DEFAULT TRUE,
    criado_em                       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em                   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                 ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT ck_pets_sexo
        CHECK (sexo IN ('MACHO', 'FEMEA', 'NAO_INFORMADO')),

    CONSTRAINT ck_pets_peso
        CHECK (peso IS NULL OR peso >= 0),

    CONSTRAINT ck_pets_porte
        CHECK (porte IS NULL OR porte IN ('PEQUENO', 'MEDIO', 'GRANDE')),

    CONSTRAINT fk_pets_cliente
        FOREIGN KEY (cliente_id)
        REFERENCES clientes (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_pets_especie
        FOREIGN KEY (especie_id)
        REFERENCES especies (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_pets_raca
        FOREIGN KEY (raca_id)
        REFERENCES racas (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE INDEX ix_pets_cliente
    ON pets (cliente_id);

CREATE INDEX ix_pets_nome
    ON pets (nome);

CREATE INDEX ix_pets_especie_raca
    ON pets (especie_id, raca_id);


-- Organiza os serviços em grupos como banho e tosa, creche, hotel e veterinário.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE categorias_servico (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(80) NOT NULL,
    ativo               BOOLEAN     NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_categorias_servico_nome
        UNIQUE (nome)
) ENGINE = InnoDB;


-- Cadastra os serviços que podem ser agendados ou lançados no fechamento.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE servicos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id        INT UNSIGNED NOT NULL,
    nome                VARCHAR(120) NOT NULL,
    descricao           VARCHAR(255) NULL,
    aceita_pacote       BOOLEAN      NOT NULL DEFAULT FALSE,
    ativo               BOOLEAN      NOT NULL DEFAULT TRUE,
    criado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_servicos_categoria_nome
        UNIQUE (categoria_id, nome),

    CONSTRAINT fk_servicos_categoria
        FOREIGN KEY (categoria_id)
        REFERENCES categorias_servico (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE = InnoDB;

CREATE INDEX ix_servicos_categoria
    ON servicos (categoria_id);

CREATE INDEX ix_servicos_nome
    ON servicos (nome);


-- Define modelos de pacotes de serviços e quantidade de créditos.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE pacotes (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    servico_id              INT UNSIGNED NOT NULL,
    nome                    VARCHAR(120) NOT NULL,
    quantidade_creditos     INT UNSIGNED NOT NULL,
    validade_dias           INT UNSIGNED NULL,
    ativo                   BOOLEAN      NOT NULL DEFAULT TRUE,
    criado_em               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT uq_pacotes_servico_nome
        UNIQUE (servico_id, nome),

    CONSTRAINT ck_pacotes_creditos
        CHECK (quantidade_creditos > 0),

    CONSTRAINT fk_pacotes_servico
        FOREIGN KEY (servico_id)
        REFERENCES servicos (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE = InnoDB;


-- Registra os pacotes contratados por cada pet.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE pet_pacotes (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pet_id                  INT UNSIGNED NOT NULL,
    pacote_id               INT UNSIGNED NOT NULL,
    data_compra             DATE         NOT NULL,
    data_validade           DATE         NULL,
    quantidade_creditos     INT UNSIGNED NOT NULL,
    status                  VARCHAR(20)  NOT NULL DEFAULT 'ATIVO',
    observacoes             VARCHAR(255) NULL,
    criado_em               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT ck_pet_pacotes_creditos
        CHECK (quantidade_creditos > 0),

    CONSTRAINT ck_pet_pacotes_status
        CHECK (status IN ('ATIVO', 'ESGOTADO', 'VENCIDO', 'CANCELADO')),

    CONSTRAINT fk_pet_pacotes_pet
        FOREIGN KEY (pet_id)
        REFERENCES pets (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_pet_pacotes_pacote
        FOREIGN KEY (pacote_id)
        REFERENCES pacotes (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE = InnoDB;

CREATE INDEX ix_pet_pacotes_pet_status
    ON pet_pacotes (pet_id, status);


-- Registra data, horário, origem, status e pet de cada agendamento.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE agendamentos (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pet_id                  INT UNSIGNED NOT NULL,
    data_hora_inicio        DATETIME     NOT NULL,
    data_hora_fim           DATETIME     NULL,
    origem                  VARCHAR(20)  NOT NULL DEFAULT 'WEB',
    status                  VARCHAR(25)  NOT NULL DEFAULT 'SOLICITADO',
    observacoes_cliente     TEXT         NULL,
    observacoes_internas    TEXT         NULL,
    criado_por              INT UNSIGNED NULL,
    respondido_por          INT UNSIGNED NULL,
    criado_em               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    respondido_em           DATETIME     NULL,

    CONSTRAINT ck_agendamentos_origem
        CHECK (origem IN ('WEB', 'MOBILE', 'INTERNO')),

    CONSTRAINT ck_agendamentos_status
        CHECK (
            status IN (
                'SOLICITADO',
                'CONFIRMADO',
                'RECUSADO',
                'CANCELADO',
                'EM_ATENDIMENTO',
                'CONCLUIDO'
            )
        ),

    CONSTRAINT ck_agendamentos_periodo
        CHECK (
            data_hora_fim IS NULL
            OR data_hora_fim >= data_hora_inicio
        ),

    CONSTRAINT fk_agendamentos_pet
        FOREIGN KEY (pet_id)
        REFERENCES pets (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_agendamentos_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_agendamentos_respondido_por
        FOREIGN KEY (respondido_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE INDEX ix_agendamentos_data_status
    ON agendamentos (data_hora_inicio, status);

CREATE INDEX ix_agendamentos_pet
    ON agendamentos (pet_id);


-- Relaciona um agendamento aos serviços incluídos nele.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE agendamento_itens (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agendamento_id          INT UNSIGNED NOT NULL,
    servico_id              INT UNSIGNED NOT NULL,
    observacoes             VARCHAR(255) NULL,

    CONSTRAINT uq_agendamento_servico
        UNIQUE (agendamento_id, servico_id),

    CONSTRAINT fk_agendamento_itens_agendamento
        FOREIGN KEY (agendamento_id)
        REFERENCES agendamentos (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_agendamento_itens_servico
        FOREIGN KEY (servico_id)
        REFERENCES servicos (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE = InnoDB;


-- Define em quais dias/intervalos existe uma configuração padrão de banho e tosa.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE agenda_padrao_banho_tosa (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dia_semana          TINYINT UNSIGNED NOT NULL,
    hora_inicio         TIME         NOT NULL,
    hora_fim            TIME         NOT NULL,
    data_inicio         DATE         NOT NULL,
    tipo_duracao        VARCHAR(20)  NOT NULL DEFAULT 'INDETERMINADO',
    data_fim            DATE         NULL,
    ativo               BOOLEAN      NOT NULL DEFAULT TRUE,
    criado_por          INT UNSIGNED NULL,
    criado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT ck_agenda_padrao_dia
        CHECK (dia_semana BETWEEN 1 AND 7),

    CONSTRAINT ck_agenda_padrao_horario
        CHECK (hora_fim > hora_inicio),

    CONSTRAINT ck_agenda_padrao_duracao
        CHECK (tipo_duracao IN ('INDETERMINADO', 'ATE_DATA')),

    CONSTRAINT ck_agenda_padrao_periodo
        CHECK (
            (tipo_duracao = 'INDETERMINADO' AND data_fim IS NULL)
            OR
            (
                tipo_duracao = 'ATE_DATA'
                AND data_fim IS NOT NULL
                AND data_fim >= data_inicio
            )
        ),

    CONSTRAINT fk_agenda_padrao_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE = InnoDB;


-- Guarda a capacidade de vagas de cada bloco de horário da agenda-padrão.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE agenda_padrao_banho_tosa_horarios (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agenda_padrao_id    INT UNSIGNED NOT NULL,
    horario             TIME         NOT NULL,
    quantidade_vagas    TINYINT UNSIGNED NOT NULL DEFAULT 5,

    CONSTRAINT uq_agenda_padrao_horario
        UNIQUE (agenda_padrao_id, horario),

    CONSTRAINT ck_agenda_padrao_quantidade
        CHECK (quantidade_vagas BETWEEN 0 AND 5),

    CONSTRAINT fk_agenda_padrao_horarios
        FOREIGN KEY (agenda_padrao_id)
        REFERENCES agenda_padrao_banho_tosa (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE INDEX ix_agenda_padrao_busca
    ON agenda_padrao_banho_tosa (
        dia_semana,
        data_inicio,
        data_fim,
        ativo
    );

CREATE INDEX ix_agenda_padrao_horarios_busca
    ON agenda_padrao_banho_tosa_horarios (
        agenda_padrao_id,
        horario,
        quantidade_vagas
    );


-- Cria a tabela "atendimentos" utilizada por este módulo do sistema.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE atendimentos (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pet_id                  INT UNSIGNED NOT NULL,
    agendamento_id          INT UNSIGNED NULL,
    data_hora_entrada       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_hora_saida         DATETIME     NULL,
    status                  VARCHAR(20)  NOT NULL DEFAULT 'ABERTO',
    observacoes_gerais      TEXT         NULL,
    criado_por              INT UNSIGNED NULL,
    finalizado_por          INT UNSIGNED NULL,
    criado_em               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT ck_atendimentos_status
        CHECK (status IN ('ABERTO', 'FINALIZADO', 'CANCELADO')),

    CONSTRAINT ck_atendimentos_periodo
        CHECK (
            data_hora_saida IS NULL
            OR data_hora_saida >= data_hora_entrada
        ),

    CONSTRAINT fk_atendimentos_pet
        FOREIGN KEY (pet_id)
        REFERENCES pets (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_atendimentos_agendamento
        FOREIGN KEY (agendamento_id)
        REFERENCES agendamentos (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_atendimentos_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_atendimentos_finalizado_por
        FOREIGN KEY (finalizado_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE INDEX ix_atendimentos_data_status
    ON atendimentos (data_hora_entrada, status);

CREATE INDEX ix_atendimentos_pet_data
    ON atendimentos (pet_id, data_hora_entrada);


-- Representa o caixa/fechamento de uma data específica.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE fechamentos_diarios (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_fechamento             DATE          NOT NULL,
    status                      VARCHAR(15)   NOT NULL DEFAULT 'ABERTO',
    valor_abertura_caixa        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_dinheiro_contado      DECIMAL(10,2) NULL,
    observacoes                 TEXT          NULL,
    aberto_por                  INT UNSIGNED  NULL,
    fechado_por                 INT UNSIGNED  NULL,
    criado_em                   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fechado_em                  DATETIME      NULL,

    CONSTRAINT uq_fechamentos_diarios_data
        UNIQUE (data_fechamento),

    CONSTRAINT ck_fechamentos_diarios_status
        CHECK (status IN ('ABERTO', 'FECHADO', 'REABERTO')),

    CONSTRAINT ck_fechamentos_abertura
        CHECK (valor_abertura_caixa >= 0),

    CONSTRAINT ck_fechamentos_contado
        CHECK (
            valor_dinheiro_contado IS NULL
            OR valor_dinheiro_contado >= 0
        ),

    CONSTRAINT fk_fechamentos_aberto_por
        FOREIGN KEY (aberto_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_fechamentos_fechado_por
        FOREIGN KEY (fechado_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE = InnoDB;


-- Guarda cada serviço/valor lançado dentro do fechamento diário.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE lancamentos_diarios (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fechamento_diario_id        INT UNSIGNED NOT NULL,
    pet_id                      INT UNSIGNED NOT NULL,
    atendimento_id              INT UNSIGNED NULL,
    servico_id                  INT UNSIGNED NOT NULL,
    pet_pacote_id               INT UNSIGNED NULL,
    creditos_pacote_usados      INT UNSIGNED NOT NULL DEFAULT 0,

    valor_servico               DECIMAL(10,2) NOT NULL,
    descricao                   VARCHAR(500)  NULL,

    criado_por                  INT UNSIGNED  NULL,
    criado_em                   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                              ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT ck_lancamentos_valor
        CHECK (valor_servico >= 0),

    CONSTRAINT ck_lancamentos_pacote
        CHECK (
            (pet_pacote_id IS NULL AND creditos_pacote_usados = 0)
            OR
            (pet_pacote_id IS NOT NULL)
        ),

    CONSTRAINT fk_lancamentos_fechamento
        FOREIGN KEY (fechamento_diario_id)
        REFERENCES fechamentos_diarios (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_lancamentos_pet
        FOREIGN KEY (pet_id)
        REFERENCES pets (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_lancamentos_atendimento
        FOREIGN KEY (atendimento_id)
        REFERENCES atendimentos (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_lancamentos_servico
        FOREIGN KEY (servico_id)
        REFERENCES servicos (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_lancamentos_pet_pacote
        FOREIGN KEY (pet_pacote_id)
        REFERENCES pet_pacotes (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_lancamentos_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE INDEX ix_lancamentos_fechamento
    ON lancamentos_diarios (fechamento_diario_id);

CREATE INDEX ix_lancamentos_pet
    ON lancamentos_diarios (pet_id);

CREATE INDEX ix_lancamentos_atendimento
    ON lancamentos_diarios (atendimento_id);

CREATE INDEX ix_lancamentos_servico
    ON lancamentos_diarios (servico_id);

CREATE INDEX ix_lancamentos_pacote
    ON lancamentos_diarios (pet_pacote_id);


-- Lista os tipos de adicional que podem ser aplicados ao serviço.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE tipos_adicional (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(100) NOT NULL,
    descricao           VARCHAR(255) NULL,
    ativo               BOOLEAN      NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_tipos_adicional_nome
        UNIQUE (nome)
) ENGINE = InnoDB;


-- Registra valores extras vinculados a um lançamento de serviço.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE lancamento_adicionais (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lancamento_diario_id    INT UNSIGNED NOT NULL,
    tipo_adicional_id       INT UNSIGNED NOT NULL,
    descricao               VARCHAR(500)  NULL,
    valor                   DECIMAL(10,2) NOT NULL,
    criado_por              INT UNSIGNED  NULL,
    criado_em               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT ck_lancamento_adicionais_valor
        CHECK (valor > 0),

    CONSTRAINT fk_adicionais_lancamento
        FOREIGN KEY (lancamento_diario_id)
        REFERENCES lancamentos_diarios (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_adicionais_tipo
        FOREIGN KEY (tipo_adicional_id)
        REFERENCES tipos_adicional (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_adicionais_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE INDEX ix_adicionais_lancamento
    ON lancamento_adicionais (lancamento_diario_id);

CREATE INDEX ix_adicionais_tipo
    ON lancamento_adicionais (tipo_adicional_id);


-- Lista as formas de pagamento aceitas pelo sistema.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE formas_pagamento (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(80) NOT NULL,
    ativo               BOOLEAN     NOT NULL DEFAULT TRUE,

    CONSTRAINT uq_formas_pagamento_nome
        UNIQUE (nome)
) ENGINE = InnoDB;


-- Registra os valores efetivamente recebidos e sua forma de pagamento.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE pagamentos (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fechamento_diario_id        INT UNSIGNED NOT NULL,
    cliente_id                  INT UNSIGNED NOT NULL,
    lancamento_diario_id        INT UNSIGNED NULL,
    pet_pacote_id               INT UNSIGNED NULL,
    forma_pagamento_id          INT UNSIGNED NOT NULL,

    data_hora_pagamento         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valor                       DECIMAL(10,2) NOT NULL,
    observacoes                 VARCHAR(500)  NULL,
    recebido_por                INT UNSIGNED  NULL,
    estornado                   BOOLEAN       NOT NULL DEFAULT FALSE,
    criado_em                   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT ck_pagamentos_valor
        CHECK (valor > 0),

    CONSTRAINT fk_pagamentos_fechamento
        FOREIGN KEY (fechamento_diario_id)
        REFERENCES fechamentos_diarios (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_pagamentos_cliente
        FOREIGN KEY (cliente_id)
        REFERENCES clientes (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_pagamentos_lancamento
        FOREIGN KEY (lancamento_diario_id)
        REFERENCES lancamentos_diarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_pagamentos_pet_pacote
        FOREIGN KEY (pet_pacote_id)
        REFERENCES pet_pacotes (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_pagamentos_forma
        FOREIGN KEY (forma_pagamento_id)
        REFERENCES formas_pagamento (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_pagamentos_recebido_por
        FOREIGN KEY (recebido_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE INDEX ix_pagamentos_fechamento
    ON pagamentos (fechamento_diario_id);

CREATE INDEX ix_pagamentos_cliente
    ON pagamentos (cliente_id);

CREATE INDEX ix_pagamentos_lancamento
    ON pagamentos (lancamento_diario_id);

CREATE INDEX ix_pagamentos_data
    ON pagamentos (data_hora_pagamento);


-- Controla entradas e saídas de crédito e débito vinculadas ao tutor.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE movimentacoes_cliente (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fechamento_diario_id        INT UNSIGNED NOT NULL,
    cliente_id                  INT UNSIGNED NOT NULL,
    lancamento_diario_id        INT UNSIGNED NULL,
    pagamento_id                INT UNSIGNED NULL,

    tipo_saldo                  VARCHAR(10)   NOT NULL,
    operacao                    VARCHAR(10)   NOT NULL,
    valor                       DECIMAL(10,2) NOT NULL,
    data_hora_movimentacao      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    descricao                   VARCHAR(500)  NOT NULL,
    criado_por                  INT UNSIGNED  NULL,

    CONSTRAINT ck_movimentacoes_tipo
        CHECK (tipo_saldo IN ('CREDITO', 'DEBITO')),

    CONSTRAINT ck_movimentacoes_operacao
        CHECK (operacao IN ('ENTRADA', 'SAIDA')),

    CONSTRAINT ck_movimentacoes_valor
        CHECK (valor > 0),

    CONSTRAINT fk_movimentacoes_fechamento
        FOREIGN KEY (fechamento_diario_id)
        REFERENCES fechamentos_diarios (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_movimentacoes_cliente
        FOREIGN KEY (cliente_id)
        REFERENCES clientes (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_movimentacoes_lancamento
        FOREIGN KEY (lancamento_diario_id)
        REFERENCES lancamentos_diarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_movimentacoes_pagamento
        FOREIGN KEY (pagamento_id)
        REFERENCES pagamentos (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_movimentacoes_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE INDEX ix_movimentacoes_fechamento
    ON movimentacoes_cliente (fechamento_diario_id);

CREATE INDEX ix_movimentacoes_cliente
    ON movimentacoes_cliente (cliente_id);

CREATE INDEX ix_movimentacoes_lancamento
    ON movimentacoes_cliente (lancamento_diario_id);


-- Cria a tabela "despesas_caixa" utilizada por este módulo do sistema.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE despesas_caixa (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fechamento_diario_id        INT UNSIGNED NOT NULL,
    forma_pagamento_id          INT UNSIGNED NULL,
    data_hora_despesa           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    descricao                   VARCHAR(255)  NOT NULL,
    categoria                   VARCHAR(100)  NULL,
    valor                       DECIMAL(10,2) NOT NULL,
    criado_por                  INT UNSIGNED  NULL,
    criado_em                   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT ck_despesas_valor
        CHECK (valor > 0),

    CONSTRAINT fk_despesas_fechamento
        FOREIGN KEY (fechamento_diario_id)
        REFERENCES fechamentos_diarios (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_despesas_forma
        FOREIGN KEY (forma_pagamento_id)
        REFERENCES formas_pagamento (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_despesas_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE INDEX ix_despesas_fechamento
    ON despesas_caixa (fechamento_diario_id);


-- Guarda o estado do fechamento de cada competência mensal.
-- As colunas abaixo definem os dados armazenados, enquanto PK/FK/constraints garantem integridade.
CREATE TABLE fechamentos_mensais (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ano                     SMALLINT UNSIGNED NOT NULL,
    mes                     TINYINT UNSIGNED  NOT NULL,
    status                  VARCHAR(15)       NOT NULL DEFAULT 'ABERTO',
    observacoes             TEXT              NULL,
    fechado_por             INT UNSIGNED      NULL,
    criado_em               DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fechado_em              DATETIME          NULL,

    CONSTRAINT uq_fechamentos_mensais_periodo
        UNIQUE (ano, mes),

    CONSTRAINT ck_fechamentos_mensais_mes
        CHECK (mes BETWEEN 1 AND 12),

    CONSTRAINT ck_fechamentos_mensais_status
        CHECK (status IN ('ABERTO', 'FECHADO', 'REABERTO')),

    CONSTRAINT fk_fechamentos_mensais_fechado_por
        FOREIGN KEY (fechado_por)
        REFERENCES usuarios (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE = InnoDB;

INSERT IGNORE INTO especies (nome)
VALUES
    ('Cão'),
    ('Gato');

INSERT IGNORE INTO categorias_servico (nome)
VALUES
    ('Banho e Tosa'),
    ('Creche'),
    ('Hotel'),
    ('Táxi'),
    ('Loja'),
    ('Veterinário'),
    ('Financeiro');

-- Insere dados iniciais necessários para que o sistema já possua opções básicas disponíveis.
INSERT INTO servicos (
    categoria_id,
    nome,
    descricao,
    aceita_pacote
)
SELECT
    id,
    'Banho',
    'Banho do pet',
    TRUE
FROM categorias_servico
WHERE nome = 'Banho e Tosa'

UNION ALL

SELECT
    id,
    'Banho e tosa',
    'Banho com tosa completa',
    TRUE
FROM categorias_servico
WHERE nome = 'Banho e Tosa'

UNION ALL

SELECT
    id,
    'Tosa higiênica',
    'Tosa higiênica do pet',
    TRUE
FROM categorias_servico
WHERE nome = 'Banho e Tosa'

UNION ALL

SELECT
    id,
    'Diária de creche',
    'Permanência na creche',
    TRUE
FROM categorias_servico
WHERE nome = 'Creche'

UNION ALL

SELECT
    id,
    'Diária de hotel',
    'Permanência no hotel',
    TRUE
FROM categorias_servico
WHERE nome = 'Hotel'

UNION ALL

SELECT
    id,
    'Táxi pet',
    'Transporte do pet',
    FALSE
FROM categorias_servico
WHERE nome = 'Táxi'

UNION ALL

SELECT
    id,
    'Venda de loja',
    'Venda de produto sem controle de estoque na V1',
    FALSE
FROM categorias_servico
WHERE nome = 'Loja'

UNION ALL

SELECT
    id,
    'Consulta veterinária',
    'Consulta veterinária',
    FALSE
FROM categorias_servico
WHERE nome = 'Veterinário'

UNION ALL

SELECT
    id,
    'Pagar débito',
    'Recebimento de débito antigo do tutor',
    FALSE
FROM categorias_servico
WHERE nome = 'Financeiro';

INSERT IGNORE INTO tipos_adicional (
    nome,
    descricao
)
VALUES
    (
        'Adicional por nós',
        'Valor adicional pela dificuldade e pelo tempo necessário para retirar nós'
    ),
    (
        'Adicional por comportamento difícil',
        'Valor adicional para pet bravo, agitado ou com manejo mais difícil'
    ),
    (
        'Outro adicional',
        'Outro motivo informado no lançamento'
    );

INSERT IGNORE INTO formas_pagamento (nome)
VALUES
    ('Dinheiro'),
    ('Pix'),
    ('Cartão');


-- Cria a view "vw_total_lancamento" para reunir dados calculados/relacionados e simplificar consultas do PHP.
CREATE OR REPLACE VIEW vw_total_lancamento AS
SELECT
    l.id                                                    AS lancamento_diario_id,
    l.fechamento_diario_id                                  AS fechamento_diario_id,
    l.pet_id                                                AS pet_id,
    l.atendimento_id                                        AS atendimento_id,
    l.servico_id                                            AS servico_id,
    l.pet_pacote_id                                         AS pet_pacote_id,
    l.creditos_pacote_usados                                AS creditos_pacote_usados,
    l.valor_servico                                         AS valor_servico,
    COALESCE(ad.total_adicionais, 0.00)                     AS total_adicionais,
    l.valor_servico
        + COALESCE(ad.total_adicionais, 0.00)               AS valor_total_lancamento
FROM lancamentos_diarios l
LEFT JOIN (
    SELECT
        lancamento_diario_id,
        SUM(valor)                                          AS total_adicionais
    FROM lancamento_adicionais
    GROUP BY
        lancamento_diario_id
) ad
    ON ad.lancamento_diario_id = l.id;


-- Cria a view "vw_lancamento_detalhado" para reunir dados calculados/relacionados e simplificar consultas do PHP.
CREATE OR REPLACE VIEW vw_lancamento_detalhado AS
SELECT
    l.id                                                    AS lancamento_diario_id,
    f.data_fechamento                                       AS data_fechamento,
    p.id                                                    AS pet_id,
    p.nome                                                  AS pet,
    c.id                                                    AS cliente_id,
    c.nome                                                  AS cliente,
    s.id                                                    AS servico_id,
    s.nome                                                  AS servico,
    l.descricao                                             AS descricao_servico,
    l.valor_servico                                         AS valor_servico,
    ta.id                                                   AS tipo_adicional_id,
    ta.nome                                                 AS adicional,
    la.descricao                                            AS descricao_adicional,
    la.valor                                                AS valor_adicional,
    vt.valor_total_lancamento                               AS valor_total_servico
FROM lancamentos_diarios l
JOIN fechamentos_diarios f
    ON f.id = l.fechamento_diario_id
JOIN pets p
    ON p.id = l.pet_id
JOIN clientes c
    ON c.id = p.cliente_id
JOIN servicos s
    ON s.id = l.servico_id
JOIN vw_total_lancamento vt
    ON vt.lancamento_diario_id = l.id
LEFT JOIN lancamento_adicionais la
    ON la.lancamento_diario_id = l.id
LEFT JOIN tipos_adicional ta
    ON ta.id = la.tipo_adicional_id;


-- Cria a view "vw_saldo_cliente" para reunir dados calculados/relacionados e simplificar consultas do PHP.
CREATE OR REPLACE VIEW vw_saldo_cliente AS
SELECT
    c.id                                                    AS cliente_id,
    c.nome                                                  AS cliente,

    COALESCE(
        SUM(
            CASE
                WHEN m.tipo_saldo = 'CREDITO'
                 AND m.operacao = 'ENTRADA'
                    THEN m.valor

                WHEN m.tipo_saldo = 'CREDITO'
                 AND m.operacao = 'SAIDA'
                    THEN -m.valor

                ELSE 0
            END
        ),
        0.00
    )                                                       AS saldo_credito,

    COALESCE(
        SUM(
            CASE
                WHEN m.tipo_saldo = 'DEBITO'
                 AND m.operacao = 'ENTRADA'
                    THEN m.valor

                WHEN m.tipo_saldo = 'DEBITO'
                 AND m.operacao = 'SAIDA'
                    THEN -m.valor

                ELSE 0
            END
        ),
        0.00
    )                                                       AS saldo_debito

FROM clientes c
LEFT JOIN movimentacoes_cliente m
    ON m.cliente_id = c.id
GROUP BY
    c.id,
    c.nome;


-- Cria a view "vw_saldo_pacotes" para reunir dados calculados/relacionados e simplificar consultas do PHP.
CREATE OR REPLACE VIEW vw_saldo_pacotes AS
SELECT
    pp.id                                                   AS pet_pacote_id,
    pp.pet_id                                               AS pet_id,
    p.nome                                                  AS pet,
    pa.nome                                                 AS pacote,
    pp.data_compra                                          AS data_compra,
    pp.data_validade                                        AS data_validade,
    pp.quantidade_creditos                                  AS creditos_totais,
    COALESCE(SUM(l.creditos_pacote_usados), 0)              AS creditos_utilizados,
    pp.quantidade_creditos
        - COALESCE(SUM(l.creditos_pacote_usados), 0)        AS creditos_restantes,
    pp.status                                               AS status
FROM pet_pacotes pp
JOIN pets p
    ON p.id = pp.pet_id
JOIN pacotes pa
    ON pa.id = pp.pacote_id
LEFT JOIN lancamentos_diarios l
    ON l.pet_pacote_id = pp.id
GROUP BY
    pp.id,
    pp.pet_id,
    p.nome,
    pa.nome,
    pp.data_compra,
    pp.data_validade,
    pp.quantidade_creditos,
    pp.status;


-- Cria a view "vw_fechamento_categoria_dia" para reunir dados calculados/relacionados e simplificar consultas do PHP.
CREATE OR REPLACE VIEW vw_fechamento_categoria_dia AS
SELECT
    f.id                                                    AS fechamento_diario_id,
    f.data_fechamento                                       AS data_fechamento,
    cs.id                                                   AS categoria_id,
    cs.nome                                                 AS categoria,
    COUNT(l.id)                                             AS quantidade_lancamentos,
    COUNT(DISTINCT l.pet_id)                                AS quantidade_pets,
    SUM(vt.valor_servico)                                   AS total_servicos_sem_adicionais,
    SUM(vt.total_adicionais)                                AS total_adicionais,
    SUM(vt.valor_total_lancamento)                          AS total_categoria
FROM fechamentos_diarios f
JOIN lancamentos_diarios l
    ON l.fechamento_diario_id = f.id
JOIN servicos s
    ON s.id = l.servico_id
JOIN categorias_servico cs
    ON cs.id = s.categoria_id
JOIN vw_total_lancamento vt
    ON vt.lancamento_diario_id = l.id
GROUP BY
    f.id,
    f.data_fechamento,
    cs.id,
    cs.nome;


-- Cria a view "vw_fechamento_servico_dia" para reunir dados calculados/relacionados e simplificar consultas do PHP.
CREATE OR REPLACE VIEW vw_fechamento_servico_dia AS
SELECT
    f.id                                                    AS fechamento_diario_id,
    f.data_fechamento                                       AS data_fechamento,
    s.id                                                    AS servico_id,
    s.nome                                                  AS servico,
    COUNT(l.id)                                             AS quantidade_lancamentos,
    COUNT(DISTINCT l.pet_id)                                AS quantidade_pets,
    SUM(vt.valor_servico)                                   AS total_servicos_sem_adicionais,
    SUM(vt.total_adicionais)                                AS total_adicionais,
    SUM(vt.valor_total_lancamento)                          AS total_servico
FROM fechamentos_diarios f
JOIN lancamentos_diarios l
    ON l.fechamento_diario_id = f.id
JOIN servicos s
    ON s.id = l.servico_id
JOIN vw_total_lancamento vt
    ON vt.lancamento_diario_id = l.id
GROUP BY
    f.id,
    f.data_fechamento,
    s.id,
    s.nome;


-- Cria a view "vw_fechamento_diario" para reunir dados calculados/relacionados e simplificar consultas do PHP.
CREATE OR REPLACE VIEW vw_fechamento_diario AS
SELECT
    f.id                                                    AS fechamento_diario_id,
    f.data_fechamento                                       AS data_fechamento,
    f.status                                                AS status,
    f.criado_em                                             AS aberto_em,
    f.fechado_em                                            AS fechado_em,

    COALESCE(lanc.quantidade_lancamentos, 0)                AS quantidade_lancamentos,
    COALESCE(lanc.quantidade_pets, 0)                       AS quantidade_pets,

    COALESCE(lanc.total_servicos_sem_adicionais, 0.00)      AS total_servicos_sem_adicionais,
    COALESCE(lanc.total_adicionais, 0.00)                   AS total_adicionais,
    COALESCE(lanc.total_comercializado, 0.00)               AS total_comercializado,

    COALESCE(pg.dinheiro, 0.00)                             AS dinheiro,
    COALESCE(pg.pix, 0.00)                                  AS pix,
    COALESCE(pg.cartao, 0.00)                               AS cartao,
    COALESCE(pg.total_recebido, 0.00)                       AS total_recebido,

    COALESCE(mov.credito_gerado, 0.00)                      AS credito_gerado,
    COALESCE(mov.credito_utilizado, 0.00)                   AS credito_utilizado,
    COALESCE(mov.debito_gerado, 0.00)                       AS debito_gerado,
    COALESCE(mov.debito_pago, 0.00)                         AS debito_pago,

    COALESCE(desp.total_despesas, 0.00)                     AS total_despesas,
    COALESCE(desp.despesas_em_dinheiro, 0.00)               AS despesas_em_dinheiro,

    f.valor_abertura_caixa                                  AS valor_abertura_caixa,

    f.valor_abertura_caixa
        + COALESCE(pg.dinheiro, 0.00)
        - COALESCE(desp.despesas_em_dinheiro, 0.00)         AS dinheiro_esperado,

    f.valor_dinheiro_contado                                AS dinheiro_contado,

    CASE
        WHEN f.valor_dinheiro_contado IS NULL
            THEN NULL
        ELSE
            f.valor_dinheiro_contado
            - (
                f.valor_abertura_caixa
                + COALESCE(pg.dinheiro, 0.00)
                - COALESCE(desp.despesas_em_dinheiro, 0.00)
            )
    END                                                     AS diferenca_dinheiro,

    COALESCE(pg.total_recebido, 0.00)
        - COALESCE(desp.total_despesas, 0.00)               AS saldo_financeiro_dia

FROM fechamentos_diarios f

LEFT JOIN (
    SELECT
        l.fechamento_diario_id,
        COUNT(l.id)                                         AS quantidade_lancamentos,
        COUNT(DISTINCT l.pet_id)                            AS quantidade_pets,
        SUM(vt.valor_servico)                               AS total_servicos_sem_adicionais,
        SUM(vt.total_adicionais)                            AS total_adicionais,
        SUM(vt.valor_total_lancamento)                      AS total_comercializado
    FROM lancamentos_diarios l
    JOIN vw_total_lancamento vt
        ON vt.lancamento_diario_id = l.id
    GROUP BY
        l.fechamento_diario_id
) lanc
    ON lanc.fechamento_diario_id = f.id

LEFT JOIN (
    SELECT
        p.fechamento_diario_id,

        SUM(
            CASE
                WHEN fp.nome = 'Dinheiro'
                    THEN p.valor
                ELSE 0
            END
        )                                                   AS dinheiro,

        SUM(
            CASE
                WHEN fp.nome = 'Pix'
                    THEN p.valor
                ELSE 0
            END
        )                                                   AS pix,

        SUM(
            CASE
                WHEN fp.nome IN ('Cartão', 'Cartão de débito', 'Cartão de crédito')
                    THEN p.valor
                ELSE 0
            END
        )                                                   AS cartao,

        SUM(p.valor)                                        AS total_recebido

    FROM pagamentos p
    JOIN formas_pagamento fp
        ON fp.id = p.forma_pagamento_id
    WHERE p.estornado = FALSE
    GROUP BY
        p.fechamento_diario_id
) pg
    ON pg.fechamento_diario_id = f.id

LEFT JOIN (
    SELECT
        fechamento_diario_id,

        SUM(
            CASE
                WHEN tipo_saldo = 'CREDITO'
                 AND operacao = 'ENTRADA'
                    THEN valor
                ELSE 0
            END
        )                                                   AS credito_gerado,

        SUM(
            CASE
                WHEN tipo_saldo = 'CREDITO'
                 AND operacao = 'SAIDA'
                    THEN valor
                ELSE 0
            END
        )                                                   AS credito_utilizado,

        SUM(
            CASE
                WHEN tipo_saldo = 'DEBITO'
                 AND operacao = 'ENTRADA'
                    THEN valor
                ELSE 0
            END
        )                                                   AS debito_gerado,

        SUM(
            CASE
                WHEN tipo_saldo = 'DEBITO'
                 AND operacao = 'SAIDA'
                    THEN valor
                ELSE 0
            END
        )                                                   AS debito_pago

    FROM movimentacoes_cliente
    GROUP BY
        fechamento_diario_id
) mov
    ON mov.fechamento_diario_id = f.id

LEFT JOIN (
    SELECT
        d.fechamento_diario_id,
        SUM(d.valor)                                        AS total_despesas,

        SUM(
            CASE
                WHEN fp.nome = 'Dinheiro'
                    THEN d.valor
                ELSE 0
            END
        )                                                   AS despesas_em_dinheiro

    FROM despesas_caixa d
    LEFT JOIN formas_pagamento fp
        ON fp.id = d.forma_pagamento_id
    GROUP BY
        d.fechamento_diario_id
) desp
    ON desp.fechamento_diario_id = f.id;


-- Cria a view "vw_fechamento_mensal" para reunir dados calculados/relacionados e simplificar consultas do PHP.
CREATE OR REPLACE VIEW vw_fechamento_mensal AS
SELECT
    YEAR(data_fechamento)                                   AS ano,
    MONTH(data_fechamento)                                  AS mes,

    SUM(quantidade_lancamentos)                             AS quantidade_lancamentos,
    SUM(quantidade_pets)                                    AS soma_pets_atendidos,

    SUM(total_servicos_sem_adicionais)                      AS total_servicos_sem_adicionais,
    SUM(total_adicionais)                                   AS total_adicionais,
    SUM(total_comercializado)                               AS total_comercializado,

    SUM(dinheiro)                                           AS dinheiro,
    SUM(pix)                                                AS pix,
    SUM(cartao)                                             AS cartao,
    SUM(total_recebido)                                     AS total_recebido,

    SUM(credito_gerado)                                     AS credito_gerado,
    SUM(credito_utilizado)                                  AS credito_utilizado,
    SUM(debito_gerado)                                      AS debito_gerado,
    SUM(debito_pago)                                        AS debito_pago,

    SUM(total_despesas)                                     AS total_despesas,
    SUM(saldo_financeiro_dia)                               AS saldo_financeiro_mes
FROM vw_fechamento_diario
GROUP BY
    YEAR(data_fechamento),
    MONTH(data_fechamento);


-- Cria a view "vw_fechamento_categoria_mes" para reunir dados calculados/relacionados e simplificar consultas do PHP.
CREATE OR REPLACE VIEW vw_fechamento_categoria_mes AS
SELECT
    YEAR(data_fechamento)                                   AS ano,
    MONTH(data_fechamento)                                  AS mes,
    categoria_id                                            AS categoria_id,
    categoria                                               AS categoria,
    SUM(quantidade_lancamentos)                             AS quantidade_lancamentos,
    SUM(quantidade_pets)                                    AS soma_pets_atendidos,
    SUM(total_servicos_sem_adicionais)                      AS total_servicos_sem_adicionais,
    SUM(total_adicionais)                                   AS total_adicionais,
    SUM(total_categoria)                                    AS total_categoria
FROM vw_fechamento_categoria_dia
GROUP BY
    YEAR(data_fechamento),
    MONTH(data_fechamento),
    categoria_id,
    categoria;


-- Cria a view "vw_historico_pet" para reunir dados calculados/relacionados e simplificar consultas do PHP.
CREATE OR REPLACE VIEW vw_historico_pet AS
SELECT
    p.id                                                    AS pet_id,
    p.nome                                                  AS pet,
    c.id                                                    AS cliente_id,
    c.nome                                                  AS cliente,
    f.data_fechamento                                       AS data_fechamento,
    l.id                                                    AS lancamento_diario_id,
    l.atendimento_id                                        AS atendimento_id,
    s.id                                                    AS servico_id,
    s.nome                                                  AS servico,
    l.descricao                                             AS descricao,
    l.valor_servico                                         AS valor_servico,
    vt.total_adicionais                                     AS total_adicionais,
    vt.valor_total_lancamento                               AS valor_total,

    CASE
        WHEN l.pet_pacote_id IS NULL
            THEN 'AVULSO'

        WHEN l.pet_pacote_id IS NOT NULL
         AND l.creditos_pacote_usados > 0
            THEN 'USO_PACOTE'

        ELSE 'COMPRA_PACOTE'
    END                                                     AS tipo_lancamento

FROM lancamentos_diarios l
JOIN fechamentos_diarios f
    ON f.id = l.fechamento_diario_id
JOIN pets p
    ON p.id = l.pet_id
JOIN clientes c
    ON c.id = p.cliente_id
JOIN servicos s
    ON s.id = l.servico_id
JOIN vw_total_lancamento vt
    ON vt.lancamento_diario_id = l.id;


-- Cria a view "vw_historico_cliente" para reunir dados calculados/relacionados e simplificar consultas do PHP.
CREATE OR REPLACE VIEW vw_historico_cliente AS
SELECT
    c.id                                                    AS cliente_id,
    c.nome                                                  AS cliente,
    p.id                                                    AS pet_id,
    p.nome                                                  AS pet,
    f.data_fechamento                                       AS data_fechamento,
    l.id                                                    AS lancamento_diario_id,
    s.nome                                                  AS servico,
    l.descricao                                             AS descricao,
    l.valor_servico                                         AS valor_servico,
    vt.total_adicionais                                     AS total_adicionais,
    vt.valor_total_lancamento                               AS valor_total
FROM clientes c
JOIN pets p
    ON p.cliente_id = c.id
JOIN lancamentos_diarios l
    ON l.pet_id = p.id
JOIN fechamentos_diarios f
    ON f.id = l.fechamento_diario_id
JOIN servicos s
    ON s.id = l.servico_id
JOIN vw_total_lancamento vt
    ON vt.lancamento_diario_id = l.id;


-- Cria a view "vw_gastos_cliente_mes" para reunir dados calculados/relacionados e simplificar consultas do PHP.
CREATE OR REPLACE VIEW vw_gastos_cliente_mes AS
SELECT
    c.id                                                    AS cliente_id,
    c.nome                                                  AS cliente,
    YEAR(f.data_fechamento)                                 AS ano,
    MONTH(f.data_fechamento)                                AS mes,
    SUM(vt.valor_total_lancamento)                          AS total_gasto
FROM clientes c
JOIN pets p
    ON p.cliente_id = c.id
JOIN lancamentos_diarios l
    ON l.pet_id = p.id
JOIN fechamentos_diarios f
    ON f.id = l.fechamento_diario_id
JOIN vw_total_lancamento vt
    ON vt.lancamento_diario_id = l.id
GROUP BY
    c.id,
    c.nome,
    YEAR(f.data_fechamento),
    MONTH(f.data_fechamento);

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
