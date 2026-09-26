Cantina Digital - Sistema de Gestão e Controle Escolar

Um sistema completo de gestão de cantina escolar projetado para simplificar os pedidos dos alunos, dar controle financeiro aos pais e otimizar o atendimento dos administradores da cantina.

 Visão Geral da Solução

O projeto é dividido em duas plataformas integradas:

1. Painel Web (Administração): Voltado para os funcionários e administradores da cantina gerenciarem o cardápio, estoque, pedidos recebidos e relatórios.
2. Aplicativo Mobile (Pais & Alunos): Voltado para os alunos realizarem pedidos e para os pais controlarem saldos, limites de gastos e acompanharem extratos de consumo.

Tecnologias Utilizadas

Painel Web (ADM)
* IDE / Editor: Visual Studio Code (VS Code)
* Frontend: HTML5, CSS3, JavaScript 

Aplicativo Mobile (Pais & Alunos)
* IDE: Android Studio
* Linguagem: Java

Principais Funcionalidades

 Painel Web (Administradores)
* Gestão de Cardápio: Cadastro, edição e remoção de produtos e preços.
* Painel de Pedidos (Cozinha): Visualização em tempo real dos pedidos feitos pelos alunos organizados por horário de recreio.
* Relatórios Financeiros: Controle de vendas diárias, semanais e mensais.

 App Mobile - Módulo dos Pais
* Controle de Saldo: Adição de créditos na conta do filho.
* Gestão de Limite de Gastos: Definição de teto diário ou mensal de consumo.
* Extrato Detalhado: Consulta de histórico de compras feitas pelo aluno.


App Mobile - Módulo dos Alunos
* Fazer Pedido: Escolha de itens do cardápio e agendamento por horário de recreio.
* Resumo de Pagamento: Escolha da forma de pagamento e confirmação.
* Histórico de Pedidos: Acompanhamento do status do pedido.



Link do figma: https://www.figma.com/design/Ulsq3EWOsZpJsGhYIkGadJ/Untitled?node-id=0-1&t=RoI7FJgzX1CD5Ns4-1

## App e painel integrados por PHP

O backend é PHP 8.3+ com PDO MySQL e Sodium. O app continua em Java e o painel em HTML/CSS/JavaScript. Ambos usam o banco `gestao_cantina` do Laragon; o backend Node não é necessário.

| Pasta | Conteúdo |
|---|---|
| `app/` | Aplicativo Android, layouts e testes |
| `web/dist/` | Painel administrativo |
| `web/api/` | API PHP, configuração de exemplo e esquema inicial |
| `web/tools/` | Migração, cadastro local de responsáveis e testes |
| `android-emulator/` | Configuração do emulador leve |

### Preparar e executar

1. Abra a **raiz deste repositório** no Android Studio. Use JDK 17 e instale o SDK Android 35. O projeto usa Gradle 8.7 e Android Gradle Plugin 8.5.2.
2. Inicie o MySQL no Laragon. Para uma instalação nova, importe `web/api/banco.sql`; em um banco já utilizado, preserve seus dados e siga as migrações descritas em `web/README.md`.
3. Copie `web/api/config.local.example.php` para `web/api/config.local.php` e ajuste usuário, senha, porta e banco. A configuração local não entra no Git.
4. Na raiz, execute `powershell -ExecutionPolicy Bypass -File .\Configurar-Cantina.ps1`. O script prepara o esquema compartilhado, faz backup antes da migração e inicia o PHP.
5. Acesse **http://localhost:8000/dist/**. Em uma instalação com os dados de exemplo, o acesso administrativo é `admin` / `123456`.
6. Selecione um emulador ou celular USB no Android Studio e clique em **Run**. No emulador, o app usa `http://10.0.2.2:8000/api/android`; em celular USB, `Iniciar-Cantina.ps1` configura `adb reverse tcp:8000 tcp:8000` para os dispositivos autorizados.

Para iniciar novamente sem migrar, use `Iniciar-Cantina.ps1`. O PHP é localizado no PATH ou na instalação do Laragon; `CANTINA_PHP` permite indicar outro executável. As portas usadas são 8000 (HTTP/PHP) e 3306 (MySQL por padrão).

`Abrir-App-Cantina.cmd` inicia o PHP, abre o **Cantina Leve API 36** e instala/abre o último APK compilado. Primeiro instale a imagem **Android 16/API 36 Google Play x86_64** no SDK Manager. O perfil é criado automaticamente se ainda não existir, com 2,5 GB de RAM, 2 núcleos e tela 720×1280. Deixe a janela aberta até terminar a inicialização. Para testar alterações de código, compile novamente pelo Run. O emulador usa a opção de pré-autorização ADB somente no aparelho virtual local; celulares físicos mantêm a autorização normal.

### Fluxos compartilhados

- Produtos e disponibilidade definidos no painel aparecem no Android. Pedidos do app aparecem no painel, que consulta atualizações a cada 15 segundos.
- Status de preparo, entrega e cancelamento são compartilhados. Cancelamentos devolvem o saldo efetivamente debitado e repõem estoque quando controlado.
- Cadastro do aluno inclui **turma**. O login do responsável exige **CPF do responsável, CPF do filho e senha**; a API confere o vínculo e abre o filho informado. Contas com vários filhos mantêm a opção **Trocar aluno**.
- `Cadastrar-Responsavel.ps1` cadastra uma conta ou vincula mais um filho com a senha atual do responsável. O aluno deve estar cadastrado previamente; vínculos existentes não são transferidos pelo script.
- A área dos pais permite créditos locais de teste, limites diário/mensal e consulta de extrato. Pix/cartão ficam pendentes: não há cobrança bancária automática.
- A tela inicial não tem ícones nem botão Voltar. As demais telas mantêm a navegação de retorno.

A migração de um banco Android anterior é opcional: `php web/tools/migrate-android.php CAMINHO_DO_BACKEND_ANTIGO/.local.json`. Ela preserva CPFs e vínculos, mapeia produtos/pedidos e não repete registros já importados. Backups privados são salvos fora do projeto, em `%LOCALAPPDATA%/CantinaBackups`. Não copie senhas, dumps reais ou arquivos `.local.json` para o repositório.

### Verificação

- API/painel/app: com o servidor ativo, `php web/tools/integration-test.php` cria seus próprios registros e os remove ao final. Para testar outro servidor, defina `CANTINA_API_URL` (por exemplo, `http://127.0.0.1:8001/api`).
- Compilação: `gradlew.bat :app:assembleDebug :app:lintDebug`.
- Telas Android: prepare a conta com `php web/tools/android-fixture.php`, execute `gradlew.bat :app:connectedDebugAndroidTest -Pandroid.testInstrumentationRunnerArguments.class=com.example.cantina.NovasTelasTest,com.example.cantina.VoltarTurmaTest` e remova a conta com `php web/tools/android-fixture.php --cleanup` ao terminar.

Resultados registrados em `VERIFICACAO.md`. Não execute o teste legado completo `FluxoTest` contra dados de uso diário: ele contém uma compra de teste. Os arquivos ZIP e SQL antigos mantidos na raiz são referências; o código integrado está nas pastas acima.

## Uso de inteligência artificial

Veja [IA.md](docs/IA.md) para entender como a inteligência artificial foi utilizada no desenvolvimento do aplicativo, do painel web e da integração PHP.
