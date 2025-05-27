# firestore-user-sync-mysql

## 🇧🇷 Descrição

Script em PHP para sincronizar dados de usuários da coleção `users` do Firebase Firestore com uma tabela MySQL local, evitando duplicações. Ideal para integração entre bancos NoSQL e relacionais em projetos que utilizam Firebase como backend e MySQL como base de dados local ou legado.

## 🇺🇸 Description

PHP script to sync user data from the Firebase Firestore `users` collection to a local MySQL table, avoiding duplicates. Ideal for projects using Firebase as a backend and MySQL as a local or legacy relational database.

---

## ✅ Pré-requisitos / Requirements

- PHP 7.4 ou superior
- Composer
- Firebase Service Account JSON
- MySQL (por exemplo, via XAMPP ou MariaDB)

---

## ⚙️ Instalação / Setup

```bash
# Clone o repositório
git clone https://github.com/seu-usuario/firestore-user-sync-mysql.git
cd firestore-user-sync-mysql

# Instale as dependências
composer install
