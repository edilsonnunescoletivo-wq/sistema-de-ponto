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
- marcação manual com justificativa e trilha de auditoria;
- edição de empresa e colaboradores;
- ativação, desativação e redefinição de senha de usuários;
- relatórios por período, resumo da folha, AEJ e espelho individual;
- aceite eletrônico do espelho com código de integridade;
- fechamento e reabertura controlada de competência.

## Importação de marcações

O módulo aceita AFD com registros de marcação do tipo 3 no formato tradicional e CSV separado por vírgula ou ponto e vírgula:

```text
nsr;data_hora;cpf_ou_matricula
1001;2026-09-12 08:00:00;0001
```

Após a importação, atribua uma jornada ao colaborador e use **Processar período**. O sistema calcula horas trabalhadas, extras, atrasos, período noturno e cria ocorrências para marcações ausentes ou faltas.

## Publicação na Locaweb

Configure as variáveis MySQL no `.env`, importe `database/schema.mysql.sql`, aponte o domínio para a pasta `public` e habilite HTTPS.

O arquivo `public/.htaccess` já contém o redirecionamento das rotas e cabeçalhos básicos de segurança para o Apache da Locaweb. Antes da entrada em produção, gere um `APP_KEY` exclusivo, altere a senha inicial e não publique o arquivo `.env` em pasta acessível pela web.
