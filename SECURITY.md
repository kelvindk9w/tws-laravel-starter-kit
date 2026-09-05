# Política de segurança

Segurança é a prioridade número um deste kit. Se você encontrou uma
vulnerabilidade, obrigado por avisar antes de divulgar.

## Como reportar

- **Não abra issue pública** para vulnerabilidades.
- Use o canal privado do GitHub: *Security → Report a vulnerability* neste
  repositório (Private Vulnerability Reporting), ou escreva para o e-mail de
  segurança configurado em `PLATFORM_SUPPORT_EMAIL` do projeto que você
  estiver usando.
- Inclua: versão/commit, passos para reproduzir, impacto e, se possível,
  uma prova de conceito.

## O que esperar

| Etapa | Prazo alvo |
|---|---|
| Confirmação de recebimento | 2 dias úteis |
| Avaliação inicial e severidade | 5 dias úteis |
| Correção para severidade alta/crítica | 14 dias |
| Divulgação coordenada | após a correção publicada |

Créditos são dados a quem reportar, se desejar.

## Versões suportadas

Apenas a branch `producao` e a última tag recebem correções de segurança.

## O que já está coberto

O README descreve o checklist de segurança do kit (32 itens) e a seção
*Pendências conhecidas* lista, de forma honesta, o que fica a cargo do
projeto derivado ou da infraestrutura (WAF, PITR, TOTP, allowlist por chave).
Cada release passa por: testes automatizados, `composer audit`,
`npm audit`, CodeQL no JavaScript e Dependabot.

---

# Security policy (English)

Please **do not open public issues** for vulnerabilities. Use GitHub's
*Security → Report a vulnerability* on this repository. We acknowledge
within 2 business days, triage within 5, and target 14 days for high or
critical fixes, followed by coordinated disclosure. Only the `producao`
branch and the latest tag receive security fixes.
