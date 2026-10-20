# Guia de Instalação

## 📋 Requisitos

| Software | Versão |
|----------|--------|
| PHP | 8.0+ |
| MySQL | 8.0+ / MariaDB 10.5+ |
| Apache | 2.4+ |

## 🚀 Instalação Rápida

### 1. Copiar o projeto
```bash
sudo cp -r ar_condicionado_pro /var/www/html/
sudo chown -R www-data:www-data /var/www/html/ar_condicionado_pro
```

### 2. Criar banco
```bash
mysql -uroot -e "CREATE DATABASE ar_condicionado_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3. Importar schema
```bash
mysql -uroot ar_condicionado_pro < banco/schema.sql
```

### 4. Configurar
Edite config/config.php com host, user, pass, dbname.

### 5. Permissões
```bash
sudo chmod -R 755 /var/www/html/ar_condicionado_pro
sudo systemctl restart apache2
```

### 6. Acessar
http://localhost/ar_condicionado_pro/

## 🐛 Solução de Problemas

### Access denied
Verificar config/config.php.

### Table does not exist
Importar schema novamente.

### Erro 500
```bash
sudo tail -50 /var/log/apache2/error.log
```

## 📦 Backup
```bash
tar -czf backup_$(date +%Y%m%d).tar.gz /var/www/html/ar_condicionado_pro
mysqldump -uroot ar_condicionado_pro > banco_$(date +%Y%m%d).sql
```
