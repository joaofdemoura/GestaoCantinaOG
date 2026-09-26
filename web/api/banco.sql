-- Banco gestao_cantina (Bd_OG.sql) com a ordem das tabelas corrigida
-- e as adaptações para o painel no final do arquivo.

create database if not exists gestao_cantina character set utf8mb4 collate utf8mb4_unicode_ci;

use gestao_cantina;

-- ---------------------------------------------------------------------------
-- Tabelas originais (mesmas colunas, só a ordem de criação mudou)
-- ---------------------------------------------------------------------------

create table cardapio (
    id_produto INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome_produto VARCHAR(100) NOT NULL,
    ds_produto VARCHAR(400) NOT NULL,
    vl_produto DECIMAL(10,2) NOT NULL,
    imagem VARCHAR(255),
    hora TIME NOT NULL,
    sabor VARCHAR(100) NOT NULL
);

create table user_filho (
    cpf_filho BIGINT NOT NULL PRIMARY KEY,
    usuario_filho VARCHAR(100) NOT NULL,
    idade_filho INT NOT NULL,
    senha VARCHAR(255) NOT NULL
);

create table user_pais (
    cpf_pai BIGINT NOT NULL PRIMARY KEY,
    usuario_pais VARCHAR(100) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    cpf_filho BIGINT NOT NULL,
    CONSTRAINT fk_pais_filho FOREIGN KEY (cpf_filho) REFERENCES user_filho(cpf_filho)
);

create table relatorio_pagamento (
    id_relatorio INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    forma_pagamento VARCHAR(50) NOT NULL,
    dt_relatorio DATE NOT NULL
);

create table carrinho (
    id_carrinho INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_produto INT NOT NULL,
    cpf_filho BIGINT NOT NULL,
    CONSTRAINT fk_carrinho_produto FOREIGN KEY (id_produto) REFERENCES cardapio(id_produto),
    CONSTRAINT fk_carrinho_filho FOREIGN KEY (cpf_filho) REFERENCES user_filho(cpf_filho)
);

create table painel_adm (
    id_painel INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_carrinho INT,
    id_relatorio INT,
    CONSTRAINT fk_painel_carrinho FOREIGN KEY (id_carrinho) REFERENCES carrinho(id_carrinho),
    CONSTRAINT fk_painel_relatorio FOREIGN KEY (id_relatorio) REFERENCES relatorio_pagamento(id_relatorio)
);

create table extrato (
    id_extrato INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    gasto DECIMAL(10,2) NOT NULL,
    horario TIME NOT NULL,
    dt_extrato DATE NOT NULL,
    cpf_filho BIGINT NOT NULL,
    CONSTRAINT fk_extrato_filho FOREIGN KEY (cpf_filho) REFERENCES user_filho(cpf_filho)
);

create table user_admin (
    cpf_admin BIGINT NOT NULL PRIMARY KEY,
    usuario_adm VARCHAR(100) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    id_extrato INT,
    id_painel INT,
    CONSTRAINT fk_admin_extrato FOREIGN KEY (id_extrato) REFERENCES extrato(id_extrato),
    CONSTRAINT fk_admin_painel FOREIGN KEY (id_painel) REFERENCES painel_adm(id_painel)
);

-- ---------------------------------------------------------------------------
-- Adaptações para o painel
-- ---------------------------------------------------------------------------

alter table cardapio
    add categoria VARCHAR(50) NOT NULL DEFAULT 'Outros',
    add disponivel TINYINT(1) NOT NULL DEFAULT 1,
    add excluido TINYINT(1) NOT NULL DEFAULT 0;

-- cpf_pai no filho permite vários filhos por responsável (irmãos).
-- user_pais.cpf_filho continua existindo, mas passa a ser opcional.
alter table user_filho
    add turma VARCHAR(20) NOT NULL DEFAULT '',
    add restricao_alimentar VARCHAR(200) NOT NULL DEFAULT '',
    add saldo DECIMAL(10,2) NOT NULL DEFAULT 0,
    add cpf_pai BIGINT NULL;

alter table user_pais
    modify cpf_filho BIGINT NULL;

alter table user_filho
    add CONSTRAINT fk_filho_pai FOREIGN KEY (cpf_pai) REFERENCES user_pais(cpf_pai);

create table pedido (
    id_pedido INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cpf_filho BIGINT NOT NULL,
    dt_pedido DATE NOT NULL,
    horario_retirada TIME NOT NULL,
    status ENUM('Em preparo', 'Pronto', 'Entregue', 'Cancelado') NOT NULL DEFAULT 'Em preparo',
    forma_pagamento VARCHAR(50),
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pedido_filho FOREIGN KEY (cpf_filho) REFERENCES user_filho(cpf_filho)
);

create table pedido_item (
    id_item INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_produto INT NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    vl_unitario DECIMAL(10,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_item_pedido FOREIGN KEY (id_pedido) REFERENCES pedido(id_pedido) ON DELETE CASCADE,
    CONSTRAINT fk_item_produto FOREIGN KEY (id_produto) REFERENCES cardapio(id_produto)
);

alter table extrato
    add id_pedido INT NULL,
    add CONSTRAINT fk_extrato_pedido FOREIGN KEY (id_pedido) REFERENCES pedido(id_pedido);

create table sessao (
    token CHAR(64) NOT NULL PRIMARY KEY,
    tipo ENUM('admin', 'pai', 'filho') NOT NULL,
    cpf BIGINT NOT NULL,
    expira_em DATETIME NOT NULL
);

-- ---------------------------------------------------------------------------
-- Dados de exemplo (senha de todos os usuários: 123456)
-- ---------------------------------------------------------------------------

insert into cardapio (id_produto, nome_produto, ds_produto, vl_produto, hora, sabor, categoria, disponivel) values
    (1, 'Pão de queijo',     'Porção com 3 unidades',     5.00,  '09:00', 'Queijo',    'Salgados', 1),
    (2, 'Sanduíche natural', 'Pão integral com recheio',  12.00, '09:00', 'Frango',    'Lanches',  1),
    (3, 'Suco de laranja',   'Copo de 300 ml',            6.00,  '09:00', 'Laranja',   'Bebidas',  1),
    (4, 'Bolo de chocolate', 'Fatia',                     7.00,  '15:30', 'Chocolate', 'Doces',    1),
    (5, 'Salgado assado',    'Unidade',                   8.00,  '15:30', 'Frango',    'Salgados', 1),
    (6, 'Água mineral',      'Garrafa de 500 ml',         3.50,  '09:00', 'Natural',   'Bebidas',  0);

insert into user_pais (cpf_pai, usuario_pais, senha, cpf_filho) values
    (11111111101, 'Carla Oliveira', '$2y$10$Ulph6miXhHD7vaogB9YRr.dhTNwt8YoP2ewUdN.iEyne2ddHoWUTe', NULL),
    (11111111102, 'Renato Martins', '$2y$10$Ulph6miXhHD7vaogB9YRr.dhTNwt8YoP2ewUdN.iEyne2ddHoWUTe', NULL),
    (11111111103, 'Paula Almeida',  '$2y$10$Ulph6miXhHD7vaogB9YRr.dhTNwt8YoP2ewUdN.iEyne2ddHoWUTe', NULL),
    (11111111104, 'Marcos Santos',  '$2y$10$Ulph6miXhHD7vaogB9YRr.dhTNwt8YoP2ewUdN.iEyne2ddHoWUTe', NULL),
    (11111111105, 'Fernanda Costa', '$2y$10$Ulph6miXhHD7vaogB9YRr.dhTNwt8YoP2ewUdN.iEyne2ddHoWUTe', NULL);

insert into user_filho (cpf_filho, usuario_filho, idade_filho, senha, turma, restricao_alimentar, saldo, cpf_pai) values
    (22222222201, 'Ana Oliveira',    13, '$2y$10$Ulph6miXhHD7vaogB9YRr.dhTNwt8YoP2ewUdN.iEyne2ddHoWUTe', '8º ano A',  '',                   24.50, 11111111101),
    (22222222202, 'Lucas Martins',   14, '$2y$10$Ulph6miXhHD7vaogB9YRr.dhTNwt8YoP2ewUdN.iEyne2ddHoWUTe', '9º ano B',  'Alergia a amendoim', 18.00, 11111111102),
    (22222222203, 'Sofia Almeida',   12, '$2y$10$Ulph6miXhHD7vaogB9YRr.dhTNwt8YoP2ewUdN.iEyne2ddHoWUTe', '7º ano A',  '',                   32.00, 11111111103),
    (22222222204, 'Pedro Santos',    15, '$2y$10$Ulph6miXhHD7vaogB9YRr.dhTNwt8YoP2ewUdN.iEyne2ddHoWUTe', '1º ano EM', '',                    9.50, 11111111104),
    (22222222205, 'Júlia Costa',     13, '$2y$10$Ulph6miXhHD7vaogB9YRr.dhTNwt8YoP2ewUdN.iEyne2ddHoWUTe', '8º ano B',  '',                   41.00, 11111111105),
    (22222222206, 'Gabriel Martins', 11, '$2y$10$Ulph6miXhHD7vaogB9YRr.dhTNwt8YoP2ewUdN.iEyne2ddHoWUTe', '6º ano A',  '',                   12.50, 11111111102);

update user_pais p
    join user_filho f on f.cpf_pai = p.cpf_pai
    set p.cpf_filho = f.cpf_filho
    where p.cpf_filho is null;

insert into user_admin (cpf_admin, usuario_adm, senha) values
    (33333333301, 'admin', '$2y$10$Ulph6miXhHD7vaogB9YRr.dhTNwt8YoP2ewUdN.iEyne2ddHoWUTe');

insert into pedido (id_pedido, cpf_filho, dt_pedido, horario_retirada, status) values
    (101, 22222222201, CURDATE(), '09:00', 'Pronto'),
    (102, 22222222202, CURDATE(), '09:00', 'Em preparo'),
    (103, 22222222203, CURDATE(), '09:00', 'Pronto'),
    (104, 22222222205, CURDATE(), '15:30', 'Em preparo');

insert into pedido_item (id_pedido, id_produto, quantidade, vl_unitario) values
    (101, 1, 2, 5.00),
    (101, 3, 1, 6.00),
    (102, 2, 1, 12.00),
    (103, 4, 1, 7.00),
    (103, 3, 1, 6.00),
    (104, 5, 1, 8.00);
