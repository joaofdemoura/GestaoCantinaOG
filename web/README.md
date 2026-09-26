# Recreio — painel da cantina

Painel de administração (web) e API em PHP puro sobre o banco MySQL `gestao_cantina`. O app Android usa a mesma API e o mesmo banco.

## Como rodar

1. Abra o Laragon e clique em **Start All** (liga o MySQL).
2. Banco:
   - instalação nova: importe `api/banco.sql`;
   - banco criado com uma versão anterior do `banco.sql`: importe, em ordem, os `api/atualizacao-*.sql` que ainda não foram aplicados.
3. Execute `php tools/migrate-android.php` para preparar as tabelas compartilhadas.
4. Na pasta do projeto:
   ```
   C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe -S 127.0.0.1:8000 router.php
   ```
5. Acesse http://localhost:8000/dist/ e entre com `admin` / `123456`.

Pelo Apache do Laragon também funciona: o `api/.htaccess` direciona as rotas. Copie `api/config.local.example.php` para `api/config.local.php` e ajuste a conexão. Esse arquivo local é ignorado pelo Git. Também é possível usar as variáveis `CANTINA_DB_HOST`, `CANTINA_DB_PORT`, `CANTINA_DB_NAME`, `CANTINA_DB_USER` e `CANTINA_DB_PASSWORD`.

## Arquivos

- `dist/`: painel (HTML, CSS e JS). Busca os dados na API e se atualiza a cada 15 segundos.
- `api/index.php`: rotas da API.
- `api/db.php`: conexão PDO e conversões (reais ↔ centavos, horários).
- `api/banco.sql`: banco original com a ordem corrigida, as adaptações e dados de exemplo.
- `router.php`: roteador para o servidor embutido do PHP.

## Usuários de exemplo (senha 123456)

| Tipo | Login |
|---|---|
| admin | `admin` |
| pai | CPF `11111111101` a `11111111105` (Renato Martins, `11111111102`, tem dois filhos) |
| filho | CPF `22222222201` a `22222222206` |

## API

Todas as rotas, exceto `login`, exigem o cabeçalho `Authorization: Bearer <token>`. Valores em dinheiro vão em **centavos** (`500` = R$ 5,00).

| Método | Rota | Quem | O que faz |
|---|---|---|---|
| POST | `/api/login` | todos | `{"type": "admin"\|"pai"\|"filho", "login", "password"}` → `token` |
| POST | `/api/logout` | todos | Encerra a sessão |
| GET | `/api/me` | todos | Dados do usuário e, para pai/filho, os alunos com saldo |
| GET | `/api/produtos` | todos | Cardápio (pai/filho recebem só os disponíveis) |
| POST | `/api/produtos` | admin | Cria item |
| PUT | `/api/produtos/{id}` | admin | Edita item |
| PATCH | `/api/produtos/{id}` | admin | `{"available": true\|false}` |
| DELETE | `/api/produtos/{id}` | admin | Exclui o item (veja abaixo) |
| GET | `/api/alunos` | todos | Admin vê todos; pai, os filhos; filho, ele mesmo |
| GET | `/api/alunos/{cpf}/extrato` | todos | Gastos do aluno |
| GET | `/api/pedidos?data=&intervalo=` | todos | Pedidos, limitados aos alunos permitidos |
| POST | `/api/pedidos` | pai, filho | `{"student", "interval": "09:00"\|"15:30", "items": [{"id", "qty"}]}` |
| PATCH | `/api/pedidos/{id}` | admin | `{"status"}`: Em preparo → Pronto → Entregue, ou `Cancelado` |
| GET | `/api/estado` | admin | Produtos, alunos e pedidos de uma vez (usado pelo painel) |

Ao criar um pedido, a API calcula o total pelos preços do banco e confere os limites. Pedidos com Saldo descontam a carteira e gravam em `extrato`, tudo numa única transação. Pix/cartão ficam pendentes, sem cobrança automática. Itens esgotados e saldo insuficiente são recusados.

**Cancelamento:** só pedidos em preparo ou prontos. O valor devolvido é o que foi descontado daquele pedido no `extrato`, e o estorno entra no extrato como valor negativo (`"type": "estorno"`).

**Exclusão de item:** se o item nunca foi pedido, é apagado do banco. Se já foi, recebe `excluido = 1`: some do cardápio e do app, mas continua nos pedidos antigos.

Bancos criados antes dessas mudanças precisam de `api/atualizacao-02.sql`.

## Ainda não feito

- Cadastro e edição de alunos e pais e recarga de saldo pelo painel.
- Upload da imagem do produto (a coluna `imagem` existe, mas o painel não a edita).
- As tabelas `carrinho`, `relatorio_pagamento` e `painel_adm` não são usadas.
- HTTPS para uma futura publicação fora do ambiente local.

## Integração Android via PHP

Backend ativo: **somente PHP 8.3 + PDO MySQL do Laragon (3306)**. O Android Java continua nativo.

- Painel: http://localhost:8000/dist/
- API web: http://127.0.0.1:8000/api/
- Adaptador Android: http://127.0.0.1:8000/api/android/
- Emulador: http://10.0.2.2:8000/api/android/
- Celular USB: execute `adb reverse tcp:8000 tcp:8000`.

`Iniciar-PHP.ps1` inicia a API e configura os dispositivos USB. O MySQL deve estar iniciado pelo Laragon. O app não depende mais de Node.js, Express ou do MariaDB portátil.

Painel e Android usam o mesmo banco para produtos, disponibilidade, alunos, turma, saldo, limites e pedidos. Os limites são configurados na área dos pais no Android. O administrador vê os pedidos criados no Android e suas mudanças de status aparecem no extrato do app. Cancelar devolve saldo apenas quando houve débito de saldo e repõe o estoque controlado. Pix/cartão continuam pendentes; não existe cobrança bancária. Créditos adicionados pelos pais são créditos locais de teste.

Responsáveis com vários filhos podem usar **Trocar aluno**. O servidor sempre valida o vínculo. Turma é obrigatória no cadastro e opcional no login; alteração só ocorre depois de verificar a senha.

Migração de esquema: `php tools/migrate-android.php`. Importação opcional do banco anterior: acrescente o caminho local para o antigo `backend/.local.json`. A importação preserva os registros existentes, mapeia IDs de produtos/pedidos e não duplica registros já importados. Backups SQL privados ficam em `%LOCALAPPDATA%/CantinaBackups`, fora da pasta servida pelo PHP. Em conflito de CPF, a importação para sem substituir a conta.

As senhas scrypt anteriores são verificadas pelo Sodium do PHP e atualizadas para `password_hash` no login. Os usuários precisam entrar novamente. O carrinho local antigo é limpo uma única vez porque os IDs de produtos mudam durante a unificação.

Teste integrado, com registros próprios e limpeza ao final: `php tools/integration-test.php`. Para os testes Android de login dos pais, execute `php tools/android-fixture.php` antes e `php tools/android-fixture.php --cleanup` depois.
Cadastro local de responsáveis: o script Android `Cadastrar-Responsavel.ps1` chama `tools/cadastrar-responsavel.php` via PHP, sem expor a senha nos argumentos do processo.

## Login do responsável

No Android, informe o CPF do responsável, o CPF do filho e a senha do responsável. O filho precisa estar previamente vinculado à conta; o login não cria nem transfere vínculos. Se houver vários filhos, o painel abre com o filho informado e mantém a opção Trocar aluno.

A rota `POST /api/android/responsavel/login` recebe `cpf`, `cpfFilho` e `senha` como texto. O CPF do filho exige 11 dígitos. Um filho sem vínculo não recebe sessão.