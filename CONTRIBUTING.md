# Contribuindo

Obrigado pelo interesse. Regras curtas:

1. **Branches**: trabalho em `desenvolvimento`; `sandbox` e `producao` são
   protegidas e só recebem fast-forward com o CI verde.
2. **Antes de abrir o PR**: `./vendor/bin/pint`, `./vendor/bin/pest` e
   `npm run build` passando (tudo em container, ver README).
3. **Convenções**: código em inglês, comentários e docs em português;
   nada hardcoded (config/platform.php e .env); toda string de UI passa por
   `__()` nos três idiomas; todo comportamento novo vem com teste.
4. **Segurança**: vulnerabilidades vão pelo canal privado (SECURITY.md),
   nunca por issue.
5. **Commits**: mensagem no imperativo, descrevendo o comportamento
   (`feat(admin): ...`, `fix(api): ...`).
