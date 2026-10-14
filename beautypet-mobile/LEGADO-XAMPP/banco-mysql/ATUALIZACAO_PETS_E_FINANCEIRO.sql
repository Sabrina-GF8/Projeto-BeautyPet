-- BEAUTY PET - ATUALIZAÇÃO INCREMENTAL
-- Execute este arquivo no phpMyAdmin se o banco WEB + Mobile já foi importado.
-- Ele não apaga nem recria dados existentes.

USE beauty_pet;

-- Garante todas as categorias exibidas pelo formulário de Novo Gasto.
INSERT INTO categorias_gasto_mobile (nome, ativo)
VALUES
    ('Higiene', TRUE),
    ('Alimentação', TRUE),
    ('Saúde', TRUE),
    ('Lazer', TRUE),
    ('Outros', TRUE)
ON DUPLICATE KEY UPDATE ativo = TRUE;

-- O cadastro simplificado de pet não precisa de tabela nova:
-- ele grava diretamente em pets e usa o cliente_id da sessão do tutor.
