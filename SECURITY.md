# Política de segurança

Segurança é a prioridade número um deste kit. Se você encontrou uma
vulnerabilidade, obrigado por avisar antes de divulgar.

## Como reportar

- **Não abra issue pública** para vulnerabilidades.
- Use o canal privado do GitHub: *Security → Report a vulnerability* neste
  repositório (Private Vulnerability Reporting) — é o caminho preferido.
- Sem conta no GitHub, escreva para **kelvindk9@gmail.com**, com
  "[segurança] tws-laravel-starter-kit" no assunto.
- Vulnerabilidade de um **projeto construído com o kit** (e não do kit em
  si) vai para o responsável por aquele projeto.
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

| Linha | Suporte |
|---|---|
| 2.x (branch `producao` e a última tag `v2.*`) | Correções de segurança e de defeitos |
| 1.x (branch `1.x`) | Só correções de segurança, até 6 meses depois do lançamento da 2.0.0 |
| Anteriores à 1.0 | Sem suporte |

## O que já está coberto

A pasta `docs/` descreve as proteções de segurança do kit (comece por
`docs/seguranca.md`) e a seção *Pendências conhecidas* do README lista, de
forma honesta, o que fica a cargo do projeto derivado ou da infraestrutura
(WAF/CDN contra ataque distribuído, CSP sem `unsafe-inline`, banco efêmero
para demo pública, PITR, TOTP, allowlist por chave).
Cada release passa por: testes automatizados, `composer audit`,
`npm audit`, CodeQL no JavaScript e Dependabot.

---

# Security policy (English)

Please **do not open public issues** for vulnerabilities. Use GitHub's
*Security → Report a vulnerability* on this repository (preferred), or email
**kelvindk9@gmail.com** with "[security] tws-laravel-starter-kit" in the
subject. We acknowledge
within 2 business days, triage within 5, and target 14 days for high or
critical fixes, followed by coordinated disclosure. Only the `producao`
branch and the latest tag receive security fixes.
