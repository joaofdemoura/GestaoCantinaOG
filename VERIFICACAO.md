# Verificação da integração PHP — 26/09/2026

- 50 verificações PHP passaram: cadastro/turma, login por perfil, CPF do filho e vínculo, ausência de sessão em vínculo inválido, múltiplos filhos, produtos do painel, pedidos do app, observações, status, cancelamento, saldo, estoque, limites e idempotência.
- 4 testes Android passaram no Cantina Leve API 36: escolha de perfil, proteção do painel, login do responsável com CPF do filho, saldo/formulários e navegação/validação de turma.
- `assembleDebug` e `lintDebug` passaram. Há avisos preexistentes de manutenção e de compatibilidade entre AGP 8.5.2 e compileSdk 35; nenhum foi ocultado.
- A remoção final do botão Voltar na tela inicial foi compilada e conferida no emulador. O APK atualizado foi instalado e abriu com sucesso.
- O acesso à API PHP foi verificado no emulador com login e consulta de cardápio. A API anterior em Node estava desligada.
- A migração foi repetida e importou zero registros duplicados. Os dados antigos foram mantidos, com backups fora da pasta pública.

Os testes PHP removem seus registros temporários; a conta temporária dos testes Android também foi removida. As verificações não processam cobranças reais. Não foram testados todos os modelos de celular físico nem uma publicação em servidor externo.