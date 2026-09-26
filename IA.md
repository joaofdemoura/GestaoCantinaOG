# Uso de Inteligência Artificial no projeto

Este documento explica como a inteligência artificial foi utilizada no desenvolvimento do **Cantina Digital / GestaoCantinaOG**, que reúne um aplicativo Android para alunos e responsáveis e um painel web para a administração da cantina.

## Ferramenta utilizada

Foi utilizado o **Codex, da OpenAI**, como assistente de programação. A ferramenta recebeu solicitações em linguagem natural, analisou os arquivos do projeto e ajudou a implementar funcionalidades, corrigir problemas e verificar o funcionamento do sistema.

A participação da IA incluiu a geração e a edição de código, além de explicações sobre configuração e testes. As alterações partiram das telas, do código existente e dos requisitos informados pelo responsável pelo projeto.

## Em quais partes usamos IA

| Parte do projeto | Como a IA foi utilizada |
| --- | --- |
| Aplicativo Android | Implementação e ajustes das classes Java que controlam as telas, dos layouts XML e da navegação entre as páginas. |
| Login e cadastro | Inclusão do campo **turma** para o aluno e do **CPF do filho** no login do responsável, com validação do vínculo entre ambos na API. |
| Interface | Ajustes solicitados nos botões de retorno, remoção dos ícones da primeira tela e remoção do botão **Voltar** somente da tela inicial. |
| Integração com o painel web | Implementação da comunicação HTTP do aplicativo com a API PHP e ajustes para que o painel e o app utilizem o mesmo banco MySQL. |
| Regras do sistema | Implementação e correção de regras de saldo, limites diário e mensal, pedidos, estoque, cancelamentos e consulta de extrato. |
| Banco de dados | Criação e ajuste de scripts SQL e de migração em PHP para adaptar o banco compartilhado e importar dados anteriores sem repetir registros. |
| Ambiente de desenvolvimento | Diagnóstico de problemas de compilação e de inicialização do emulador, ajustes de memória e criação de scripts para iniciar o PHP e o ambiente de testes. |
| Verificação e documentação | Criação e execução de testes, análise dos resultados e elaboração de instruções de instalação e uso. Este documento também foi redigido com auxílio de IA. |

## Como foi o processo

As solicitações foram feitas durante o desenvolvimento, por exemplo: adicionar campos nas telas, corrigir erros apresentados no Android Studio e conectar o aplicativo ao painel web usando PHP.

A IA analisou o código e as mensagens de erro, propôs soluções e realizou alterações nos arquivos. Para verificar essas alterações, foram executadas compilações, verificações de código, testes da API e testes no emulador. As observações e os novos pedidos do responsável orientaram os ajustes seguintes.

O trabalho incluiu mudanças em Java, XML, PHP, SQL, HTML, CSS, JavaScript e scripts de execução. A arquitetura resultante mantém o aplicativo em Java, o painel em HTML/CSS/JavaScript e o backend em PHP com MySQL no Laragon.

## A IA faz parte do funcionamento do aplicativo?

A IA foi utilizada como ferramenta de desenvolvimento. A versão documentada do sistema não integra um chatbot nem faz chamadas a um serviço de IA para realizar login, calcular saldo, registrar pedidos ou apresentar os dados do painel.

Essas operações são executadas pelo código do aplicativo e da API PHP, utilizando o banco de dados compartilhado. Não é necessário fornecer uma chave de API de IA para executar o projeto.

## Verificações e limites

Na integração registrada em **26/09/2026**, passaram 50 verificações da API PHP, 4 testes Android e os comandos de compilação e análise `assembleDebug` e `lintDebug`. Também foi conferido o acesso à API pelo emulador. Os resultados e suas limitações estão descritos em [VERIFICACAO.md](VERIFICACAO.md).

Esses resultados se referem ao ambiente e aos fluxos testados. O código produzido com auxílio de IA precisa continuar sendo revisado e validado a cada mudança, especialmente nas regras de acesso, saldo e pedidos. A responsabilidade pela avaliação e pela entrega do projeto permanece com seus desenvolvedores.

A adição de saldo é destinada a créditos locais de teste, e Pix/cartão ficam pendentes; não foi implementado processamento bancário real. Também não foi validado o funcionamento em todos os modelos de celular nem uma publicação em servidor externo.

## Arquivos relacionados

- [README.md](README.md): visão geral, instalação e execução do projeto.
- [app/](app/): código e telas do aplicativo Android.
- [web/README.md](web/README.md): instruções do painel e da API PHP.
- [web/api/](web/api/): backend PHP e arquivos SQL.
- [web/tools/](web/tools/): migração e testes de integração.
- [VERIFICACAO.md](VERIFICACAO.md): registro das verificações realizadas.
