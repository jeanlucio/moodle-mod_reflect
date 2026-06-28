# Moodle Activity Reflect

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-mod_reflect/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-mod_reflect/actions/workflows/ci.yml)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Stable-green?style=flat-square)

[English](#english) | [Português](#português)

---

## English

The **Reflect Activity** is a modern self-assessment plugin for Moodle. It provides a clean, Google Forms-inspired interface for students to reflect on their learning and submit numerical and open-text responses, complete with real-time autosave functionality.

---

### ✨ Features

* 📝 **Multiple Questions:** Teachers can add multiple questions per activity using an intuitive inline editor.
* 🎚️ **Response Types:** Supports numerical responses (0-100 visual slider) and open-text responses.
* 💾 **Real-Time Autosave:** Responses are saved automatically via AJAX with visual feedback.
* 📊 **Gradebook Integration:** Real-time synchronization of numerical responses to the Moodle gradebook.
* 🧮 **Grading Methods:** Choose between "Manual" (set maximum grade per question) or "Distribute" (divide the total activity grade equally among all questions).
* 💬 **Global Comments:** Optional comment field for students to provide additional context.
* 🎨 **Modern UX/UI:** Clean interface inspired by modern web forms.
* 🔒 **Privacy API Compliant:** Full support for Moodle's Privacy API (GDPR) for data export and deletion.

---

### 🎓 Educational Purpose

Reflect is designed to:

* Encourage student self-assessment and metacognition.
* Provide structured feedback mechanisms without the complexity of rubrics or the Workshop module.
* Reduce student anxiety with automatic background saving.

---

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.1+    |

---

### 🛠️ Installation

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `mod/` directory.
3. Rename the folder to `reflect` (if necessary).
   Final path:
   `your-moodle/mod/reflect/`
4. Visit **Site administration > Notifications** to complete installation.
5. Add the activity to a course.

---

### 📖 Usage

1. Add the **Reflect Activity** to your course.
2. Configure the total grade and grading method.
3. Access the activity as a Teacher to add questions inline directly on the view page.
4. Students access the activity and fill out their self-assessment; progress is saved automatically.

---

### 🧪 Automated Tests

Reflect ships with comprehensive PHPUnit tests ensuring stability across Moodle updates.

#### PHPUnit — Unit & Integration Tests

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `backup_test.php` | 1 | Backup and restore step definitions, preserving questions and user responses |
| `cross_instance_security_test.php` | 1 | Cross-instance capability and security validations |
| `event_test.php` | 2 | Native Moodle events triggering (course module viewed, response submitted) |
| `external_test.php` | 3 | Web service endpoints, grading math, and session capability validations |
| `lib_test.php` | 6 | Instance lifecycle (add, update, delete), gradebook integration and completion rules |
| `privacy_provider_test.php` | 6 | GDPR compliance, metadata declaration, and user data deletion |
| **Total** | **19** | |

**Line coverage by class (PHPUnit + Xdebug):**

| Class | Line coverage |
|-------|:-------------:|
| `event\course_module_viewed` | 100% |
| `privacy\provider` | 87% |
| `event\response_submitted` | 72% |
| `external\delete_question` | 72% |
| `external\save_response` | 68% |
| `external\save_question` | 58% |
| `local\grade_manager` | 57% |
| **Overall** | **72.83%** |

```bash
vendor/bin/phpunit --testsuite mod_reflect
```

#### Behat — Acceptance Tests

| Feature file | Scenarios | What is covered |
|--------------|----------:|----------------|
| `reflect.feature` | 1 | Teacher creates an inline question, student views and submits response, teacher views the responses report |
| **Total** | **1** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@mod_reflect --profile=chrome
```

---

## 📄 License / Licença

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

---

## Português

A atividade **Reflect** é um plugin moderno de autoavaliação para Moodle. Fornece uma interface limpa, inspirada no Google Forms, para que os estudantes reflitam sobre seu aprendizado e enviem respostas numéricas e abertas, com salvamento automático em tempo real.

---

### ✨ Funcionalidades

* 📝 **Múltiplas Perguntas:** Professores podem adicionar várias perguntas por atividade usando um editor inline intuitivo.
* 🎚️ **Tipos de Resposta:** Suporta respostas numéricas (controle deslizante visual de 0-100) e respostas de texto livre.
* 💾 **Autosave em Tempo Real:** As respostas são salvas automaticamente via AJAX com feedback visual.
* 📊 **Integração com Livro de Notas:** Sincronização em tempo real das respostas numéricas com o gradebook do Moodle.
* 🧮 **Métodos de Avaliação:** Escolha entre "Manual" (nota máxima definida por pergunta) ou "Distribuir" (divide a nota total da atividade igualmente entre as perguntas).
* 💬 **Comentários Globais:** Campo opcional para que os estudantes adicionem contexto extra.
* 🎨 **UX/UI Moderna:** Interface inspirada em formulários web modernos.
* 🔒 **Conformidade com a API de Privacidade:** Suporte completo à API de Privacidade do Moodle (LGPD/GDPR) para exportação e exclusão de dados.

---

### 🎓 Finalidade Educacional

O Reflect foi projetado para:

* Estimular a autoavaliação e a metacognição dos estudantes.
* Fornecer mecanismos de feedback estruturados sem a complexidade de rubricas ou do módulo Laboratório de Avaliação (Workshop).
* Reduzir a ansiedade do aluno com o salvamento automático em segundo plano.

---

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5+   |
| PHP        | 8.1+   |

---

### 🛠️ Instalação

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `mod/` do seu Moodle.
3. Renomeie para `reflect` (se necessário).
   Caminho final:
   `seu-moodle/mod/reflect/`
4. Acesse **Administração do site > Notificações** para concluir a instalação.
5. Adicione a atividade a um curso.

---

### 📖 Como Usar

1. Adicione a atividade **Reflect** ao seu curso.
2. Configure a nota total e o método de avaliação desejado.
3. Acesse a atividade como Professor para adicionar perguntas diretamente na própria tela da atividade (inline).
4. Os estudantes acessam a atividade e preenchem a autoavaliação; o progresso é salvo automaticamente.

---

### 🧪 Testes Automatizados

O Reflect inclui testes abrangentes em PHPUnit para garantir a estabilidade a cada atualização do Moodle.

#### PHPUnit — Testes Unitários e de Integração

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `backup_test.php` | 1 | Definições de backup e restore, preservando as perguntas e respostas dos usuários |
| `cross_instance_security_test.php` | 1 | Validações de segurança e capacidades entre instâncias |
| `event_test.php` | 2 | Disparo de eventos nativos do Moodle (visualização do módulo, submissão de respostas) |
| `external_test.php` | 3 | Endpoints de web service, cálculos de nota e validações de capability |
| `lib_test.php` | 6 | Ciclo de vida da instância (adicionar, atualizar, excluir), integração com gradebook e regras de conclusão |
| `privacy_provider_test.php` | 6 | Conformidade com LGPD, declaração de metadados e exclusão de dados de usuário |
| **Total** | **19** | |

**Cobertura de linhas por classe (PHPUnit + Xdebug):**

| Classe | Cobertura de linhas |
|--------|:-------------------:|
| `event\course_module_viewed` | 100% |
| `privacy\provider` | 87% |
| `event\response_submitted` | 72% |
| `external\delete_question` | 72% |
| `external\save_response` | 68% |
| `external\save_question` | 58% |
| `local\grade_manager` | 57% |
| **Total** | **72.83%** |

```bash
vendor/bin/phpunit --testsuite mod_reflect
```

#### Behat — Testes de Aceitação

| Arquivo de feature | Cenários | O que é coberto |
|-------------------|--------:|----------------|
| `reflect.feature` | 1 | Professor cria pergunta na própria tela (inline), aluno visualiza e envia a resposta, professor visualiza relatório de respostas |
| **Total** | **1** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@mod_reflect --profile=chrome
```

---

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio
