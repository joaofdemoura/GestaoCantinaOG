CREATE DATABASE gestao_cantina;

USE gestao_cantina;

CREATE TABLE cardapio (
    id_produto INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome_produto VARCHAR(100) NOT NULL,
    ds_produto VARCHAR(400) NOT NULL,
    vl_produto DECIMAL(10,2) NOT NULL,
    imagem VARCHAR(255),
    hora TIME NOT NULL,
    sabor VARCHAR(100) NOT NULL,
    qt_produto INT NOT NULL
);

CREATE TABLE user_filho (
    cpf_filho BIGINT NOT NULL PRIMARY KEY,
    usuario_filho VARCHAR(100) NOT NULL,
    idade_filho INT NOT NULL,
    senha VARCHAR(255) NOT NULL
);

CREATE TABLE user_pais (
    cpf_pai BIGINT NOT NULL PRIMARY KEY,
    usuario_pais VARCHAR(100) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    cpf_filho BIGINT NOT NULL,
    CONSTRAINT fk_pais_filho
        FOREIGN KEY (cpf_filho)
        REFERENCES user_filho(cpf_filho)
);

CREATE TABLE user_admin (
    cpf_admin BIGINT NOT NULL PRIMARY KEY,
    usuario_adm VARCHAR(100) NOT NULL,
    senha VARCHAR(255) NOT NULL
);

CREATE TABLE pedido (
    id_pedido INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cpf_filho BIGINT NOT NULL,
    dt_pedido DATE NOT NULL,
    horario_recreio TIME NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    status_pedido VARCHAR(30) NOT NULL DEFAULT 'Pendente',
    CONSTRAINT fk_pedido_filho
        FOREIGN KEY (cpf_filho)
        REFERENCES user_filho(cpf_filho)
);

CREATE TABLE item_pedido (
    id_item INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_produto INT NOT NULL,
    quantidade INT NOT NULL,
    valor_unitario DECIMAL(10,2) NOT NULL,
    observacao VARCHAR(400),
    CONSTRAINT fk_item_pedido
        FOREIGN KEY (id_pedido)
        REFERENCES pedido(id_pedido),
    CONSTRAINT fk_item_produto
        FOREIGN KEY (id_produto)
        REFERENCES cardapio(id_produto)
);

CREATE TABLE pagamento (
    id_pagamento INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    forma_pagamento VARCHAR(50) NOT NULL,
    dt_pagamento DATE NOT NULL,
    status_pagamento VARCHAR(30) NOT NULL DEFAULT 'Pendente',
    CONSTRAINT fk_pagamento_pedido
        FOREIGN KEY (id_pedido)
        REFERENCES pedido(id_pedido)
);

CREATE TABLE extrato (
    id_extrato INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cpf_filho BIGINT NOT NULL,
    id_pedido INT NOT NULL,
    gasto DECIMAL(10,2) NOT NULL,
    horario TIME NOT NULL,
    dt_extrato DATE NOT NULL,
    CONSTRAINT fk_extrato_filho
        FOREIGN KEY (cpf_filho)
        REFERENCES user_filho(cpf_filho),
    CONSTRAINT fk_extrato_pedido
        FOREIGN KEY (id_pedido)
        REFERENCES pedido(id_pedido)
);

CREATE TABLE painel_adm (
    id_painel INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cpf_admin BIGINT NOT NULL,
    CONSTRAINT fk_painel_admin
        FOREIGN KEY (cpf_admin)
        REFERENCES user_admin(cpf_admin)
);

INSERT INTO user_filho (cpf_filho, usuario_filho, idade_filho, senha) VALUES
(11111111111, 'Joao', 15, '1234'),
(22222222222, 'Maria', 16, '1234');

INSERT INTO user_pais (cpf_pai, usuario_pais, senha, cpf_filho) VALUES
(33333333333, 'Carlos', '1234', 11111111111),
(44444444444, 'Ana', '1234', 22222222222);

INSERT INTO user_admin (cpf_admin, usuario_adm, senha) VALUES
(55555555555, 'Cantina', '1234');

INSERT INTO cardapio (nome_produto, ds_produto, vl_produto, imagem, hora, sabor, qt_produto) VALUES
('Sanduíche Natural', 'Sanduíche natural de frango com alface e tomate', 8.50, 'sanduiche.png', '09:00:00', 'Frango', 30),
('Suco de Laranja', 'Suco natural de laranja', 5.00, 'suco.png', '09:00:00', 'Laranja', 50),
('Bolo de Chocolate', 'Fatia de bolo de chocolate', 6.00, 'bolo.png', '15:30:00', 'Chocolate', 20);

INSERT INTO pedido (cpf_filho, dt_pedido, horario_recreio, valor_total, status_pedido) VALUES
(11111111111, CURDATE(), '09:00:00', 13.50, 'Pendente');

INSERT INTO item_pedido (id_pedido, id_produto, quantidade, valor_unitario, observacao) VALUES
(1, 1, 1, 8.50, 'Não colocar tomate'),
(1, 2, 1, 5.00, NULL);

INSERT INTO pagamento (id_pedido, forma_pagamento, dt_pagamento, status_pagamento) VALUES
(1, 'PIX', CURDATE(), 'Pago');

INSERT INTO extrato (cpf_filho, id_pedido, gasto, horario, dt_extrato) VALUES
(11111111111, 1, 13.50, '09:00:00', CURDATE());

SELECT * FROM cardapio;

SELECT nome_produto, vl_produto, qt_produto
FROM cardapio
WHERE qt_produto > 0;

SELECT *
FROM pedido
WHERE cpf_filho = 11111111111;

SELECT p.id_pedido, f.usuario_filho, c.nome_produto, i.quantidade, i.valor_unitario, i.observacao, p.horario_recreio, p.valor_total, p.status_pedido
FROM pedido p
JOIN user_filho f
    ON p.cpf_filho = f.cpf_filho
JOIN item_pedido i
    ON p.id_pedido = i.id_pedido
JOIN cardapio c
    ON i.id_produto = c.id_produto
WHERE p.id_pedido = 1;

SELECT e.id_extrato, e.id_pedido, e.gasto, e.horario, e.dt_extrato
FROM extrato e
WHERE e.cpf_filho = 11111111111
ORDER BY e.dt_extrato DESC;

SELECT p.id_pedido, f.usuario_filho, p.horario_recreio, p.valor_total, p.status_pedido
FROM pedido p
JOIN user_filho f
    ON p.cpf_filho = f.cpf_filho
WHERE p.status_pedido = 'Pendente';

SELECT * FROM cardapio
WHERE hora = '09:00:00'
AND qt_produto > 0;

SELECT * FROM cardapio
WHERE hora = '15:30:00'
AND qt_produto > 0;