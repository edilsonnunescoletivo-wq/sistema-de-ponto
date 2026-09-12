# Sistema de Ponto

Sistema web de registro e tratamento de ponto, separado do Soluções Condo e preparado para hospedagem PHP/MySQL na Locaweb.

## Executar localmente

Requisitos: PHP 8.1+ com PDO SQLite.

```bash
cp .env.example .env
php -S localhost:8000 public/router.php
```

Acesse `http://localhost:8000`. O banco e os dados demonstrativos são criados automaticamente.

- E-mail: `admin@pontocerto.local`
- Senha: `Admin@123`

Troque a senha antes de publicar.

## Estrutura inicial

- autenticação e controle de sessão;
- dashboard operacional;
- empresas e colaboradores;
- jornadas e marcações;
- tratamento de inconsistências;
- espelho de ponto e relatórios;
- auditoria e configurações;
- endpoint para o futuro agente comunicador.

## Importação de marcações

O módulo aceita AFD com registros de marcação do tipo 3 no formato tradicional e CSV separado por vírgula ou ponto e vírgula:

```text
nsr;data_hora;cpf_ou_matricula
1001;2026-09-12 08:00:00;0001
```

Após a importação, atribua uma jornada ao colaborador e use **Processar período**. O sistema calcula horas trabalhadas, extras, atrasos, período noturno e cria ocorrências para marcações ausentes ou faltas.

## Publicação na Locaweb

Configure as variáveis MySQL no `.env`, importe `database/schema.mysql.sql`, aponte o domínio para a pasta `public` e habilite HTTPS.
