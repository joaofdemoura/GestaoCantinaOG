-- Para bancos criados com a versão anterior do banco.sql.
-- Instalações novas não precisam deste arquivo: o banco.sql já inclui estas mudanças.

use gestao_cantina;

create table sessao (
    token CHAR(64) NOT NULL PRIMARY KEY,
    tipo ENUM('admin', 'pai', 'filho') NOT NULL,
    cpf BIGINT NOT NULL,
    expira_em DATETIME NOT NULL
);

alter table pedido_item
    add vl_unitario DECIMAL(10,2) NOT NULL DEFAULT 0;

update pedido_item i
    join cardapio c on c.id_produto = i.id_produto
    set i.vl_unitario = c.vl_produto;

alter table extrato
    add id_pedido INT NULL,
    add CONSTRAINT fk_extrato_pedido FOREIGN KEY (id_pedido) REFERENCES pedido(id_pedido);
