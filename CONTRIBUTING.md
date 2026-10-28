# Contribuindo

Obrigado pelo interesse. O kit está em **beta público** (linha 2.x): issues
com defeitos, dúvidas de uso e sugestões são bem-vindas, e pull requests
também — para mudança grande, abra uma issue antes para combinar o caminho.
Ao participar, você concorda com o [código de conduta](CODE_OF_CONDUCT.md).
Ao enviar um pull request, você concorda em licenciar a contribuição sob a
[licença MIT](LICENSE) do projeto.

Regras curtas:

1. **Branches**: abra o pull request contra `desenvolvimento`; `sandbox` e
   `producao` são protegidas e só recebem fast-forward com o CI verde, na
   publicação de cada versão.
2. **Ambiente**: uma vez por clone, `scripts/preparar-dev` na raiz (cria o
   `.env` dos starters e o `.env` da raiz com as senhas de desenvolvimento do
   PostgreSQL e do Redis); depois, `docker compose up -d` (ver README). As
   portas ficam só em 127.0.0.1.
3. **Antes de abrir o PR**: `./vendor/bin/pint`,
   `./vendor/bin/phpstan analyse` (Larastan nível 8, sem baseline),
   `./vendor/bin/pest` e `npm run build` passando (tudo em container, rodando no starter —
   `starters/livewire` —, ver README).
4. **Convenções**: código em inglês, comentários e docs em português;
   nada hardcoded (config/platform.php e .env); toda string de UI passa por
   `__()` nos três idiomas; todo comportamento novo vem com teste.
5. **Segurança**: vulnerabilidades vão pelo canal privado (SECURITY.md),
   nunca por issue.
6. **Commits**: mensagem no imperativo, descrevendo o comportamento
   (`feat(admin): ...`, `fix(api): ...`).
