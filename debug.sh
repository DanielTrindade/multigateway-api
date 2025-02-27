#!/bin/bash

# Cores para formatação
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Determine o comando do Docker Compose
if command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
elif command -v docker &> /dev/null && docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
else
    echo -e "${RED}Docker Compose não encontrado. Por favor, instale o Docker Compose.${NC}"
    exit 1
fi

echo -e "${BLUE}=== INÍCIO DO DIAGNÓSTICO DO AMBIENTE MULTIGATEWAY ===${NC}\n"

# Verificar status dos contêineres
echo -e "${BLUE}[INFO]${NC} Verificando status dos contêineres..."
$DOCKER_COMPOSE ps

# Verificar logs do contêiner app
echo -e "\n${BLUE}[INFO]${NC} Verificando logs do contêiner app..."
$DOCKER_COMPOSE logs --tail=50 app

# Verificar estrutura de diretórios no contêiner
echo -e "\n${BLUE}[INFO]${NC} Verificando estrutura de diretórios no contêiner..."
$DOCKER_COMPOSE exec app ls -la || echo -e "${RED}[ERRO]${NC} Não foi possível acessar o contêiner app."

# Verificar existência do composer.json
echo -e "\n${BLUE}[INFO]${NC} Verificando composer.json no contêiner..."
$DOCKER_COMPOSE exec app cat composer.json || echo -e "${RED}[ERRO]${NC} composer.json não encontrado no contêiner."

# Verificar vendor
echo -e "\n${BLUE}[INFO]${NC} Verificando pasta vendor no contêiner..."
$DOCKER_COMPOSE exec app ls -la vendor || echo -e "${YELLOW}[AVISO]${NC} Pasta vendor não encontrada no contêiner."

# Verificar autoload.php
echo -e "\n${BLUE}[INFO]${NC} Verificando vendor/autoload.php no contêiner..."
$DOCKER_COMPOSE exec app cat vendor/autoload.php 2>/dev/null || echo -e "${YELLOW}[AVISO]${NC} autoload.php não encontrado."

# Verificar versão do PHP
echo -e "\n${BLUE}[INFO]${NC} Verificando versão do PHP no contêiner..."
$DOCKER_COMPOSE exec app php -v || echo -e "${RED}[ERRO]${NC} Não foi possível verificar a versão do PHP."

# Verificar versão do Composer
echo -e "\n${BLUE}[INFO]${NC} Verificando versão do Composer no contêiner..."
$DOCKER_COMPOSE exec app composer --version || echo -e "${RED}[ERRO]${NC} Não foi possível verificar a versão do Composer."

# Verificar configuração do Laravel
echo -e "\n${BLUE}[INFO]${NC} Verificando arquivo .env do Laravel..."
$DOCKER_COMPOSE exec app cat .env || echo -e "${YELLOW}[AVISO]${NC} Arquivo .env não encontrado no contêiner."

# Tentar executar comando PHP Artisan
echo -e "\n${BLUE}[INFO]${NC} Tentando executar PHP Artisan..."
$DOCKER_COMPOSE exec app php artisan --version || echo -e "${RED}[ERRO]${NC} Não foi possível executar o PHP Artisan."

# Verificar conexão com o banco de dados
echo -e "\n${BLUE}[INFO]${NC} Verificando conexão com o banco de dados..."
$DOCKER_COMPOSE exec app php artisan db:monitor || echo -e "${YELLOW}[AVISO]${NC} Não foi possível verificar a conexão com o banco de dados."

echo -e "\n${GREEN}=== FIM DO DIAGNÓSTICO ===${NC}"
echo -e "${YELLOW}Se você continuar enfrentando problemas, verifique se:${NC}"
echo "1. O arquivo composer.json está presente na pasta multigateway-app"
echo "2. As permissões dos arquivos estão corretas"
echo "3. O PHP Dockerfile está configurado corretamente"
echo "4. O contêiner MySQL está acessível pelo contêiner da aplicação"
echo -e "${GREEN}Para resolver o problema de dependências, tente:${NC}"
echo "$DOCKER_COMPOSE exec app composer install --no-scripts"
echo -e "${BLUE}====================================${NC}\n"