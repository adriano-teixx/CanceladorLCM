# 📄 CanceladorLCM

![PHP](https://img.shields.io/badge/PHP-%3E%3D8.0-blue.svg)
![SAP B1 Service Layer](https://img.shields.io/badge/SAP%20B1-Service%20Layer-orange.svg)
![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)

**Cancelamento em massa de Lançamentos Contábeis Manuais (LCM) no SAP Business One via CSV e Web.**

Este sistema automatiza o cancelamento em lote de LCMs no SAP Business One, usando apenas a Service Layer, credenciais de login e CompanyDB. Desenvolvido em PHP puro, com interface web simples.

---

## 🚀 Funcionalidades

- 🔗 **Integração com SAP B1 via Service Layer**  
  Autenticação segura (login, senha e CompanyDB) e chamadas RESTful para cancelamento de documentos.

- 📂 **Processamento em lote de arquivos CSV**  
  Leitura, validação e processamento de grandes volumes de lançamentos contábeis de forma automática.

- ⚠️ **Tratamento de exceções inteligente**  
  Captura erros como sessão expirada, acesso negado, documento não encontrado, lançamento já cancelado e outros cenários de negócio, gerando logs detalhados.

- 🌐 **Interface Web simples**  
  Acesse de qualquer lugar com conexão à internet.

- 📝 **Logs detalhados**  
  Registro completo das operações em `logs/app.log` para auditoria e suporte.

---

## 🧰 Tecnologias

- PHP (sem frameworks)
- SAP Business One Service Layer (REST API)
- HTML/CSS básico
- CSV para entrada de dados

---

## 🛠️ Instalação e Execução

1. Clone o repositório:  
   ```bash
   git clone https://github.com/adriano-teixx/CanceladorLCM.git

2. php -S localhost:8000 -t public
