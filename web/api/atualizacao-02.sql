-- Para bancos criados antes desta versão. Instalações novas usam só o banco.sql.

use gestao_cantina;

alter table cardapio
    add excluido TINYINT(1) NOT NULL DEFAULT 0;
