create database gestao_cantina;

use gestao_cantina;

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

create table painel_adm (
    id_painel INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_carrinho INT,
    id_relatorio INT,
    CONSTRAINT fk_painel_carrinho FOREIGN KEY (id_carrinho) REFERENCES carrinho(id_carrinho),
    CONSTRAINT fk_painel_relatorio FOREIGN KEY (id_relatorio) REFERENCES relatorio_pagamento(id_relatorio)
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
